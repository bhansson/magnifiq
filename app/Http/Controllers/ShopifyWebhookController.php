<?php

namespace App\Http\Controllers;

use App\Models\StoreConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ShopifyWebhookController extends Controller
{
    /**
     * Handle the app/uninstalled webhook from Shopify.
     *
     * When a merchant uninstalls the app, Shopify sends this webhook
     * so we can clean up the connection and mark it as disconnected.
     */
    public function appUninstalled(Request $request): Response
    {
        if (! $this->verifyWebhook($request)) {
            Log::warning('Shopify webhook HMAC verification failed', [
                'topic' => $request->header('X-Shopify-Topic'),
                'shop' => $request->header('X-Shopify-Shop-Domain'),
            ]);

            return response('Unauthorized', 401);
        }

        $shopDomain = $request->header('X-Shopify-Shop-Domain');

        if (! $shopDomain) {
            Log::warning('Shopify webhook missing shop domain header');

            return response('Bad Request', 400);
        }

        $connection = StoreConnection::where('platform', StoreConnection::PLATFORM_SHOPIFY)
            ->where('store_identifier', $shopDomain)
            ->first();

        if ($connection) {
            $connection->markDisconnected();

            Log::info('Store connection marked as disconnected via webhook', [
                'connection_id' => $connection->id,
                'shop' => $shopDomain,
                'team_id' => $connection->team_id,
            ]);
        } else {
            Log::debug('Shopify app/uninstalled webhook received for unknown store', [
                'shop' => $shopDomain,
            ]);
        }

        return response('OK', 200);
    }

    /**
     * Verify the webhook HMAC signature.
     *
     * Shopify signs webhooks with the app's client secret.
     * The signature is sent in the X-Shopify-Hmac-SHA256 header.
     */
    protected function verifyWebhook(Request $request): bool
    {
        $hmacHeader = $request->header('X-Shopify-Hmac-SHA256');

        if (! $hmacHeader) {
            return false;
        }

        $secret = config('store-integrations.platforms.shopify.client_secret');

        if (! $secret) {
            Log::error('Shopify client secret not configured for webhook verification');

            return false;
        }

        $body = $request->getContent();
        $computedHmac = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return hash_equals($hmacHeader, $computedHmac);
    }
}
