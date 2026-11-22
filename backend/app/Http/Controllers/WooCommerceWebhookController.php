<?php

namespace App\Http\Controllers;

use App\Integrations\WooCommerce\OrderMapper;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WooCommerceWebhookController extends Controller
{
    public function __invoke(Request $request, PurchaseService $service, OrderMapper $mapper)
    {
        // Na ativação, o WooCommerce envia um teste sem assinatura que não deve gravar compras.
        if ($request->isMethod('POST') && str_starts_with((string) $request->header('Content-Type'), 'application/x-www-form-urlencoded')
            && preg_match('/^webhook_id=[0-9]+$/D', $request->getContent())) {
            return response('OK', 200);
        }
        $secret = config('services.woocommerce.webhook_secret');
        abort_if(! is_string($secret) || $secret === '', 503, 'Webhook secret is not configured.');
        $signature = $request->header('X-WC-Webhook-Signature', '');
        abort_unless(hash_equals(base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true)), $signature), 401, 'Invalid webhook signature.');
        abort_unless(in_array($request->header('X-WC-Webhook-Topic'), ['order.created', 'order.updated'], true), 400, 'Unsupported webhook topic.');
        $order = $request->json()->all();
        $validated = Validator::make($order, [
            'id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string'],
        ])->validate();
        if (! in_array($validated['status'], ['processing', 'completed'], true)) {
            return response()->json(['ignored' => true, 'reason' => 'Order is not processing or completed.']);
        }
        [$purchase, $created] = $service->store($mapper->map($order)->toPurchase());

        return response()->json(['created' => $created, 'reference' => $purchase->reference], $created ? 201 : 200);
    }
}
