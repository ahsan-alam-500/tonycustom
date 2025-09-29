<?php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    // Fetch orders for the authenticated user
    public function index()
    {
        $user = Auth::user();

        $orders = Order::with('orderItems.product')->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return response()
            ->json(['success' => true, 'status' => 200, 'message' => 'Orders fetched successfully', 'data' => ['orders' => $orders, ], ]);
    }

    // Store a new order
    public function store(Request $request)
    {
        $user = Auth::user();

        // Validate request
        $validator = Validator::make($request->all() , ['name' => 'required|string|max:255', 'email' => 'required|email', 'phone' => 'required|string|max:50', 'address' => 'required|string|max:500', 'total' => 'required|numeric|min:0', 'is_customized' => 'boolean', 'customized_file' => 'nullable|string', 'status' => 'nullable|string|in:pending,completed,cancelled', 'order_items' => 'required|array|min:1', 'order_items.*.product_id' => 'required|exists:products,id', 'order_items.*.quantity' => 'required|integer|min:1', ]);

        if ($validator->fails())
        {
            return response()
                ->json(['success' => false, 'status' => 422, 'message' => 'Validation failed', 'errors' => $validator->errors() , ]);
        }

        // Create order
        $order = Order::create(['user_id' => $user->id, 'name' => $request->name, 'email' => $request->email, 'phone' => $request->phone, 'address' => $request->address, 'total' => $request->total, 'status' => $request->status ? ? 'pending', 'is_customized' => $request->is_customized ? ? false, 'customized_file' => $request->customized_file ? ? null, ]);

        // Attach order items
        foreach ($request->order_items as $item)
        {
            $order->orderItems()
                ->create(['product_id' => $item['product_id'], 'quantity' => $item['quantity'], 'price' => $item['price'] ? ? 0, ]);
        }

        return response()->json(['success' => true, 'status' => 201, 'message' => 'Order created successfully', 'data' => ['order' => $order->load('orderItems.product') , ], ]);
    }

    // Optional: show a single order
    public function show($id)
    {
        $user = Auth::user();

        $order = Order::with('orderItems.product')->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json(['success' => true, 'status' => 200, 'message' => 'Order fetched successfully', 'data' => ['order' => $order, ], ]);
    }
}

