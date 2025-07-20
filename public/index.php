<?php
require '../vendor/autoload.php';

// Load .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!getenv($name)) { // Don't override existing env vars
                putenv("$name=$value");
                $_ENV[$name] = $value;
            }
        }
    }
}

Flight::set('flight.views.path', '../views');

require '../app/Database.php';
require '../app/SmsParser.php';
require '../app/routes.php';

Flight::start();