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
        $amount = 100; // you can replace this with cart total

        try {
            $paymentService = PaymentManager::make($gatewayCode, 'sandbox');

            $order = $paymentService->createOrder($amount, 'USD');

            return response()->json([
                'success' => true,
                'gateway' => $gatewayCode,
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Payment process failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PayPal success callback
     */
    public function paypalSuccess(Request $request)
    {
        $orderId = $request->query('token'); // PayPal returns ?token=ORDER_ID

        try {
            $paypal = PaymentManager::make('paypal', 'sandbox');
            $result = $paypal->captureOrder($orderId);

            if (($result['status'] ?? null) === 'COMPLETED') {

                // Extract PayPal payer info
                $payer = $result['payer'] ?? [];
                $purchaseUnit = $result['purchase_units'][0] ?? [];
                $amount = $purchaseUnit['payments']['captures'][0]['amount']['value'] ?? 0;

                // ✅ 1. Create Order
                $order = \App\Models\Order::create([
                    'customer_id' => auth()->check() ? auth()->id() : null,
                    'guest_email' => $payer['email_address'] ?? null,
                    'total_amount' => $amount,
                    'status' => 'completed',
                ]);

                $cart = session('cart', []); // you should already have cart in session
                foreach ($cart as $productId => $item) {
                    \App\Models\OrderDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $productId,
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);
                }

                $shippingData = session('checkout.shipping'); // store shipping info in session before redirect
                if ($shippingData) {
                    \App\Models\ShippingAddress::create([
                        'order_id' => $order->id,
                        'customer_id' => auth()->check() ? auth()->id() : null,
                        'name' => $shippingData['name'],
                        'phone' => $shippingData['phone'],
                        'address' => $shippingData['address'],
                        'city' => $shippingData['city'],
                        'postal_code' => $shippingData['postal_code'],
                        'country' => $shippingData['country'],
                    ]);
                }

                // ✅ 4. Clear cart session
                session()->forget(['cart', 'checkout.shipping']);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed & order stored successfully.',
                    'order_id' => $order->id,
                    'details' => $result,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment not completed.',
                'details' => $result,
            ]);
        } catch (\Exception $e) {
            \Log::error('PayPal success error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PayPal cancel callback
     */
    public function paypalCancel()
    {
        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled by user.',
        ]);
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
