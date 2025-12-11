<?php

namespace App\Http\Controllers;

use App\Facades\Store;
use App\Jobs\SyncStoreProducts;
use App\Models\StoreConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StoreOAuthController extends Controller
{
    public function redirect(Request $request, string $platform): RedirectResponse
    {
        $request->validate([
            'store' => ['required', 'string', 'max:255'],
        ]);

        $team = $request->user()->currentTeam;
        $storeIdentifier = $request->input('store');

        $adapter = Store::forPlatform($platform);

        $state = Str::random(40);
        $request->session()->put('store_oauth_state', $state);
        $request->session()->put('store_oauth_platform', $platform);
        $request->session()->put('store_oauth_store', $storeIdentifier);
        $request->session()->put('store_oauth_team_id', $team->id);

        $redirectUri = route('store.oauth.callback', ['platform' => $platform]);
        $authUrl = $adapter->getAuthorizationUrl($storeIdentifier, $state, $redirectUri);

        return redirect()->away($authUrl);
    }

    public function callback(Request $request, string $platform): RedirectResponse
    {
        $sessionState = $request->session()->pull('store_oauth_state');
        $sessionPlatform = $request->session()->pull('store_oauth_platform');
        $storeIdentifier = $request->session()->pull('store_oauth_store');
        $teamId = $request->session()->pull('store_oauth_team_id');

        if (! $sessionState || $request->input('state') !== $sessionState) {
            Log::warning('Store OAuth state mismatch', [
                'platform' => $platform,
                'expected' => $sessionState,
                'received' => $request->input('state'),
            ]);

            return redirect()->route('catalog.index')
                ->with('error', 'Invalid OAuth state. Please try connecting again.');
        }

        if ($sessionPlatform !== $platform) {
            return redirect()->route('catalog.index')
                ->with('error', 'Platform mismatch. Please try connecting again.');
        }

        $adapter = Store::forPlatform($platform);

        if (! $adapter->verifyCallback($request->all())) {
            Log::warning('Store OAuth callback verification failed', [
                'platform' => $platform,
                'store' => $storeIdentifier,
            ]);

            return redirect()->route('catalog.index')
                ->with('error', 'Could not verify the callback. Please try again.');
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect()->route('catalog.index')
                ->with('error', 'No authorization code received.');
        }

        try {
            $redirectUri = route('store.oauth.callback', ['platform' => $platform]);
            $credentials = $adapter->exchangeCodeForToken($storeIdentifier, $code, $redirectUri);

            $connection = StoreConnection::updateOrCreate(
                [
                    'team_id' => $teamId,
                    'platform' => $platform,
                    'store_identifier' => $storeIdentifier,
                ],
                [
                    'name' => $storeIdentifier,
                    'access_token' => $credentials->accessToken,
                    'refresh_token' => $credentials->refreshToken,
                    'token_expires_at' => $credentials->expiresAt,
                    'scopes' => $credentials->scopes,
                    'status' => StoreConnection::STATUS_CONNECTED,
                    'last_error' => null,
                    'metadata' => $credentials->metadata,
                ]
            );

            $storeName = $adapter->getStoreName($connection);
            if ($storeName !== $connection->name) {
                $connection->update(['name' => $storeName]);
            }

            SyncStoreProducts::dispatch($connection);

            Log::info('Store connection established', [
                'connection_id' => $connection->id,
                'platform' => $platform,
                'store' => $storeIdentifier,
                'team_id' => $teamId,
            ]);

            return redirect()->route('catalog.index')
                ->with('success', "Connected to {$storeName}! Products are being synced.");

        } catch (\Exception $e) {
            Log::error('Store OAuth token exchange failed', [
                'platform' => $platform,
                'store' => $storeIdentifier,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('catalog.index')
                ->with('error', 'Failed to connect to the store. Please try again.');
        }
    }

    public function disconnect(Request $request, StoreConnection $connection): RedirectResponse
    {
        $team = $request->user()->currentTeam;

        if ($connection->team_id !== $team->id) {
            abort(403);
        }

        $storeName = $connection->name;
        $connection->delete();

        return redirect()->route('catalog.index')
            ->with('success', "Disconnected from {$storeName}.");
    }
}
