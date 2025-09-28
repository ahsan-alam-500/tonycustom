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
    /**
     * Display a listing of products with pagination.
     */
public function index(Request $request): JsonResponse
{
    try {
        // Fetch products with relations
        $products = Product::with([
            'category',
            'images',
            'skin_tones', 'hairs', 'noses', 'eyes', 'mouths',
            'dresses', 'crowns', 'base_cards', 'beards'
        ])
        ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
        ->when($request->type, fn($q) => $q->where('type', $request->type))
        ->when($request->status !== null, fn($q) => $q->where('status', $request->status === 'true'))
        ->when($request->search, fn($q) => $q->where('name', 'LIKE', "%{$request->search}%"))
        ->latest()
        ->paginate($request->get('per_page', 15));

        // Transform collection to add full URLs
        $products->getCollection()->transform(function ($p) {
            // Main image
            if ($p->image) {
                $p->image = url('public/storage/' . $p->image);
                \Log::info($p->image);
            }

            // Gallery images
            if ($p->relationLoaded('images')) {
                $p->gallery_images = $p->images->map(function ($img) {
                    return [
                        'id' => $img->id,
                        'url' => url('public/storage/' . $img->image),
                    ];
                })->toArray();
            }

            // Customizable options
            if ($p->type === 'customizable') {
                $relations = ['skin_tones','hairs','noses','eyes','mouths','dresses','crowns','base_cards','beards'];
                $customizations = [];
                foreach ($relations as $relation) {
                    if ($p->relationLoaded($relation)) {
                        $customizations[$relation] = $p->{$relation}->map(function ($item) {
                            $itemData = [
                                'id' => $item->id,
                                'name' => $item->name,
                                'image' => $item->image ? url('public/storage/' . $item->image) : null,
                            ];
                            return $itemData;
                        })->toArray();
                    }
                }
                $p->customizations = $customizations;
            }
            \Log::info($p);
            return $p;
        });

    \Log::info($products);

        return $this->successResponse(
            'Products fetched successfully',
            $this->formatProductsResponse($products)
        );
    } catch (Exception $e) {
        return $this->errorResponse('Failed to fetch products: ' . $e->getMessage(), 500);
    }
}

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $this->validateProductData($request);

            DB::beginTransaction();

            $productData = $this->prepareProductData($validated);
            $productData['type'] = strtolower($validated['type']);

            $product = Product::create($productData);

            // main image
            if (!empty($validated['image'])) {
                $mainImagePath = $this->saveBase64Image($validated['image'], 'products/main');
                $product->update(['image' => $mainImagePath]);
            }

            // gallery images
            if (!empty($validated['images'])) {
                $this->saveGalleryImages($product, $validated['images']);
            }

            // customizations
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

    /**
     * Show product details.
     */
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

    /**
     * Update product.
     */
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

            // main image
            if (!empty($validated['image'])) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $mainImagePath = $this->saveBase64Image($validated['image'], 'products/main');
                $product->update(['image' => $mainImagePath]);
            }

            // gallery images
            if (isset($validated['images'])) {
                foreach ($product->images as $image) {
                    Storage::disk('public')->delete($image->image);
                }
                $product->images()->delete();

                if (!empty($validated['images'])) {
                    $this->saveGalleryImages($product, $validated['images']);
                }
            }

            // customizations
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

    /**
     * Delete product.
     */
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

    /**
     * Validation rules.
     */
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

        if ($request->type === 'Customizable') {
            $customFields = [
                'base_cards', 'skin_tones', 'hairs', 'noses',
                'eyes', 'mouths', 'dresses', 'crowns', 'beards'
            ];

            foreach ($customFields as $field) {
                $rules[$field] = 'sometimes|array';
                $rules[$field . '.*.name'] = 'sometimes|string|max:255';
                $rules[$field . '.*.images'] = 'sometimes|array';
                $rules[$field . '.*.images.*'] = 'sometimes|string';
            }
        }

        return $request->validate($rules);
    }

    /**
     * Prepare product data.
     */
    private function prepareProductData(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'] ? $validated['slug']."-".rand(1000, 9999) : (Str::slug($validated['name'])."-".rand(1000, 9999)),
            'type' => $validated['type'],
            'price' => $validated['price'],
            'status' => $validated['status'],
            'category_id' => $validated['category_id'],
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'offer_price' => $validated['offer_price'] ?? null,
        ];
    }

    /**
     * Save gallery images.
     */
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

    /**
     * Handle customizable options safely.
     */
 /**
 * Handle customizable options.
 */
private function handleCustomizations(Product $product, Request $request, bool $isUpdate = false): void
{
    $relations = [
        'skin_tones', 'hairs', 'noses', 'eyes', 'mouths',
        'dresses', 'crowns', 'base_cards', 'beards'
    ];

    foreach ($relations as $relation) {
        if ($request->has($relation) && is_array($request->$relation)) {

            // Update mode → old items & images delete
            if ($isUpdate) {
                foreach ($product->{$relation} as $item) {
                    foreach ($item->images as $image) {
                        Storage::disk('public')->delete($image->image);
                    }
                }
                $product->{$relation}()->delete();
            }

            // Loop over incoming items
            foreach ($request->$relation as $itemData) {
                // Create main item record
                $item = $product->{$relation}()->create([
                    'name' => $itemData['name'] ?? '',
                    'product_id' => $product->id,
                    // 'image' => 'abc', // main image column nullable হলে null, নাহলে first image later set করা যাবে
                    'image' => $this->saveBase64Image($itemData['images'][0], "products/customizations/{$relation}")
                ]);

                // Loop over images array if exists
                if (!empty($itemData['images']) && is_array($itemData['images'])) {
                    foreach ($itemData['images'] as $index => $imageBase64) {
                        $path = $this->saveBase64Image($imageBase64, "products/customizations/{$relation}");
                        $item->images()->create(['image' => $path]);

                        // যদি main image column nullable না হয়, প্রথম image এখানে set করতে পারো
                        if ($index === 0 && $item->image === null) {
                            $item->update(['image' => $path]);
                        }
                    }
                }
            }
        }
    }
}


    /**
     * Delete customization images.
     */
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

    /**
     * Save base64 image and return path.
     */
    private function saveBase64Image(string $base64Image, string $folder): string
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
            $extension = strtolower($type[1]);
        } else {
            $imageData = $base64Image;
            $extension = 'png';
        }

        $imageData = str_replace(' ', '+', $imageData);
        $decodedImage = base64_decode($imageData);

        if ($decodedImage === false) {
            throw new Exception('Failed to decode base64 image');
        }

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $extension = 'png';
        }

        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $filePath = $folder . '/' . $fileName;

        if (!Storage::disk('public')->put($filePath, $decodedImage)) {
            throw new Exception('Failed to save image to storage');
        }

        return $filePath;
    }

    /**
     * Format single product for API response.
     */
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
            'discount_percentage' => $product->offer_price ? round((($product->price - $product->offer_price)/$product->price)*100,2) : 0,
            'status' => $product->status,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'created_at' => $product->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $product->updated_at->format('Y-m-d H:i:s'),
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
                'slug' => $product->category->slug ?? null,
            ];
        }

        if ($product->type === 'customizable') {
            $customizations = [];
            $relations = ['skin_tones', 'hairs', 'noses', 'eyes', 'mouths', 'dresses', 'crowns', 'base_cards', 'beards'];

            foreach ($relations as $relation) {
                if ($product->relationLoaded($relation)) {
                    $customizations[$relation] = $product->{$relation}->map(function ($item) {
                        $itemData = ['id' => $item->id, 'name' => $item->name];
                        if ($item->relationLoaded('images')) {
                            $itemData['images'] = $item->images->map(fn($img) => [
                                'id' => $img->id,
                                'url' => asset('storage/' . $img->image)
                            ])->toArray();
                        }
                        return $itemData;
                    })->toArray();
                }
            }

            if (!empty($customizations)) {
                $data['customizations'] = $customizations;
            }
        }

        return $data;
    }

    /**
     * Format products response for pagination.
     */
    private function formatProductsResponse($products): array
    {
        return [
            'data' => $products->getCollection()->map(fn($p) => $this->formatSingleProduct($p)),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
                'has_more_pages' => $products->hasMorePages(),
            ]
        ];
    }

    /**
     * JSON success response.
     */
    private function successResponse(string $message, $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'status' => $status, 'message' => $message, 'data' => $data], $status);
    }

    /**
     * JSON error response.
     */
    private function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['success' => false, 'status' => $status, 'message' => $message], $status);
    }

    /**
     * Validation error response.
     */
    private function validationErrorResponse(ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status' => 422,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    }

    /**
     * Unauthorized response.
     */
    private function unauthorizedResponse(): JsonResponse
    {
        return $this->errorResponse('Unauthorized access', 401);
    }
}
