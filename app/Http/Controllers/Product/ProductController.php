<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of products with pagination.
     */
    public function index()
    {
        $products = Product::with(['category', 'images'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Products fetched successfully',
            'data' => $products,
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        if (!Auth::check() || Auth::user()->role !== 'Admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug',
            'type' => 'required|in:simple,customizable',
            'price' => 'required|numeric',
            'status' => 'required|boolean',
            'offer_price' => 'nullable|numeric',
            'category_id' => 'required|exists:categories,id',
            'short_description' => 'nullable|string',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'base_cards' => 'sometimes|array',
            'skin_tones' => 'sometimes|array',
            'hairs' => 'sometimes|array',
            'noses' => 'sometimes|array',
            'eyes' => 'sometimes|array',
            'mouths' => 'sometimes|array',
            'dresses' => 'sometimes|array',
            'crowns' => 'sometimes|array',
            'beards' => 'sometimes|array',
        ]);

        $data = $request->only([
            'name', 'slug', 'type',
            'price', 'status', 'offer_price', 'category_id',
            'short_description', 'description'
        ]);

        $data['slug'] = $request->slug ?? Str::slug($request->name);

        // Save main image if base64
        if ($request->filled('image')) {
            $data['image'] = $this->saveBase64Image($request->image, 'product-images');
        }

        $product = Product::create($data);

        // Handle customizable images
        if ($product->type === 'customizable') {
            $this->handleCustomizations($product, $request);
        }

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Product created successfully',
            'data' => $product->load('category', 'images')
        ]);
    }

    /**
     * Show product details.
     */
    public function show($id)
    {
        try {
            $product = Product::with(['category', 'images'])->findOrFail($id);

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

            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => $product
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Product not found'
            ]);
        }
    }

    /**
     * Update product.
     */
    public function update(Request $request, $id)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $product = Product::findOrFail($id);

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'slug' => 'nullable|string|unique:products,slug,' . $product->id,
                'type' => 'sometimes|in:simple,customizable',
                'price' => 'sometimes|numeric',
                'status' => 'sometimes|boolean',
                'offer_price' => 'nullable|numeric',
                'category_id' => 'sometimes|exists:categories,id',
                'short_description' => 'nullable|string',
                'description' => 'nullable|string',
                'image' => 'nullable|string', // base64 image
                'skin_tones' => 'sometimes|array',
                'hairs' => 'sometimes|array',
                'noses' => 'sometimes|array',
                'eyes' => 'sometimes|array',
                'mouths' => 'sometimes|array',
                'dresses' => 'sometimes|array',
                'crowns' => 'sometimes|array',
                'base_cards' => 'sometimes|array',
                'beards' => 'sometimes|array',
            ]);

            $data = $request->only([
                'name', 'slug', 'type',
                'price', 'status', 'offer_price', 'category_id',
                'short_description', 'description'
            ]);

            if ($request->filled('image')) {
                $data['image'] = $this->saveBase64Image($request->image, 'product-images');
            }

            if (!empty($data['name']) && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['name']);
            }

            $product->update($data);

            if ($product->type === 'customizable') {
                $this->handleCustomizations($product, $request, true);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product updated successfully',
                'data' => $product->load('category','images')
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Product not found'
            ]);
        }
    }

    /**
     * Delete product.
     */
    public function destroy($id)
    {
        if (!Auth::check() || Auth::user()->role !== 'admin') {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $product = Product::findOrFail($id);
            $product->delete();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Product not found'
            ]);
        }
    }

    /**
     * Handle customizable options.
     */
    private function handleCustomizations($product, Request $request, $isUpdate = false)
    {
        $relations = ['skin_tones','hairs','noses','eyes','mouths','dresses','crowns','base_cards','beards'];

        foreach ($relations as $relation) {
            if ($request->has($relation)) {
                if ($isUpdate) {
                    $product->{$relation}()->delete();
                }

                foreach ($request->$relation as $itemData) {
                    $item = $product->{$relation}()->create([
                        'name' => $itemData['name'] ?? null,
                    ]);

                    if (!empty($itemData['images'])) {
                        foreach ($itemData['images'] as $imageBase64) {
                            $path = $this->saveBase64Image($imageBase64, $relation.'-images');
                            $item->images()->create(['image' => $path]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Save base64 image to disk.
     */
    private function saveBase64Image($base64Image, $folder)
    {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $base64Image = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]);
            if (!in_array($type, ['jpg','jpeg','png','gif'])) {
                throw new \Exception('Invalid image type');
            }
            $base64Image = str_replace(' ', '+', $base64Image);
            $imageData = base64_decode($base64Image);
            if ($imageData === false) {
                throw new \Exception('base64_decode failed');
            }
            $fileName = uniqid() . '.' . $type;
            $filePath = $folder.'/'.$fileName;
            Storage::disk('public')->put($filePath, $imageData);
            return $filePath;
        }
        throw new \Exception('Invalid base64 image string');
    }
}
