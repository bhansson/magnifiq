<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class RegisterResponse implements RegisterResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        // Check for pending Shopify installation
        if ($pendingInstall = $request->session()->pull('shopify_pending_install')) {
            return redirect()->route('store.oauth.redirect', [
                'platform' => 'shopify',
                'store' => $pendingInstall['shop'],
            ]);
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}
