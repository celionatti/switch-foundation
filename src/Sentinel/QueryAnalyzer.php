<?php

declare(strict_types=1);

namespace Switch\Foundation\Sentinel;

class QueryAnalyzer
{
    /**
     * Analyze a list of executed SQL queries for vulnerabilities and performance bottlenecks.
     *
     * @param array<int, array<string, mixed>> $queries List of query records (sql, time_ms, bindings, etc.)
     * @param float $slowThresholdMs
     * @return array<int, DiagnosticResult>
     */
    public function analyze(array $queries, float $slowThresholdMs = 50.0): array
    {
        $results = [];

        if (empty($queries)) {
            return [DiagnosticResult::pass('Database', 'Query Health', 'No SQL queries executed or analyzed.')];
        }

        $templates = [];
        $slowQueries = [];
        $unparameterizedRisks = [];
        $unboundedSelects = [];

        foreach ($queries as $q) {
            $rawSql = (string) ($q['sql'] ?? '');
            $timeMs = (float) ($q['time_ms'] ?? 0.0);
            $bindings = (array) ($q['bindings'] ?? []);

            // 1. Slow Query Check
            if ($timeMs >= $slowThresholdMs) {
                $slowQueries[] = [
                    'sql' => $rawSql,
                    'time_ms' => $timeMs,
                ];
            }

            // 2. Unparameterized SQL Injection Risk Check
            // Check if query contains raw string/integer concatenations in WHERE clause without parameter placeholders (? or :param)
            if (empty($bindings)) {
                if (preg_match("/WHERE\s+[^=><]+(=|>|<|LIKE|IN)\s*('[^']*'|\"[^\"]*\"|\d+)/i", $rawSql)
                    && !preg_match("/WHERE\s+[^=><]+(=|>|<|LIKE|IN)\s*\?/i", $rawSql)
                ) {
                    $unparameterizedRisks[] = $rawSql;
                }
            }

            // 3. Unbounded SELECT * without LIMIT on non-schema queries
            if (preg_match('/^SELECT\s+\*\s+FROM\s+([a-zA-Z0-9_]+)\s*$/i', trim($rawSql), $matches)
                || (preg_match('/^SELECT\s+\*\s+FROM\s+/i', trim($rawSql)) && !preg_match('/\bLIMIT\b/i', $rawSql) && !preg_match('/\bWHERE\s+id\s*=/i', $rawSql))
            ) {
                $unboundedSelects[] = $rawSql;
            }

            // 4. Group for N+1 Query Detection
            $normalized = preg_replace("/\b\d+\b|'[^']*'|\"[^\"]*\"/", '?', $rawSql);
            $templates[$normalized][] = $rawSql;
        }

        // Generate Diagnostic Results

        // N+1 Queries
        $duplicateGroups = array_filter($templates, fn($list) => count($list) >= 3);
        if (!empty($duplicateGroups)) {
            foreach ($duplicateGroups as $template => $instances) {
                $count = count($instances);
                $results[] = DiagnosticResult::warning(
                    'Database Performance',
                    "Possible N+1 Query Detected ({$count}x)",
                    "Query template `{$template}` was executed {$count} times during a single request. This is typically caused by loading relations in a loop.",
                    "Use eager loading (`with(['relation'])`) to batch-load related records in a single query.",
                    ['count' => $count, 'sample' => $instances[0]]
                );
            }
        } else {
            $results[] = DiagnosticResult::pass('Database Performance', 'N+1 Query Check', 'No duplicate query loops detected.');
        }

        // SQL Injection Risks
        if (!empty($unparameterizedRisks)) {
            foreach (array_slice($unparameterizedRisks, 0, 3) as $riskSql) {
                $results[] = DiagnosticResult::critical(
                    'SQL Security',
                    'Unparameterized Raw SQL Detected (SQLi Risk)',
                    "Query appears to concatenate raw values directly into SQL: `{$riskSql}`. This exposes the database to SQL injection.",
                    'Use parameterized query bindings (`where("column", "=", $value)`) or PDO prepared statements.',
                    ['sql' => $riskSql]
                );
            }
        } else {
            $results[] = DiagnosticResult::pass('SQL Security', 'Parameter Binding Check', 'All analyzed queries use parameterized bindings.');
        }

        // Slow Queries
        if (!empty($slowQueries)) {
            $count = count($slowQueries);
            $maxTime = max(array_column($slowQueries, 'time_ms'));
            $results[] = DiagnosticResult::warning(
                'Database Performance',
                "Slow Database Queries ({$count} queries > {$slowThresholdMs}ms)",
                "The slowest query took {$maxTime}ms: `{$slowQueries[0]['sql']}`.",
                'Add database indexes on filtering/sorting columns or optimize query joins.',
                ['slow_count' => $count, 'max_time_ms' => $maxTime]
            );
        } else {
            $results[] = DiagnosticResult::pass('Database Performance', 'Query Execution Speed', "All queries executed under {$slowThresholdMs}ms.");
        }

        // Unbounded SELECT *
        if (!empty($unboundedSelects)) {
            $results[] = DiagnosticResult::info(
                'Database Performance',
                'Unbounded SELECT * Without LIMIT',
                'One or more queries fetch all columns with no pagination limit: `' . $unboundedSelects[0] . '`. As the table grows, this causes memory exhaustion.',
                'Specify needed columns (`select(["id", "name"])`) and add pagination with `paginate()` or `limit()`.'
            );
        }

        return $results;
    }
}
