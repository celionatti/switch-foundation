<?php

declare(strict_types=1);

namespace Switch\Foundation\Sentinel;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Sentinel
{
    /**
     * Run a comprehensive audit on the entire application.
     *
     * @param string $basePath Project root directory
     * @param array<string, mixed> $config Application configuration
     * @param array<int, array<string, mixed>> $queries Analyzed query records
     * @return array{score: int, grade: string, is_healthy: bool, counts: array<string, int>, results: array<int, DiagnosticResult>}
     */
    public static function audit(string $basePath = '', array $config = [], array $queries = []): array
    {
        $basePath = $basePath !== '' ? $basePath : (defined('SWITCH_BASE_PATH') ? constant('SWITCH_BASE_PATH') : getcwd());

        $security = new SecurityScanner();
        $doctor = new ProductionDoctor();
        $queryAnalyzer = new QueryAnalyzer();

        $results = array_merge(
            $security->auditConfig($config),
            $doctor->diagnose($basePath, $config),
            $queryAnalyzer->analyze($queries)
        );

        $counts = [
            'critical' => 0,
            'warning' => 0,
            'info' => 0,
            'pass' => 0,
        ];

        $penalty = 0;
        foreach ($results as $res) {
            if ($res->isCritical()) {
                $counts['critical']++;
                $penalty += 25;
            } elseif ($res->isWarning()) {
                $counts['warning']++;
                $penalty += 8;
            } elseif ($res->level === DiagnosticResult::LEVEL_INFO) {
                $counts['info']++;
                $penalty += 2;
            } else {
                $counts['pass']++;
            }
        }

        $score = max(0, min(100, 100 - $penalty));
        $grade = match (true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'F',
        };

        return [
            'score' => $score,
            'grade' => $grade,
            'is_healthy' => $counts['critical'] === 0,
            'counts' => $counts,
            'results' => $results,
        ];
    }

    /**
     * Inspect a live HTTP request/response in real-time.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface|null $response
     * @return array<int, DiagnosticResult>
     */
    public static function inspectRequest(ServerRequestInterface $request, ?ResponseInterface $response = null): array
    {
        $scanner = new SecurityScanner();
        return $scanner->inspectRuntime($request, $response);
    }

    /**
     * Inspect executed queries in real-time.
     *
     * @param array<int, array<string, mixed>> $queries
     * @param float $slowThresholdMs
     * @return array<int, DiagnosticResult>
     */
    public static function inspectQueries(array $queries, float $slowThresholdMs = 50.0): array
    {
        $analyzer = new QueryAnalyzer();
        return $analyzer->analyze($queries, $slowThresholdMs);
    }
}
