<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductHasImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Exception;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $products = Product::with(['category', 'images'])
                ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
                ->when($request->type, fn($q) => $q->where('type', $request->type))
                ->when($request->status !== null, fn($q) => $q->where('status', $request->boolean('status')))
                ->when($request->search, fn($q) => $q->where('name', 'LIKE', "%{$request->search}%"))
                ->latest()
                ->paginate($request->get('per_page', 15));

            return $this->successResponse(
                'Products fetched successfully',
                $this->formatProductsResponse($products)
            );
        } catch (Exception $e) {
            return $this->errorResponse('Failed to fetch products', 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateProductData($request);

            DB::beginTransaction();

            $productData = $this->prepareProductData($validated);
            $productData['type'] = strtolower($validated['type']);

            $product = Product::create($productData);

            // ✅ Main image
            if (!empty($validated['image'])) {
                $mainImagePath = $this->saveBase64Image($validated['image'], 'products/main');
                $product->update(['image' => $mainImagePath]);
            }

            // ✅ Gallery images
            if (!empty($validated['images'])) {
                $this->saveGalleryImages($product, $validated['images']);
            }

            // ✅ Customizations
            if ($product->type === 'customizable') {
                $this->handleCustomizations($product, $request);
            }

            DB::commit();

            return $this->successResponse(
                'Product created successfully',
                $this->formatSingleProduct($product->load(['category', 'images'])),
                201
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    public function show($slug): JsonResponse
    {
        try {
            $product = Product::with(['category', 'images'])->where('slug', $slug)->firstOrFail();

            if ($product->type === 'customizable') {
                $product->load([
                    'skin_tones.images', 'hairs.images', 'noses.images',
                    'eyes.images', 'mouths.images', 'dresses.images',
                    'crowns.images', 'base_cards.images', 'beards.images',
                ]);
            }

            return $this->successResponse(
                'Product fetched successfully',
                $this->formatSingleProduct($product)
            );
        } catch (Exception $e) {
            return $this->errorResponse('Product not found', 404);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        if (!Gate::allows('update-products')) {
            return $this->unauthorizedResponse();
        }

        try {
            $product = Product::findOrFail($id);
            $validated = $this->validateProductData($request, $product->id);

            DB::beginTransaction();

            $productData = $this->prepareProductData($validated);
            $product->update($productData);

            // ✅ Main image update
            if (!empty($validated['image'])) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $mainImagePath = $this->saveBase64Image($validated['image'], 'products/main');
                $product->update(['image' => $mainImagePath]);
            }

            // ✅ Gallery images update
            if (isset($validated['images'])) {
                foreach ($product->images as $image) {
                    Storage::disk('public')->delete($image->image);
                }
                $product->images()->delete();

                if (!empty($validated['images'])) {
                    $this->saveGalleryImages($product, $validated['images']);
                }
            }

            // ✅ Customizations
            if ($product->type === 'customizable') {
                $this->handleCustomizations($product, $request, true);
            }

            DB::commit();

            return $this->successResponse(
                'Product updated successfully',
                $this->formatSingleProduct($product->load(['category', 'images']))
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update product: ' . $e->getMessage(), 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $product = Product::findOrFail($id);

            DB::beginTransaction();

            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image);
            }

            if ($product->type === 'customizable') {
                $this->deleteCustomizationImages($product);
            }

            $product->delete();

            DB::commit();

            return $this->successResponse('Product deleted successfully');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to delete product', 500);
        }
    }

    // ================= Helpers =================

    private function validateProductData(Request $request, $productId = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug' . ($productId ? ',' . $productId : ''),
            'type' => 'required|in:Simple,Customizable',
            'price' => 'required|numeric|min:0',
            'status' => 'required|boolean',
            'offer_price' => 'nullable|numeric|min:0|lt:price',
            'category_id' => 'required|exists:categories,id',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'required|string',
        ];

        if (strtolower($request->type) === 'customizable') {
            $customizableFields = [
                'base_cards', 'skin_tones', 'hairs', 'noses',
                'eyes', 'mouths', 'dresses', 'crowns', 'beards'
            ];

            foreach ($customizableFields as $field) {
                $rules[$field] = 'sometimes|array';
                $rules[$field . '.*.name'] = 'required|string|max:255';
                $rules[$field . '.*.images'] = 'sometimes|array';
                $rules[$field . '.*.images.*'] = 'required|string';
            }
        }

        return $request->validate($rules);
    }

    private function prepareProductData(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'slug' => $validated['slug']
                ? $validated['slug'] . "-" . rand(1000, 9999)
                : (Str::slug($validated['name']) . "-" . rand(1000, 9999)),
            'type' => $validated['type'],
            'price' => $validated['price'],
            'status' => $validated['status'],
            'category_id' => $validated['category_id'],
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'offer_price' => $validated['offer_price'] ?? null,
        ];
    }

    private function saveGalleryImages(Product $product, array $images): void
    {
        foreach ($images as $imageBase64) {
            if (!empty($imageBase64)) {
                $imagePath = $this->saveBase64Image($imageBase64, 'products/gallery');
                ProductHasImage::create([
                    'product_id' => $product->id,
                    'image' => $imagePath,
                ]);
            }
        }
    }

    private function handleCustomizations(Product $product, Request $request, bool $isUpdate = false): void
    {
        $relations = [
            'skin_tones', 'hairs', 'noses', 'eyes', 'mouths',
            'dresses', 'crowns', 'base_cards', 'beards'
        ];

        foreach ($relations as $relation) {
            if ($request->has($relation)) {
                if ($isUpdate) {
                    foreach ($product->{$relation} as $item) {
                        foreach ($item->images as $image) {
                            Storage::disk('public')->delete($image->image);
                        }
                    }
                    $product->{$relation}()->delete();
                }

                foreach ($request->$relation as $itemData) {
                    $item = $product->{$relation}()->create(['name' => $itemData['name']]);

                    if (!empty($itemData['images'])) {
                        foreach ($itemData['images'] as $imageBase64) {
                            $path = $this->saveBase64Image($imageBase64, "products/customizations/{$relation}");
                            $item->images()->create(['image' => $path]);
                        }
                    }
                }
            }
        }
    }

    private function deleteCustomizationImages(Product $product): void
    {
        $relations = [
            'skin_tones', 'hairs', 'noses', 'eyes', 'mouths',
            'dresses', 'crowns', 'base_cards', 'beards'
        ];

        foreach ($relations as $relation) {
            foreach ($product->{$relation} as $item) {
                foreach ($item->images as $image) {
                    Storage::disk('public')->delete($image->image);
                }
            }
        }
    }

    private function saveBase64Image(string $base64Image, string $folder): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
            $extension = strtolower($type[1]);
        } else {
            $imageData = $base64Image;
            $extension = 'png';
        }

        $decodedImage = base64_decode($imageData);

        if ($decodedImage === false) {
            throw new Exception('Failed to decode base64 image');
        }

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $extension = 'png';
        }

        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $filePath = "products/$folder/$fileName";

        Storage::disk('public')->put($filePath, $decodedImage);

        return $filePath;
    }

    // ✅ Responses
    private function formatProductsResponse($products): array
    {
        return [
            'data' => $products->getCollection()->map(fn($p) => $this->formatSingleProduct($p)),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ];
    }

    private function formatSingleProduct($product): array
    {
        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type,
            'price' => $product->price,
            'offer_price' => $product->offer_price,
            'final_price' => $product->offer_price ?? $product->price,
            'status' => $product->status,
        ];

        if ($product->image) {
            $data['image'] = asset('storage/' . $product->image);
        }

        if ($product->relationLoaded('images')) {
            $data['gallery_images'] = $product->images->map(fn($img) => [
                'id' => $img->id,
                'url' => asset('storage/' . $img->image),
                'alt' => $product->name
            ])->toArray();
        }

        if ($product->relationLoaded('category') && $product->category) {
            $data['category'] = [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ];
        }

        return $data;
    }

    private function successResponse(string $message, $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'status' => $status, 'message' => $message, 'data' => $data], $status);
    }

    private function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'status' => $status, 'message' => $message], $status);
    }

    private function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false, 'status' => 422, 'message' => 'Validation failed', 'errors' => $e->errors()
        ], 422);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return $this->errorResponse('Unauthorized access', 401);
    }
}
