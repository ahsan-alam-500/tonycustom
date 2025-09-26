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
            $products = Product::with(['category', 'images'])
                ->when($request->category_id, function ($query) use ($request) {
                    $query->where('category_id', $request->category_id);
                })
                ->when($request->type, function ($query) use ($request) {
                    $query->where('type', $request->type);
                })
                ->when($request->status !== null, function ($query) use ($request) {
                    $query->where('status', $request->status === 'true');
                })
                ->when($request->search, function ($query) use ($request) {
                    $query->where('name', 'LIKE', "%{$request->search}%");
                })
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

    /**
     * Store a newly created product.
     */
    public function store(Request $request): JsonResponse
    {

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Product Returning',
            'data'    => ['info' => $request->all()]
        ]);

        if (!Gate::allows('create-products')) {
            return $this->unauthorizedResponse();
        }

        try {
            $validated = $this->validateProductData($request);

            DB::beginTransaction();

            // Prepare product data
            $productData = $this->prepareProductData($validated);

            // Create product
            $product = Product::create($productData);

            // Handle main image (single image)
            if (!empty($validated['image'])) {
                $mainImagePath = $this->saveBase64Image($validated['image'], 'products/main');
                $product->update(['image' => $mainImagePath]);
            }

            // Handle gallery images (multiple images)
            if (!empty($validated['images'])) {
                $this->saveGalleryImages($product, $validated['images']);
            }

            // Handle customizable options if product type is customizable
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
    public function show($id): JsonResponse
    {
        try {
            $product = Product::with(['category', 'images'])->findOrFail($id);

            // Load customization relationships if product is customizable
            if ($product->type === 'customizable') {
                $product->load([
                    'skin_tones.images',
                    'hairs.images',
                    'noses.images',
                    'eyes.images',
                    'mouths.images',
                    'dresses.images',
                    'crowns.images',
                    'base_cards.images',
                    'beards.images',
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

            // Update product data
            $productData = $this->prepareProductData($validated);
            $product->update($productData);

            // Handle main image update
            if (!empty($validated['image'])) {
                // Delete old main image if exists
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $mainImagePath = $this->saveBase64Image($validated['image'], 'products/main');
                $product->update(['image' => $mainImagePath]);
            }

            // Handle gallery images update
            if (isset($validated['images'])) {
                // Delete existing gallery images
                foreach ($product->images as $image) {
                    Storage::disk('public')->delete($image->image);
                }
                $product->images()->delete();

                // Save new gallery images
                if (!empty($validated['images'])) {
                    $this->saveGalleryImages($product, $validated['images']);
                }
            }

            // Handle customizable options update
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
        if (!Gate::allows('delete-products')) {
            return $this->unauthorizedResponse();
        }

        try {
            $product = Product::findOrFail($id);

            DB::beginTransaction();

            // Delete main image
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            // Delete gallery images
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image);
            }

            // Delete customization images if exists
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
     * Validate product data.
     */
    private function validateProductData(Request $request, $productId = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug' . ($productId ? ',' . $productId : ''),
            'type' => 'required|in:simple,customizable',
            'price' => 'required|numeric|min:0',
            'status' => 'required|boolean',
            'offer_price' => 'nullable|numeric|min:0|lt:price',
            'category_id' => 'required|exists:categories,id',
            'short_description' => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'image' => 'nullable|string', // Base64 main image
            'images' => 'nullable|array|max:10', // Gallery images array
            'images.*' => 'nullable|string', // Each gallery image as base64
        ];

        // Add customizable validation rules if type is customizable
        if ($request->type === 'customizable') {
            $customizableFields = [
                'base_cards', 'skin_tones', 'hairs', 'noses',
                'eyes', 'mouths', 'dresses', 'crowns', 'beards'
            ];

            foreach ($customizableFields as $field) {
                $rules[$field] = 'sometimes|array';
                $rules[$field . '.*.name'] = 'required|string|max:255';
                $rules[$field . '.*.images'] = 'sometimes|array';
                $rules[$field . '.*.images.*'] = 'string'; // Base64 images
            }
        }

        return $request->validate($rules);
    }

    /**
     * Prepare product data for storage.
     */
    private function prepareProductData(array $validated): array
    {
        $data = [
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'type' => $validated['type'],
            'price' => $validated['price'],
            'status' => $validated['status'],
            'category_id' => $validated['category_id'],
            'short_description' => $validated['short_description'] ?? null,
            'description' => $validated['description'] ?? null,
            'offer_price' => $validated['offer_price'] ?? null,
        ];

        return $data;
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
     * Handle customizable options.
     */
    private function handleCustomizations(Product $product, Request $request, bool $isUpdate = false): void
    {
        $relations = [
            'skin_tones', 'hairs', 'noses', 'eyes', 'mouths',
            'dresses', 'crowns', 'base_cards', 'beards'
        ];

        foreach ($relations as $relation) {
            if ($request->has($relation)) {
                if ($isUpdate) {
                    // Delete existing customization images
                    foreach ($product->{$relation} as $item) {
                        foreach ($item->images as $image) {
                            Storage::disk('public')->delete($image->image);
                        }
                    }
                    $product->{$relation}()->delete();
                }

                foreach ($request->$relation as $itemData) {
                    $item = $product->{$relation}()->create([
                        'name' => $itemData['name'],
                    ]);

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
     * Save base64 image to storage.
     */
    private function saveBase64Image(string $base64Image, string $folder): string
    {
        // Validate base64 image format
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            throw new Exception('Invalid base64 image format');
        }

        // Extract image data
        $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
        $extension = strtolower($type[1]);

        // Validate image extension
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            throw new Exception('Invalid image type. Allowed: jpg, jpeg, png, gif, webp');
        }

        // Decode base64
        $imageData = str_replace(' ', '+', $imageData);
        $decodedImage = base64_decode($imageData);

        if ($decodedImage === false) {
            throw new Exception('Failed to decode base64 image');
        }

        // Generate unique filename
        $fileName = time() . '_' . uniqid() . '.' . $extension;
        $filePath = $folder . '/' . $fileName;

        // Save to storage
        if (!Storage::disk('public')->put($filePath, $decodedImage)) {
            throw new Exception('Failed to save image to storage');
        }

        return $filePath;
    }

    /**
     * Format products response for pagination.
     */
    private function formatProductsResponse($products): array
    {
        return [
            'data' => $products->getCollection()->map(function ($product) {
                return $this->formatSingleProduct($product);
            }),
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
     * Format single product response.
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
            'discount_percentage' => $product->offer_price ?
                round((($product->price - $product->offer_price) / $product->price) * 100, 2) : 0,
            'status' => $product->status,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'created_at' => $product->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $product->updated_at->format('Y-m-d H:i:s'),
        ];

        // Main image
        if ($product->image) {
            $data['image'] = asset('storage/' . $product->image);
        }

        // Gallery images
        if ($product->relationLoaded('images')) {
            $data['gallery_images'] = $product->images->map(function ($image) use ($product) {
                return [
                    'id' => $image->id,
                    'url' => asset('storage/' . $image->image),
                    'alt' => $product->name
                ];
            })->toArray();
        }

        // Category
        if ($product->relationLoaded('category') && $product->category) {
            $data['category'] = [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug ?? null,
            ];
        }

        // Customization options (only for customizable products)
        if ($product->type === 'customizable') {
            $customizations = [];

            $relations = [
                'skin_tones', 'hairs', 'noses', 'eyes', 'mouths',
                'dresses', 'crowns', 'base_cards', 'beards'
            ];

            foreach ($relations as $relation) {
                if ($product->relationLoaded($relation)) {
                    $customizations[$relation] = $product->{$relation}->map(function ($item) {
                        $itemData = [
                            'id' => $item->id,
                            'name' => $item->name,
                        ];

                        if ($item->relationLoaded('images')) {
                            $itemData['images'] = $item->images->map(function ($img) {
                                return [
                                    'id' => $img->id,
                                    'url' => asset('storage/' . $img->image)
                                ];
                            })->toArray();
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
     * Return success response.
     */
    private function successResponse(string $message, $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $status,
            'message' => $message,
            'data' => $data
        ], $status);
    }

    /**
     * Return error response.
     */
    private function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status' => $status,
            'message' => $message,
        ], $status);
    }

    /**
     * Return validation error response.
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
     * Return unauthorized response.
     */
    private function unauthorizedResponse(): JsonResponse
    {
        return $this->errorResponse('Unauthorized access', 401);
    }
}
