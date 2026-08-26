<?php

declare(strict_types=1);

namespace Switch\Foundation\Sentinel;

class ProductionDoctor
{
    /**
     * Run all production readiness and system health checks.
     *
     * @param string $basePath Project root directory path
     * @param array<string, mixed> $config
     * @return array<int, DiagnosticResult>
     */
    public function diagnose(string $basePath, array $config = []): array
    {
        $results = [];

        $env = strtolower((string) ($config['app']['env'] ?? (getenv('APP_ENV') ?: 'development')));
        $isProduction = in_array($env, ['production', 'prod'], true);

        // 1. PHP Version
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            $results[] = DiagnosticResult::critical(
                'PHP Runtime',
                'Unsupported PHP Version: ' . PHP_VERSION,
                'Switch Framework requires PHP 8.2 or newer for performance, typing, and security patches.',
                'Upgrade your server PHP version to 8.2 or higher.'
            );
        } else {
            $results[] = DiagnosticResult::pass('PHP Runtime', 'PHP Version Check', 'Running PHP ' . PHP_VERSION . ' (>= 8.2).');
        }

        // 2. Storage Directory Write Permissions
        $storageDirs = [
            'storage' => $basePath . '/storage',
            'storage/cache' => $basePath . '/storage/cache',
            'storage/logs' => $basePath . '/storage/logs',
            'storage/views' => $basePath . '/storage/views',
        ];

        $unwritable = [];
        foreach ($storageDirs as $name => $dir) {
            if (is_dir($dir) && !is_writable($dir)) {
                $unwritable[] = $name;
            }
        }

        if (!empty($unwritable)) {
            $results[] = DiagnosticResult::critical(
                'Filesystem',
                'Unwritable Storage Directories: ' . implode(', ', $unwritable),
                'The web server process cannot write cache files, session data, compiled views, or error logs.',
                'Grant write permissions: `chmod -R 775 storage` or ensure web user owns storage directory.'
            );
        } else {
            $results[] = DiagnosticResult::pass('Filesystem', 'Storage Write Permissions', 'All storage directories are writable.');
        }

        // 3. OPcache Status (Production check)
        $opcacheEnabled = function_exists('opcache_get_status') && ini_get('opcache.enable') === '1';
        if ($isProduction && !$opcacheEnabled) {
            $results[] = DiagnosticResult::warning(
                'Performance',
                'PHP OPcache Disabled in Production',
                'Without OPcache bytecode compilation caching, PHP re-parses every script on every request, reducing throughput by 60-80%.',
                'Enable `opcache.enable=1` and `opcache.enable_cli=1` in your server php.ini.'
            );
        } else {
            $results[] = DiagnosticResult::pass('Performance', 'OPcache Configuration', $opcacheEnabled ? 'OPcache is active.' : 'OPcache check passed for development.');
        }

        // 4. display_errors Setting
        $displayErrors = strtolower((string) ini_get('display_errors'));
        $displayErrorsOn = in_array($displayErrors, ['1', 'on', 'true', 'yes'], true);

        if ($isProduction && $displayErrorsOn) {
            $results[] = DiagnosticResult::critical(
                'Security',
                'PHP display_errors Enabled in Production',
                'Fatal errors and uncaught exceptions will output raw code and database passwords directly into public HTML responses.',
                'Set `display_errors=Off` and `log_errors=On` in your production php.ini.'
            );
        } else {
            $results[] = DiagnosticResult::pass('Security', 'Error Display Policy', 'display_errors is properly configured.');
        }

        // 5. expose_php Setting
        $exposePhp = strtolower((string) ini_get('expose_php'));
        $exposePhpOn = in_array($exposePhp, ['1', 'on', 'true', 'yes'], true);

        if ($isProduction && $exposePhpOn) {
            $results[] = DiagnosticResult::info(
                'Security',
                'PHP expose_php Enabled',
                'The `X-Powered-By: PHP/' . PHP_VERSION . '` HTTP response header is sent, leaking the server PHP version to scanners.',
                'Set `expose_php=Off` in your php.ini.'
            );
        }

        // 6. SQLite Database Permissions (if sqlite used)
        $dbConnection = (string) ($config['database']['default'] ?? (getenv('DB_CONNECTION') ?: 'sqlite'));
        if ($dbConnection === 'sqlite') {
            $sqlitePath = (string) ($config['database']['connections']['sqlite']['database'] ?? ($basePath . '/database/database.sqlite'));
            if (file_exists($sqlitePath)) {
                if (!is_writable($sqlitePath) || !is_writable(dirname($sqlitePath))) {
                    $results[] = DiagnosticResult::critical(
                        'Database',
                        'SQLite Database or Directory Not Writable',
                        'SQLite requires write permissions on both the database file and its parent folder for transaction journaling.',
                        'Grant write permissions to `database/` and `' . basename($sqlitePath) . '`.'
                    );
                } else {
                    $results[] = DiagnosticResult::pass('Database', 'SQLite Database Access', 'SQLite file and directory are writable.');
                }
            }
        }

        return $results;
    }
}
