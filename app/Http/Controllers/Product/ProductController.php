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
            $products = Product::with([
                "category",
                "images",
                "skin_tones",
                "hairs",
                "noses",
                "eyes",
                "mouths",
                "dresses",
                "crowns",
                "base_cards",
                "beards",
                "trading_fronts",
                "trading_backs",
            ])
                ->when(
                    $request->category_id,
                    fn($q) => $q->where("category_id", $request->category_id)
                )
                ->when(
                    $request->type,
                    fn($q) => $q->where("type", $request->type)
                )
                ->when(
                    $request->status !== null,
                    fn($q) => $q->where("status", $request->status === "true")
                )
                ->when(
                    $request->search,
                    fn($q) => $q->where("name", "LIKE", "%{$request->search}%")
                )
                ->latest()
                ->paginate($request->get("per_page", 15));

            $products->getCollection()->transform(function ($p) {
                // Main product image url saving to database is relative
                $p->image = $p->image
                    ? "storage/" . ltrim($p->image, "/")
                    : null;

                // Gallery images
                $p->gallery_images = $p->images
                    ->map(
                        fn($img) => [
                            "id" => $img->id,
                            "url" => $img->image
                                ? "storage/" . ltrim($img->image, "/")
                                : null,
                            "alt" => $img->alt ?? null,
                        ]
                    )
                    ->toArray();

                // Customizations
                $custom_relations = [
                    "skin_tones",
                    "hairs",
                    "noses",
                    "eyes",
                    "mouths",
                    "dresses",
                    "crowns",
                    "base_cards",
                    "beards",
                    "trading_fronts",
                    "trading_backs",
                ];
                $customizations = [];

                foreach ($custom_relations as $relation) {
                    $customizations[$relation] = $p->{$relation}
                        ->map(
                            fn($item) => [
                                "id" => $item->id,
                                "name" => $item->name,
                                "image" => $item->image
                                    ? "storage/" . ltrim($item->image, "/")
                                    : "Something Went Wrong",
                            ]
                        )
                        ->toArray();
                }

                $p->customizations = $customizations;

                return $p;
            });

            return response()->json([
                "success" => true,
                "status" => 200,
                "message" => "Products fetched successfully",
                "data" => $products,
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "status" => 500,
                "message" => "Failed to fetch products: " . $e->getMessage(),
            ]);
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
            $productData["type"] = strtolower($validated["type"]);

            $product = Product::create($productData);

            // main image
            if (!empty($validated["image"])) {
                $mainImagePath = $this->saveBase64Image(
                    $validated["image"],
                    "products/main"
                );
                $product->update(["image" => $mainImagePath]);
            }

            // gallery images
            if (!empty($validated["images"])) {
                $this->saveGalleryImages($product, $validated["images"]);
            }

            // customizations
            if ($product->type === "trading") {
                $this->handleCustomizations($product, $request);
            }
            if ($product->type === "customizable") {
                $this->handleCustomizations($product, $request);
            }

            DB::commit();

            return $this->successResponse(
                "Product created successfully",
                $this->formatSingleProduct(
                    $product->load(["category", "images"])
                ),
                201
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                "Failed to create product: " . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Show product details.
     */
    public function show($slug): JsonResponse
    {
        try {
            $product = Product::with(["category", "images"])
                ->where("slug", $slug)
                ->firstOrFail();

            // Main product image
            $product->image = $product->image
                ? "storage/" . ltrim($product->image, "/")
                : null;

            // Gallery images
            $product->gallery_images = $product->images
                ->map(
                    fn($img) => [
                        "id" => $img->id,
                        "url" => $img->image
                            ? "storage/" . ltrim($img->image, "/")
                            : null,
                        "alt" => $img->alt ?? null,
                    ]
                )
                ->toArray();

            // Customizations (images included)
            $custom_relations = [
                "skin_tones",
                "hairs",
                "noses",
                "eyes",
                "mouths",
                "dresses",
                "crowns",
                "base_cards",
                "beards",
                "trading_fronts",
                "trading_backs",
            ];
            $customizations = [];

            foreach ($custom_relations as $relation) {
                $customizations[$relation] = $product->{$relation}
                    ->map(
                        fn($item) => [
                            "id" => $item->id,
                            "name" => $item->name,
                            "image" => $item->image
                                ? "storage/" . ltrim($item->image, "/")
                                : null,
                        ]
                    )
                    ->toArray();
            }

            $product->customizations = $customizations;

            return response()->json([
                "success" => true,
                "status" => 200,
                "message" => "Product fetched successfully",
                "data" => $product,
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "status" => 404,
                "message" => "Product not found",
            ]);
        }
    }

    /**
     * Update product.
     */
    public function update(Request $request, $id): JsonResponse
    {
        if (!Gate::allows("update-products")) {
            return $this->unauthorizedResponse();
        }

        try {
            $product = Product::findOrFail($id);
            $validated = $this->validateProductData($request, $product->id);

            DB::beginTransaction();

            $productData = $this->prepareProductData($validated);
            $product->update($productData);

            // main image
            if (!empty($validated["image"])) {
                if ($product->image) {
                    Storage::disk("public")->delete($product->image);
                }
                $mainImagePath = $this->saveBase64Image(
                    $validated["image"],
                    "products/main"
                );
                $product->update(["image" => $mainImagePath]);
            }

            // gallery images
            if (isset($validated["images"])) {
                foreach ($product->images as $image) {
                    Storage::disk("public")->delete($image->image);
                }
                $product->images()->delete();

                if (!empty($validated["images"])) {
                    $this->saveGalleryImages($product, $validated["images"]);
                }
            }

            // customizations
            if ($product->type === "customizable") {
                $this->handleCustomizations($product, $request, true);
            }

            DB::commit();

            return $this->successResponse(
                "Product updated successfully",
                $this->formatSingleProduct(
                    $product->load(["category", "images"])
                )
            );
        } catch (ValidationException $e) {
            DB::rollBack();
            return $this->validationErrorResponse($e);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                "Failed to update product: " . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete product.
     */
    /**
     * Delete product.
     */
    public function destroy($id): JsonResponse
    {
        try {
            $product = Product::with([
                "images",
                "skin_tones",
                "hairs",
                "noses",
                "eyes",
                "mouths",
                "dresses",
                "crowns",
                "base_cards",
                "beards",
                "trading_fronts",
                "trading_backs",
            ])->findOrFail($id);

            DB::beginTransaction();

            // Delete main product image
            if ($product->image) {
                Storage::disk("public")->delete($product->image);
            }

            // Delete gallery images
            foreach ($product->images as $image) {
                Storage::disk("public")->delete($image->image);
            }

            // Delete customizable images
            if ($product->type === "customizable") {
                $relations = [
                    "skin_tones",
                    "hairs",
                    "noses",
                    "eyes",
                    "mouths",
                    "dresses",
                    "crowns",
                    "base_cards",
                    "beards",
                    "trading_fronts",
                    "trading_backs",
                ];

                foreach ($relations as $relation) {
                    foreach ($product->{$relation} as $item) {
                        if ($item->image) {
                            Storage::disk("public")->delete($item->image);
                        }
                    }
                    // Delete all customization records
                    $product->{$relation}()->delete();
                }
            }

            // Delete the product
            $product->delete();

            DB::commit();

            return $this->successResponse("Product deleted successfully");
        } catch (Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                "Failed to delete product: " . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Validation rules.
     */
    private function validateProductData(
        Request $request,
        $productId = null
    ): array {
        $rules = [
            "name" => "required|string|max:255",
            "slug" =>
                "nullable|string|unique:products,slug" .
                ($productId ? "," . $productId : ""),
           "type" => "required|string|in:simple,customizable,trading,Simple,Customizable,Trading",
            "price" => "required|numeric|min:0",
            "status" => "required|boolean",
            "offer_price" => "nullable|numeric|min:0|lt:price",
            "category_id" => "required|exists:categories,id",
            "short_description" => "nullable|string",
            "description" => "nullable|string",
            "image" => "nullable|string",
            "images" => "nullable|array",
            "images.*" => "required|string",
        ];

        if ($request->type === "Customizable" || $request->type === "Trading") {
            $customFields = [
                "base_cards",
                "skin_tones",
                "hairs",
                "noses",
                "eyes",
                "mouths",
                "dresses",
                "crowns",
                "beards",
                "trading_fronts",
                "trading_backs",
            ];

            foreach ($customFields as $field) {
                $rules[$field] = "sometimes|array";
                $rules[$field . ".*.name"] = "sometimes|string|max:255";
                $rules[$field . ".*.images"] = "sometimes|array";
                $rules[$field . ".*.images.*"] = "sometimes|string";
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
            "name" => $validated["name"],
            "slug" => $validated["slug"]
                ? $validated["slug"] . "-" . rand(1000, 9999)
                : Str::slug($validated["name"]) . "-" . rand(1000, 9999),
            "type" => $validated["type"],
            "price" => $validated["price"],
            "status" => $validated["status"],
            "category_id" => $validated["category_id"],
            "short_description" => $validated["short_description"] ?? null,
            "description" => $validated["description"] ?? null,
            "offer_price" => $validated["offer_price"] ?? null,
        ];
    }

    /**
     * Save gallery images.
     */
    private function saveGalleryImages(Product $product, array $images): void
    {
        foreach ($images as $imageBase64) {
            if (!empty($imageBase64)) {
                $imagePath = $this->saveBase64Image(
                    $imageBase64,
                    "products/gallery"
                );
                ProductHasImage::create([
                    "product_id" => $product->id,
                    "image" => $imagePath,
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
    private function handleCustomizations(
        Product $product,
        Request $request,
        bool $isUpdate = false
    ): void {
        $relations = [
            "skin_tones",
            "hairs",
            "noses",
            "eyes",
            "mouths",
            "dresses",
            "crowns",
            "base_cards",
            "beards",
            "trading_fronts",
            "trading_backs",
        ];

        foreach ($relations as $relation) {
            if ($request->has($relation) && is_array($request->$relation)) {
                // Update mode → old items & images delete
                if ($isUpdate) {
                    foreach ($product->{$relation} as $item) {
                        foreach ($item->images as $image) {
                            Storage::disk("public")->delete($image->image);
                        }
                    }
                    $product->{$relation}()->delete();
                }

                // Every $itemData directly being string (base 64 image)
                foreach ($request->$relation as $index => $imageBase64) {
                    $path = $this->saveBase64Image(
                        $imageBase64,
                        "products/customizations/{$relation}"
                    );

                    // New record to relation
                    $item = $product->{$relation}()->create([
                        "name" => ucfirst($relation) . " " . ($index + 1), // auto name generate
                        "product_id" => $product->id,
                        "image" => $path,
                    ]);

                    // If needed more images for customization then add here $item->images()->create()
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
            "skin_tones",
            "hairs",
            "noses",
            "eyes",
            "mouths",
            "dresses",
            "crowns",
            "base_cards",
            "beards",
            "trading_fronts",
            "trading_backs",
        ];

        foreach ($relations as $relation) {
            foreach ($product->{$relation} as $item) {
                foreach ($item->images as $image) {
                    Storage::disk("public")->delete($image->image);
                }
            }
        }
    }

    /**
     * Save base64 image and return path.
     */
    private function saveBase64Image(
        string $base64Image,
        string $folder
    ): string {
        if (preg_match("/^data:image\/(\w+);base64,/", $base64Image, $type)) {
            $imageData = substr($base64Image, strpos($base64Image, ",") + 1);
            $extension = strtolower($type[1]);
        } else {
            $imageData = $base64Image;
            $extension = "png";
        }

        $imageData = str_replace(" ", "+", $imageData);
        $decodedImage = base64_decode($imageData);

        if ($decodedImage === false) {
            throw new Exception("Failed to decode base64 image");
        }

        if (!in_array($extension, ["jpg", "jpeg", "png", "gif", "webp"])) {
            $extension = "png";
        }

        $fileName = time() . "_" . uniqid() . "." . $extension;
        $filePath = $folder . "/" . $fileName;

        if (!Storage::disk("public")->put($filePath, $decodedImage)) {
            throw new Exception("Failed to save image to storage");
        }

        return $filePath;
    }

    /**
     * Format single product for API response.
     */
    private function formatSingleProduct($product): array
    {
        $data = [
            "id" => $product->id,
            "name" => $product->name,
            "slug" => $product->slug,
            "type" => $product->type,
            "price" => $product->price,
            "offer_price" => $product->offer_price,
            "final_price" => $product->offer_price ?? $product->price,
            "discount_percentage" => $product->offer_price
                ? round(
                    (($product->price - $product->offer_price) /
                        $product->price) *
                        100,
                    2
                )
                : 0,
            "status" => $product->status,
            "short_description" => $product->short_description,
            "description" => $product->description,
            "created_at" => $product->created_at->format("Y-m-d H:i:s"),
            "updated_at" => $product->updated_at->format("Y-m-d H:i:s"),
        ];

        if ($product->image) {
            $data["image"] = asset("storage/" . $product->image);
        }

        if ($product->relationLoaded("images")) {
            $data["gallery_images"] = $product->images
                ->map(
                    fn($img) => [
                        "id" => $img->id,
                        "url" => asset("storage/" . $img->image),
                        "alt" => $product->name,
                    ]
                )
                ->toArray();
        }

        if ($product->relationLoaded("category") && $product->category) {
            $data["category"] = [
                "id" => $product->category->id,
                "name" => $product->category->name,
                "slug" => $product->category->slug ?? null,
            ];
        }

        if ($product->type === "customizable") {
            $customizations = [];
            $relations = [
                "skin_tones",
                "hairs",
                "noses",
                "eyes",
                "mouths",
                "dresses",
                "crowns",
                "base_cards",
                "beards",
                "trading_fronts",
                "trading_backs",
            ];

            foreach ($relations as $relation) {
                if ($product->relationLoaded($relation)) {
                    $customizations[$relation] = $product->{$relation}
                        ->map(function ($item) {
                            $itemData = [
                                "id" => $item->id,
                                "name" => $item->name,
                            ];
                            if ($item->relationLoaded("images")) {
                                $itemData["images"] = $item->images
                                    ->map(
                                        fn($img) => [
                                            "id" => $img->id,
                                            "url" => asset(
                                                "storage/" . $img->image
                                            ),
                                        ]
                                    )
                                    ->toArray();
                            }
                            return $itemData;
                        })
                        ->toArray();
                }
            }

            if (!empty($customizations)) {
                $data["customizations"] = $customizations;
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
            "data" => $products
                ->getCollection()
                ->map(fn($p) => $this->formatSingleProduct($p)),
            "pagination" => [
                "current_page" => $products->currentPage(),
                "last_page" => $products->lastPage(),
                "per_page" => $products->perPage(),
                "total" => $products->total(),
                "from" => $products->firstItem(),
                "to" => $products->lastItem(),
                "has_more_pages" => $products->hasMorePages(),
            ],
        ];
    }

    /**
     * JSON success response.
     */
    private function successResponse(
        string $message,
        $data = null,
        int $status = 200
    ): JsonResponse {
        return response()->json(
            [
                "success" => true,
                "status" => $status,
                "message" => $message,
                "data" => $data,
            ],
            $status
        );
    }

    /**
     * JSON error response.
     */
    private function errorResponse(
        string $message,
        int $status = 400
    ): JsonResponse {
        return response()->json(
            ["success" => false, "status" => $status, "message" => $message],
            $status
        );
    }

    /**
     * Validation error response.
     */
    private function validationErrorResponse(
        ValidationException $e
    ): JsonResponse {
        return response()->json(
            [
                "success" => false,
                "status" => 422,
                "message" => "Validation failed",
                "errors" => $e->errors(),
            ],
            422
        );
    }

    /**
     * Unauthorized response.
     */
    private function unauthorizedResponse(): JsonResponse
    {
        return $this->errorResponse("Unauthorized access", 401);
    }
}
