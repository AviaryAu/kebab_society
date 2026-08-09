<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Only Society administrators may reach the admin.
 *
 * A signed-in but unauthorised visitor is shown a 404 rather than a 403, so the
 * existence of the admin is not confirmed to someone poking at URLs.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            throw new NotFoundHttpException;
        }

        return $next($request);
    }
}
