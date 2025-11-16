<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class DetectPartnerContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $partnerSlug = $request->query('partner');
        $partnerTeam = null;

        if ($partnerSlug) {
            $partnerTeam = Team::query()
                ->where('type', 'partner')
                ->where('partner_slug', $partnerSlug)
                ->first();
        }

        View::share('partnerTeam', $partnerTeam);

        return $next($request);
    }
}
