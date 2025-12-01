<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Display a product by catalog slug and SKU.
     * Supports language switching via optional /{lang} path segment.
     */
    public function show(Request $request, string $catalogSlug, string $sku, ?string $lang = null): View|RedirectResponse
    {
        $team = Auth::user()->currentTeam;
        abort_if(! $team, 404);

        $catalog = ProductCatalog::where('team_id', $team->id)
            ->where('slug', $catalogSlug)
            ->firstOrFail();

        $product = $catalog->findProductBySku($sku, $lang);

        abort_if(! $product, 404);

        return view('products.show', [
            'product' => $product,
            'catalog' => $catalog,
            'currentLanguage' => $lang,
        ]);
    }

    /**
     * Legacy route: redirect old ID-based URLs to new semantic URLs.
     * Falls back to showing product directly if not in a catalog.
     */
    public function showLegacy(Product $product): View|RedirectResponse
    {
        $team = Auth::user()->currentTeam;
        abort_if(! $team || $product->team_id !== $team->id, 404);

        // If product is in a catalog and has a SKU, redirect to semantic URL
        if ($product->isInCatalog() && $product->sku) {
            $catalog = $product->feed->catalog;

            return redirect()->route('products.show', [
                'catalog' => $catalog->slug,
                'sku' => $product->sku,
                'lang' => $product->feed->language,
            ], 301);
        }

        // Products not in catalogs or without SKU: show directly (no redirect)
        return view('products.show', [
            'product' => $product,
            'catalog' => null,
            'currentLanguage' => null,
        ]);
    }
}
