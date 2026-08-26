<?php

declare(strict_types=1);

namespace Switch\Foundation\Sentinel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SecurityScanner
{
    /**
     * Run all static security configuration audits.
     *
     * @param array<string, mixed> $config
     * @return array<int, DiagnosticResult>
     */
    public function auditConfig(array $config = []): array
    {
        $results = [];

        $env = strtolower((string) ($config['app']['env'] ?? (getenv('APP_ENV') ?: 'development')));
        $debug = (bool) ($config['app']['debug'] ?? (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1'));
        $appKey = (string) ($config['app']['key'] ?? (getenv('APP_KEY') ?: ''));

        // 1. Check APP_DEBUG in Production
        if (($env === 'production' || $env === 'prod') && $debug) {
            $results[] = DiagnosticResult::critical(
                'Security',
                'APP_DEBUG Enabled in Production',
                'Detailed exception stack traces and sensitive server environment variables may be exposed to end users and attackers.',
                'Set APP_DEBUG=false in your production .env file.'
            );
        } else {
            $results[] = DiagnosticResult::pass('Security', 'Debug Mode Configuration', 'Debug mode is safely configured for current environment.');
        }

        // 2. Check APP_KEY / Encryption Secret
        if ($appKey === '' || $appKey === 'base64:your-32-character-secret-key' || strlen($appKey) < 16) {
            $results[] = DiagnosticResult::critical(
                'Security',
                'Missing or Weak APP_KEY',
                'The application encryption key is missing or uses a default placeholder. This compromises session encryption, signed URLs, and token hashing.',
                'Generate a secure key using `php switch key:generate`.'
            );
        } else {
            $results[] = DiagnosticResult::pass('Security', 'Application Encryption Key', 'A valid secret encryption key is configured.');
        }

        // 3. Check Session Security Config
        $sessionSecure = (bool) ($config['session']['secure'] ?? false);
        $sessionHttpOnly = (bool) ($config['session']['http_only'] ?? true);
        $sessionSameSite = strtolower((string) ($config['session']['same_site'] ?? 'lax'));

        if (!$sessionHttpOnly) {
            $results[] = DiagnosticResult::warning(
                'Security',
                'Session Cookies HttpOnly Flag Disabled',
                'Session cookies accessible via JavaScript are vulnerable to Cross-Site Scripting (XSS) token theft.',
                'Enable `http_only => true` in config/session.php.'
            );
        }

        if ($sessionSameSite === 'none' || $sessionSameSite === '') {
            $results[] = DiagnosticResult::warning(
                'Security',
                'Weak Session Cookie SameSite Policy',
                'Cookies with SameSite=None are sent on cross-site requests, increasing vulnerability to CSRF attacks.',
                'Set `same_site => "lax"` or `"strict"` in config/session.php.'
            );
        }

        return $results;
    }

    /**
     * Inspect a live runtime HTTP request & response cycle for security headers and threats.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface|null $response
     * @return array<int, DiagnosticResult>
     */
    public function inspectRuntime(ServerRequestInterface $request, ?ResponseInterface $response = null): array
    {
        $results = [];

        // 1. Check HTTP Security Headers on Response
        if ($response !== null) {
            $headers = array_change_key_case($response->getHeaders(), CASE_LOWER);

            // CSP
            if (!isset($headers['content-security-policy'])) {
                $results[] = DiagnosticResult::warning(
                    'Security Headers',
                    'Missing Content-Security-Policy (CSP)',
                    'Without a CSP header, the browser cannot mitigate Cross-Site Scripting (XSS) or data injection attacks.',
                    'Add a Content-Security-Policy header via security middleware.'
                );
            } else {
                $results[] = DiagnosticResult::pass('Security Headers', 'Content-Security-Policy Active', 'CSP header is present.');
            }

            // X-Content-Type-Options
            if (!isset($headers['x-content-type-options']) || strtolower($response->getHeaderLine('x-content-type-options')) !== 'nosniff') {
                $results[] = DiagnosticResult::warning(
                    'Security Headers',
                    'Missing X-Content-Type-Options: nosniff',
                    'Prevents the browser from MIME-sniffing a response away from the declared content-type.',
                    'Set `X-Content-Type-Options: nosniff` in response headers.'
                );
            } else {
                $results[] = DiagnosticResult::pass('Security Headers', 'MIME Sniffing Protection', 'nosniff header is active.');
            }

            // X-Frame-Options
            if (!isset($headers['x-frame-options']) && !isset($headers['content-security-policy'])) {
                $results[] = DiagnosticResult::warning(
                    'Security Headers',
                    'Missing X-Frame-Options (Clickjacking Risk)',
                    'Without X-Frame-Options or CSP frame-ancestors, attackers can frame this site inside malicious iframes.',
                    'Set `X-Frame-Options: SAMEORIGIN` or `DENY`.'
                );
            }

            // HSTS on HTTPS
            $isHttps = $request->getUri()->getScheme() === 'https'
                || strtolower($request->getHeaderLine('X-Forwarded-Proto')) === 'https';

            if ($isHttps && !isset($headers['strict-transport-security'])) {
                $results[] = DiagnosticResult::warning(
                    'Security Headers',
                    'Missing Strict-Transport-Security (HSTS)',
                    'HTTPS connections should enforce HSTS to protect users from SSL-stripping man-in-the-middle attacks.',
                    'Set `Strict-Transport-Security: max-age=31536000; includeSubDomains`.'
                );
            }
        }

        // 2. Check Open Redirect Risk in Query Parameters
        $queryParams = $request->getQueryParams();
        if (empty($queryParams) && $request->getUri()->getQuery() !== '') {
            parse_str($request->getUri()->getQuery(), $queryParams);
        }
        $redirectKeys = ['redirect', 'url', 'next', 'return', 'dest', 'destination', 'return_to'];
        $host = $request->getUri()->getHost();

        foreach ($redirectKeys as $key) {
            if (isset($queryParams[$key]) && is_string($queryParams[$key])) {
                $target = trim($queryParams[$key]);
                if (preg_match('#^https?://#i', $target)) {
                    $targetHost = parse_url($target, PHP_URL_HOST);
                    if ($targetHost && $host && !str_ends_with(strtolower($targetHost), strtolower($host))) {
                        $results[] = DiagnosticResult::warning(
                            'Security Threat',
                            'Potential Open Redirect Parameter: ?' . $key,
                            'The query parameter `' . $key . '` points to an external untrusted domain (' . $targetHost . '), which can be exploited for phishing attacks.',
                            'Validate that redirect URLs are relative paths or match a whitelist of trusted domains.'
                        );
                    }
                }
            }
        }

        return $results;
    }
}
