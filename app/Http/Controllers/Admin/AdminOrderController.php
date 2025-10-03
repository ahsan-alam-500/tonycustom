<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminOrderController extends Controller
{
    /**
     * Display a listing of the orders.
     */
    public function index(Request $request): JsonResponse
    {
        // Authorization using Gate/Policy (better approach)
        try {
            // Query optimization with pagination
            $orders = Order::with(['orderItems', 'user:id,name,email'])
                ->when($request->status, function ($query) use ($request) {
                    $query->where('status', $request->status);
                })
                ->latest('id')
                ->paginate(10);

            return $this->successResponse(
                'Orders retrieved successfully',
                OrderResource::collection($orders)
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve orders', 500);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(Order $order): JsonResponse
    {
        try {
            $order->load(['orderItems.product', 'user']);

            return $this->successResponse(
                'Order retrieved successfully',
                new OrderResource($order)
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Order not found', 404);
        }
    }
    /**
     * Update order status.
     */
public function update(Request $request, Order $order): JsonResponse
{
    $request->validate([
        'status' => 'required|in:pending,processing,shipped,delivered,cancelled'
    ]);

    try {
        $order->update([
            'status' => $request->status,
            'updated_by' => Auth::user()->id
        ]);

        return $this->successResponse(
            'Order status updated successfully',
            new OrderResource($order)
        );

    } catch (\Exception $e) {
        return $this->errorResponse('Failed to update order status', 500);
    }
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
}
