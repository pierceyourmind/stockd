<?php
declare(strict_types=1);

/**
 * Send JSON response and exit
 */
function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Clean numeric strings by removing $, %, +, commas
 * Convert null indicators (--, n/a, N/A, empty) to null
 */
function cleanNumeric(?string $value): ?float {
    if ($value === null || $value === '' || $value === '--' || strtolower(trim($value)) === 'n/a') {
        return null;
    }

    $cleaned = preg_replace('/[\$,%+]/', '', trim($value));
    $cleaned = str_replace(',', '', $cleaned);

    return is_numeric($cleaned) ? (float) $cleaned : null;
}

/**
 * Find closest price in historical data to target timestamp
 */
function findClosestPrice(array $timestamps, array $closes, int $targetTime): ?float {
    if (empty($timestamps) || empty($closes)) {
        return null;
    }

    $closestIdx = 0;
    $closestDiff = PHP_INT_MAX;

    foreach ($timestamps as $idx => $ts) {
        $diff = abs($ts - $targetTime);
        if ($diff < $closestDiff) {
            $closestDiff = $diff;
            $closestIdx = $idx;
        }
    }

    // Return the close price, skipping nulls
    $price = $closes[$closestIdx] ?? null;
    if ($price === null) {
        // Try nearby indices if this one is null
        for ($i = 1; $i <= 5; $i++) {
            if (isset($closes[$closestIdx + $i]) && $closes[$closestIdx + $i] !== null) {
                return (float) $closes[$closestIdx + $i];
            }
            if (isset($closes[$closestIdx - $i]) && $closes[$closestIdx - $i] !== null) {
                return (float) $closes[$closestIdx - $i];
            }
        }
    }

    return $price !== null ? (float) $price : null;
}
