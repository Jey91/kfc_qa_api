<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;

class SecurityHeadersMiddleware
{
    /**
     * Handle the middleware
     *
     * @param Request $request The request object
     * @param Response $response The response object
     * @param callable $next The next middleware handler
     * @return Response
     */
    public function handle(Request $request, Response $response, callable $next)
    {

        
        // First, pass the request to the next middleware/handler in the chain
        // This allows the request to be processed and a response to be generated
        $response = $next($request, $response);

        // Now that we have the response from the application,
        // we can add our security headers to it

        // Protect against XSS attacks
        $response->setHeader('X-XSS-Protection', '1; mode=block');

        // Prevent MIME type sniffing
        $response->setHeader('X-Content-Type-Options', 'nosniff');

        // Control iframe embedding (CLICKJACKING protection)
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');

        // Enforce HTTPS and protect against protocol downgrade attacks
        // Uncomment if your site uses HTTPS
        // $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // Control which features and APIs can be used in the browser
        $response->setHeader('Feature-Policy', "geolocation 'self'; microphone 'none'; camera 'none'");

        // Control which resources the browser is allowed to load
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // Modify as needed
            "style-src 'self' 'unsafe-inline'", // Modify as needed
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "frame-src 'self'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        $response->setHeader('Content-Security-Policy', implode('; ', $cspDirectives));

        // Referrer Policy - control how much referrer information should be included with requests
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (formerly Feature Policy) - control browser features
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');

        // Return the modified response
        return $response;
    }
}
