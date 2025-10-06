<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Class OrderController
 *
 * Handles order creation, listing, and details.
 * Includes payment trace handling on order creation.
 *
 * @package App\Http\Controllers
 */
class OrderController extends Controller
{
    /**
     * Fetch all orders for the authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = Auth::user();

        $orders = Order::with(['orderItems.product', 'orderHasPaids'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Orders fetched successfully',
            'data'    => ['orders' => $orders],
        ]);
    }

    /**
     * Store a new order with items and payment trace.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    // public function store(Request $request)
    // {
    //     $user = Auth::user();

    //     // Validate request
    //     $validator = Validator::make($request->all(), [
    //         // Order validation
    //         'name'              => 'required|string|max:255',
    //         'email'             => 'required|email',
    //         'phone'             => 'required|string|max:50',
    //         'address'           => 'required|string|max:500',
    //         'total'             => 'required|numeric|min:0',
    //         'is_customized'     => 'boolean',
    //         'customized_file'   => 'nullable|string',
    //         'status'            => 'nullable|string|in:pending,completed,cancelled',

    //         // Order items validation
    //         'order_items'                   => 'required|array|min:1',
    //         'order_items.*.product_id'      => 'required|exists:products,id',
    //         'order_items.*.quantity'        => 'required|integer|min:1',
    //         'order_items.*.price'           => 'nullable|numeric|min:0',

    //         // Payment validation
    //         'payment_method'    => 'required|string|in:cash,cod,card,stripe,bkash',
    //         'payment_status'    => 'nullable|string|in:pending,completed,failed',
    //         'transaction_id'    => 'nullable|string|max:100',
    //         'notes'             => 'nullable|string',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'status'  => 422,
    //             'message' => 'Validation failed',
    //             'errors'  => $validator->errors(),
    //         ]);
    //     }

    //     // Use transaction to avoid partial save
    //     DB::beginTransaction();

    //     try {
    //         // Create order
    //         $order = Order::create([
    //             'user_id'         => $user->id,
    //             'name'            => $request->name,
    //             'email'           => $request->email,
    //             'phone'           => $request->phone,
    //             'address'         => $request->address,
    //             'total'           => $request->total,
    //             'status'          => $request->status ?? 'pending',
    //             'is_customized'   => $request->is_customized ?? false,
    //             'customized_file' => $request->customized_file ?? null,
    //         ]);

    //         // Attach order items
    //         foreach ($request->order_items as $item) {
    //             $order->orderItems()->create([
    //                 'product_id' => $item['product_id'],
    //                 'quantity'   => $item['quantity'],
    //                 'price'      => $item['price'] ?? 0,
    //             ]);
    //         }

    //         // Payment trace
    //         $order->orderHasPaids()->create([
    //             'amount'         => $order->total,
    //             'method'         => $request->payment_method,
    //             'status'         => $request->payment_status,
    //             'transaction_id' => $request->transaction_id ?? null,
    //             'notes'          => $request->notes ?? null,
    //         ]);

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'status'  => 201,
    //             'message' => 'Order created successfully',
    //             'data'    => [
    //                 'order' => $order->load(['orderItems.product', 'orderHasPaids']),
    //             ],
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'success' => false,
    //             'status'  => 500,
    //             'message' => 'Failed to create order',
    //             'error'   => $e->getMessage(),
    //         ]);
    //     }
    // }

    public function store(Request $request)
{
    $user = Auth::user();

    // Validate request
    $validator = Validator::make($request->all(), [
        // Order validation
        'name'              => 'required|string|max:255',
        'email'             => 'required|email',
        'phone'             => 'required|string|max:50',
        'address'           => 'required|string|max:500',
        'total'             => 'required|numeric|min:0',
        'is_customized'     => 'boolean',
        'customized_file'   => 'nullable|string',
        'status'            => 'nullable|string|in:pending,completed,cancelled',

        // Order items validation
        'order_items'                   => 'required|array|min:1',
        'order_items.*.product_id'      => 'required|exists:products,id',
        'order_items.*.quantity'        => 'required|integer|min:1',
        'order_items.*.price'           => 'nullable|numeric|min:0',
        'order_items.*.FinalPDF'        => 'nullable|string', // Base64 or path
        'order_items.*.FinalProduct'    => 'nullable|array', // images array

        // Payment validation
        'payment_method'    => 'required|string|in:cash,cod,card,stripe,bkash',
        'payment_status'    => 'nullable|string|in:pending,completed,failed',
        'transaction_id'    => 'nullable|string|max:100',
        'notes'             => 'nullable|string',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'status'  => 422,
            'message' => 'Validation failed',
            'errors'  => $validator->errors(),
        ]);
    }

    DB::beginTransaction();

    try {
        // Create main order
        $order = Order::create([
            'user_id'         => $user->id,
            'name'            => $request->name,
            'email'           => $request->email,
            'phone'           => $request->phone,
            'address'         => $request->address,
            'total'           => $request->total,
            'status'          => $request->status ?? 'pending',
            'is_customized'   => $request->is_customized ?? false,
            'customized_file' => $request->customized_file ?? null,
        ]);

        // Attach order items
        foreach ($request->order_items as $item) {
            $orderItem = $order->orderItems()->create([
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'price'      => $item['price'] ?? 0,
            ]);

            // If customized -> save FinalPDF in order table
            if (!empty($item['FinalPDF'])) {
                $order->update([
                    'is_customized'   => true,
                    'customized_file' => $item['FinalPDF'], // Base64 or uploaded path
                ]);
            }

            // If FinalProduct images exist -> save in order_items
            if (!empty($item['FinalProduct'])) {
                $orderItem->update([
                    'customization_images' => json_encode($item['FinalProduct']),
                ]);
            }
        }

        // Payment trace
        $order->orderHasPaids()->create([
            'amount'         => $order->total,
            'method'         => $request->payment_method,
            'status'         => $request->payment_status,
            'transaction_id' => $request->transaction_id ?? null,
            'notes'          => $request->notes ?? null,
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => 'Order created successfully',
            'data'    => [
                'order' => $order->load(['orderItems.product', 'orderHasPaids']),
            ],
        ]);
    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'status'  => 500,
            'message' => 'Failed to create order',
            'error'   => $e->getMessage(),
        ]);
    }
}


    /**
     * Show a single order with its items and payments.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();

        $order = Order::with(['orderItems.product', 'orderHasPaids'])
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Order fetched successfully',
            'data'    => ['order' => $order],
        ]);
    }
}
