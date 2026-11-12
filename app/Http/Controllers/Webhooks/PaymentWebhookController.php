<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request, string $gateway, PaymentManager $payments): Response
    {
        // Provider deliveries are small. Refuse anything abusive before the
        // signature check so a public endpoint cannot be used to exhaust memory.
        if (strlen($request->getContent()) > 524288) {
            return response('Payload too large', 413);
        }

        try {
            $handler = $payments->gatewayForWebhook($gateway);
        } catch (Throwable) {
            return response('Unknown gateway', 404);
        }

        if (! $handler->verifyWebhook($request)) {
            Log::warning('Rejected webhook with invalid signature', ['gateway' => $gateway]);

            return response('Invalid signature', 400);
        }

        try {
            $handler->handleWebhook($request);
        } catch (Throwable $exception) {
            Log::error('Webhook processing failed', ['gateway' => $gateway, 'error' => $exception->getMessage()]);

            return response('Webhook processing failed', 500);
        }

        return response('OK');
    }
}
