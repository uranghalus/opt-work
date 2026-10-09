<?php

// tests/bootstrap.php
// This file is loaded before any tests run, allowing us to set up the testing environment

// Force SQLite in-memory database for testing
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('APP_ENV=testing');

// Load .env.testing if it exists (for any additional test-specific config)
$envTesting = __DIR__ . '/../.env.testing';
if (file_exists($envTesting)) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..', '.env.testing');
    $dotenv->load();
}

// Then load the regular autoloader
require __DIR__ . '/../vendor/autoload.php';