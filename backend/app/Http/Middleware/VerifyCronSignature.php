<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCronSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $sharedSecret = config('app.cron_shared_secret', env('CRON_SHARED_SECRET'));
        $signature = $request->header('X-Internal-Signature');

        if (! $sharedSecret || ! hash_equals($sharedSecret, (string) $signature)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid cron signature.');
        }

        return $next($request);
    }
}
