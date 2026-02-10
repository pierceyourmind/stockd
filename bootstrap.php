<?php
declare(strict_types=1);

// Temporary .env loader -- replaced by phpdotenv in Plan 02
// This provides minimal .env file parsing until Composer is installed

$envFile = __DIR__ . '/.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        // Skip comments and empty lines
        $line = trim($line);
        if (empty($line) || $line[0] === '#') {
            continue;
        }

        // Parse KEY=VALUE
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        // Strip surrounding quotes if present
        if (strlen($value) >= 2) {
            $firstChar = $value[0];
            $lastChar = $value[strlen($value) - 1];
            if (($firstChar === '"' && $lastChar === '"') || ($firstChar === "'" && $lastChar === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Set environment variable
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}
