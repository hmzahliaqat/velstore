<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGateway;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class CheckoutController extends Controller
{
    public function index()
    {
        $paymentGateways = PaymentGateway::with('configs')
            ->where('is_active', 1)
            ->get();

        $paypal = $paymentGateways->firstWhere('code', 'paypal');
        $paypalClientId = $paypal
            ? $paypal->getConfigValue('client_id', 'sandbox')
            : null;

        $cart = Session::get('cart', []);
        $subtotal = 0;

        foreach ($cart as $key => $item) {
            $product = \App\Models\Product::with(['translations', 'thumbnail'])->find($item['product_id']);

            $variant = isset($item['variant_id'])
                ? ProductVariant::with('images')->find($item['variant_id'])
                : ProductVariant::where('product_id', $item['product_id'])->where('is_primary', true)->first();

            $subtotal += $item['price'] * $item['quantity'];
        }

        $shipping = null;
        $total = $subtotal + ($shipping ?? 0);

        return view('themes.xylo.checkout', compact('cart', 'subtotal', 'shipping', 'total', 'paymentGateways', 'paypalClientId'));
    }

    public function process(Request $request)
    {
        $gatewayCode = $request->input('gateway');
        $amount = 100;

        $paymentService = PaymentManager::make($gatewayCode, 'sandbox');

        $order = $paymentService->createOrder($amount, 'USD');

        return response()->json($order);
    }

    public function store(Request $request)
    {
        $request->validate([
            'address' => 'required|string|max:255',
            'payment_method' => 'required',
        ]);

        $cart = Session::get('cart', []);

        if (empty($cart)) {
            return redirect()->back()->with('error', 'Cart is empty!');
        }

        // Calculate total
        $total = collect($cart)->sum(function ($item) {
            return $item['price'] * $item['quantity'];
        });

        // Save Order
        $order = Order::create([
            'user_id' => Auth::id(),
            'address' => $request->address,
            'payment_method' => $request->payment_method,
            'total' => $total,
            'status' => 'pending',
        ]);

        // Save Order Items
        foreach ($cart as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'] ?? null,
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'attributes' => json_encode($item['attributes']),
            ]);
        }

        // Clear the session cart
        Session::forget('cart');

        return redirect()->route('thankyou')->with('success', 'Order placed successfully!');
    }
}
