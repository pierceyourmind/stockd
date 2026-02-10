<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/session.php';

// If already authenticated, redirect to index
if (isAuthenticated()) {
    header('Location: /index.php');
    exit;
}

$error = '';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $passwordHash = $_ENV['AUTH_PASSWORD_HASH'] ?? '';

    if (!empty($passwordHash) && password_verify($password, $passwordHash)) {
        // Login successful
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['regenerated_time'] = time();
        header('Location: /index.php');
        exit;
    } else {
        $error = 'Invalid password';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Stockd</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        main {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        .error {
            color: #e53e3e;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        h1 {
            text-align: center;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <main>
        <article>
            <h1>Stockd</h1>
            <form method="POST">
                <label for="password">
                    Password
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autofocus
                        placeholder="Enter password"
                    >
                </label>
                <?php if ($error): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <button type="submit">Login</button>
            </form>
        </article>
    </main>
</body>
</html>
