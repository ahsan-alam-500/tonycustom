<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderHasPaid;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Class PaymentController
 *
 * Handles all payment-related operations for orders,
 * such as storing, viewing, and updating payment records.
 *
 * @package App\Http\Controllers
 */
class PaymentController extends Controller
{
    /**
     * Display a listing of payments.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $payments = OrderHasPaid::with('order')->latest()->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Payments fetched successfully.',
            'data' => [
                'payments' => $payments
            ],
        ]);
    }

    /**
     * Store a newly created payment in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id'       => 'required|exists:orders,id',
            'amount'         => 'required|numeric|min:0',
            'method'         => 'required|string|max:50',
            'status'         => 'required|string|max:20',
            'transaction_id' => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
        ]);

        $payment = OrderHasPaid::create($validated);

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => 'Payment recorded successfully.',
            'data'    => [
                'payment' => $payment
            ],
        ], 201);
    }

    /**
     * Display the specified payment.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $payment = OrderHasPaid::with('order')->findOrFail($id);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Payment fetched successfully.',
            'data'    => [
                'payment' => $payment
            ],
        ]);
    }

    /**
     * Update the specified payment.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $payment = OrderHasPaid::findOrFail($id);

        $validated = $request->validate([
            'amount'         => 'sometimes|numeric|min:0',
            'method'         => 'sometimes|string|max:50',
            'status'         => 'sometimes|string|max:20',
            'transaction_id' => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
        ]);

        $payment->update($validated);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Payment updated successfully.',
            'data'    => [
                'payment' => $payment
            ],
        ]);
    }

    /**
     * Remove the specified payment.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $payment = OrderHasPaid::findOrFail($id);
        $payment->delete();

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Payment deleted successfully.',
        ]);
    }
}
