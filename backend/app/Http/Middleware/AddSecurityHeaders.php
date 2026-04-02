<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = config('security.headers');

        $response->headers->set('Referrer-Policy', $headers['referrer_policy']);
        $response->headers->set('X-Content-Type-Options', $headers['content_type_options']);
        $response->headers->set('X-Frame-Options', $headers['frame_options']);
        $response->headers->set('Permissions-Policy', $headers['permissions_policy']);
        $response->headers->set('Cross-Origin-Opener-Policy', $headers['cross_origin_opener_policy']);
        $response->headers->set('Cross-Origin-Resource-Policy', $headers['cross_origin_resource_policy']);

        if ($headers['content_security_policy']) {
            $response->headers->set('Content-Security-Policy', $headers['content_security_policy']);
        }

        if ($request->isSecure() && $headers['hsts']['enabled']) {
            $hsts = sprintf('max-age=%d', $headers['hsts']['max_age']);

            if ($headers['hsts']['include_subdomains']) {
                $hsts .= '; includeSubDomains';
            }

            if ($headers['hsts']['preload']) {
                $hsts .= '; preload';
            }

            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        return $response;
    }
}
