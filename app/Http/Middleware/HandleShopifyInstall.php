<?php

namespace App\Http\Middleware;

use App\Facades\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HandleShopifyInstall
{
    /**
     * Handle an incoming request.
     *
     * Detects Shopify app installation requests (from Partner Dashboard "Install" button)
     * and either initiates OAuth immediately (if user is logged in) or stores the
     * pending installation for after login.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only process requests with Shopify installation parameters
        if (! $this->isShopifyInstallRequest($request)) {
            return $next($request);
        }

        $shop = $request->input('shop');

        // Verify HMAC signature to ensure request is from Shopify
        if (! $this->verifyHmac($request->all())) {
            Log::warning('Shopify install request HMAC verification failed', [
                'shop' => $shop,
            ]);

            return $next($request);
        }

        Log::info('Shopify installation request received', ['shop' => $shop]);

        // If user is authenticated and has a team, redirect to OAuth flow
        if ($request->user() && $request->user()->currentTeam) {
            return redirect()->route('store.oauth.redirect', [
                'platform' => 'shopify',
                'store' => $shop,
            ]);
        }

        // Store pending installation in session for after login
        $request->session()->put('shopify_pending_install', [
            'shop' => $shop,
            'host' => $request->input('host'),
            'timestamp' => $request->input('timestamp'),
        ]);

        // Continue to welcome page where user can login/register
        return $next($request);
    }

    /**
     * Check if this request is a Shopify installation request.
     *
     * We skip OAuth routes to avoid redirect loops - when Shopify redirects
     * back after authorization, the callback URL also has shop/hmac/timestamp.
     */
    protected function isShopifyInstallRequest(Request $request): bool
    {
        // Skip OAuth routes to avoid redirect loops
        if ($request->is('store/*/connect', 'store/*/callback')) {
            return false;
        }

        return $request->has('shop')
            && $request->has('hmac')
            && $request->has('timestamp')
            && str_ends_with($request->input('shop'), '.myshopify.com');
    }

    /**
     * Verify the HMAC signature from Shopify.
     */
    protected function verifyHmac(array $params): bool
    {
        try {
            $adapter = Store::forPlatform('shopify');

            return $adapter->verifyCallback($params);
        } catch (\Exception $e) {
            Log::error('Failed to verify Shopify HMAC', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
