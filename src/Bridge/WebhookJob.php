<?php

declare(strict_types=1);

namespace Switch\Foundation\Bridge;

use Switch\Foundation\Queue\Job;

class WebhookJob extends Job
{
    /** @var callable|null */
    public static $httpClient = null;

    public function __construct(
        public readonly string $url,
        public readonly array $payload,
        public readonly string $secret = '',
        public readonly array $headers = [],
        public readonly int $maxAttempts = 5,
        public readonly int $backoffSeconds = 10,
    ) {
    }

    public function handle(): void
    {
        $body = json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = time();

        $headers = $this->headers;
        $headers['Content-Type'] = 'application/json';
        $headers['User-Agent'] = 'Switch-Bridge/1.0';

        if ($this->secret !== '') {
            $sig = hash_hmac('sha256', "{$timestamp}.{$body}", $this->secret);
            $headers['X-Switch-Signature'] = "t={$timestamp},v1={$sig}";
        }

        $deliveryRecord = [
            'url' => $this->url,
            'payload' => $this->payload,
            'headers' => $headers,
            'timestamp' => $timestamp,
            'attempt' => $this->attempts + 1,
            'success' => true,
        ];

        // Custom or mock HTTP client
        if (self::$httpClient !== null) {
            $result = (self::$httpClient)($this->url, $body, $headers);
            $deliveryRecord['success'] = $result !== false;
            WebhookDispatcher::$deliveryHistory[] = $deliveryRecord;
            return;
        }

        // Native cURL execution
        if (function_exists('curl_init')) {
            $ch = curl_init($this->url);
            $curlHeaders = [];
            foreach ($headers as $k => $v) {
                $curlHeaders[] = "{$k}: {$v}";
            }

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $curlHeaders,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);

            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $success = ($status >= 200 && $status < 300);
            $deliveryRecord['success'] = $success;
            $deliveryRecord['status'] = $status;
            $deliveryRecord['error'] = $error;
        } else {
            // Stream context fallback
            $headerLines = [];
            foreach ($headers as $k => $v) {
                $headerLines[] = "{$k}: {$v}";
            }

            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headerLines),
                    'content' => $body,
                    'timeout' => 15,
                    'ignore_errors' => true,
                ],
            ]);

            $response = @file_get_contents($this->url, false, $ctx);
            $deliveryRecord['success'] = ($response !== false);
        }

        WebhookDispatcher::$deliveryHistory[] = $deliveryRecord;
    }
}
