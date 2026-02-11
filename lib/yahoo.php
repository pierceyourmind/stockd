<?php
declare(strict_types=1);

/**
 * Create Yahoo Finance HTTP context with standard User-Agent
 *
 * @param int $timeout Timeout in seconds (default: 15)
 * @return resource Stream context for use with file_get_contents()
 */
function yahooContext(int $timeout = 15) {
    return stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
            'timeout' => $timeout,
        ],
    ]);
}
