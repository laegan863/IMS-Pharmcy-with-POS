<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends owners and pharmacists to the security page until they turn on
 * two-factor authentication. Apply it to app routes, not to settings routes.
 */
class EnsureTwoFactorIsEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->requiresTwoFactor() && $user->two_factor_confirmed_at === null) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Your role requires two-factor authentication. Turn it on to continue.'),
            ]);

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
