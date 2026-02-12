<?php
declare(strict_types=1);

// Start session with secure cookie configuration
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_start([
    'cookie_lifetime' => 0,
    'cookie_secure' => $isSecure,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_path' => '/',
]);

/**
 * Check if user is authenticated
 */
function isAuthenticated(): bool
{
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Require authentication - redirect to login or return 401 JSON
 */
function requireAuth(): void
{
    // Regenerate session ID every 15 minutes for security
    if (isAuthenticated()) {
        $regenerateInterval = 15 * 60; // 15 minutes in seconds
        if (!isset($_SESSION['regenerated_time']) || (time() - $_SESSION['regenerated_time']) > $regenerateInterval) {
            session_regenerate_id(true);
            $_SESSION['regenerated_time'] = time();
        }
    }

    if (!isAuthenticated()) {
        // Check if this is an API request
        $isApiRequest = false;

        // Check Content-Type header for JSON
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $isApiRequest = true;
        }

        // Check if action parameter is set (API pattern)
        if (isset($_GET['action'])) {
            $isApiRequest = true;
        }

        if ($isApiRequest) {
            // Return 401 JSON for API requests
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        } else {
            // Redirect to login for page requests
            header('Location: /auth/login.php');
            exit;
        }
    }
}
