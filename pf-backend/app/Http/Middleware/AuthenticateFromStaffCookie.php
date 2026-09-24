<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateFromStaffCookie
{
    /**
     * If Authorization header is missing, attempt to authenticate using the HttpOnly staff session cookie.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->bearerToken() && $request->hasCookie('lapaqu_staff_session')) {
            $cookieToken = $request->cookie('lapaqu_staff_session');
            if (!empty($cookieToken)) {
                $request->headers->set('Authorization', 'Bearer ' . $cookieToken);
            }
        }

        return $next($request);
    }
}
