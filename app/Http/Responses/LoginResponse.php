<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class LoginResponse implements LoginResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        // Check for pending Shopify installation
        if ($pendingInstall = $request->session()->pull('shopify_pending_install')) {
            return redirect()->route('store.oauth.redirect', [
                'platform' => 'shopify',
                'store' => $pendingInstall['shop'],
            ]);
        }

        return redirect()->intended(Fortify::redirects('login'));
    }
}
