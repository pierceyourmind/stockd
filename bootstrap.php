<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// Load .env file using phpdotenv
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// .env variables are now available via $_ENV
