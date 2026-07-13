<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCourierApproved
{
    /** Onaylanmamış kuryeyi kurye alanından süzer → onay-bekleme ekranına. */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isCourier() && ! $user->isApproved()) {
            return $request->expectsJson()
                ? abort(403, 'Hesabın henüz onaylanmadı.')
                : redirect()->route('courier.pending');
        }

        return $next($request);
    }
}
