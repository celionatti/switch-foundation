<?php

declare(strict_types=1);

namespace Switch\Foundation\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Foundation\Sentinel\DiagnosticResult;
use Switch\Foundation\Sentinel\ProductionDoctor;
use Switch\Foundation\Sentinel\QueryAnalyzer;
use Switch\Foundation\Sentinel\SecurityScanner;
use Switch\Foundation\Sentinel\Sentinel;
use Switch\Http\Response;
use Switch\Http\ServerRequest;
use Switch\Http\Uri;

class SentinelTest extends TestCase
{
    public function testSecurityScannerDetectsProductionDebugAndWeakKey(): void
    {
        $scanner = new SecurityScanner();

        $results = $scanner->auditConfig([
            'app' => [
                'env' => 'production',
                'debug' => true,
                'key' => '',
            ],
            'session' => [
                'http_only' => false,
                'same_site' => 'none',
            ],
        ]);

        $criticals = array_filter($results, fn($r) => $r->isCritical());
        $warnings = array_filter($results, fn($r) => $r->isWarning());

        $this->assertNotEmpty($criticals);
        $this->assertNotEmpty($warnings);

        $titles = array_map(fn($r) => $r->title, $results);
        $this->assertContains('APP_DEBUG Enabled in Production', $titles);
        $this->assertContains('Missing or Weak APP_KEY', $titles);
        $this->assertContains('Session Cookies HttpOnly Flag Disabled', $titles);
    }

    public function testSecurityScannerDetectsOpenRedirectAndMissingHeaders(): void
    {
        $scanner = new SecurityScanner();

        $request = new ServerRequest(
            method: 'GET',
            uri: new Uri('https://my-app.com/login?redirect=https://evil-attacker.com/phish')
        );

        $response = new Response(200, [
            'Content-Type' => 'text/html',
        ]);

        $results = $scanner->inspectRuntime($request, $response);
        $titles = array_map(fn($r) => $r->title, $results);

        $this->assertContains('Potential Open Redirect Parameter: ?redirect', $titles);
        $this->assertContains('Missing Content-Security-Policy (CSP)', $titles);
        $this->assertContains('Missing X-Content-Type-Options: nosniff', $titles);
    }

    public function testQueryAnalyzerDetectsNPlusOneAndSqlInjectionRisks(): void
    {
        $analyzer = new QueryAnalyzer();

        $queries = [
            ['sql' => 'SELECT * FROM users WHERE id = 1', 'time_ms' => 2.5],
            ['sql' => 'SELECT * FROM users WHERE id = 2', 'time_ms' => 1.8],
            ['sql' => 'SELECT * FROM users WHERE id = 3', 'time_ms' => 2.1],
            ['sql' => "SELECT * FROM posts WHERE title = 'test' AND author = 'admin'", 'time_ms' => 65.0], // slow + unparameterized
        ];

        $results = $analyzer->analyze($queries, slowThresholdMs: 50.0);
        $titles = array_map(fn($r) => $r->title, $results);

        $hasNPlusOne = false;
        $hasSlow = false;
        $hasSqli = false;

        foreach ($titles as $t) {
            if (str_contains($t, 'N+1 Query Detected')) $hasNPlusOne = true;
            if (str_contains($t, 'Slow Database Queries')) $hasSlow = true;
            if (str_contains($t, 'Unparameterized Raw SQL Detected')) $hasSqli = true;
        }

        $this->assertTrue($hasNPlusOne, 'Expected N+1 detection');
        $this->assertTrue($hasSlow, 'Expected slow query detection');
        $this->assertTrue($hasSqli, 'Expected SQL injection risk detection');
    }

    public function testProductionDoctorChecksPhpVersion(): void
    {
        $doctor = new ProductionDoctor();
        $results = $doctor->diagnose(__DIR__ . '/../../../skeleton');

        $phpCheck = current(array_filter($results, fn($r) => $r->title === 'PHP Version Check'));
        $this->assertTrue(!empty($phpCheck));
        $this->assertEquals(DiagnosticResult::LEVEL_PASS, $phpCheck->level);
    }

    public function testSentinelAuditCalculatesScoreAndGrade(): void
    {
        $report = Sentinel::audit(
            basePath: __DIR__ . '/../../../skeleton',
            config: [
                'app' => ['env' => 'development', 'debug' => true, 'key' => 'base64:secure-32-byte-encryption-key'],
                'session' => ['http_only' => true, 'same_site' => 'lax'],
            ],
            queries: []
        );

        $this->assertArrayHasKey('score', $report);
        $this->assertArrayHasKey('grade', $report);
        $this->assertArrayHasKey('counts', $report);
        $this->assertGreaterThanOrEqual(80, $report['score']);
        $this->assertTrue($report['is_healthy']);
    }
}
