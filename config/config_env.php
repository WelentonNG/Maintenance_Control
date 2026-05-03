<?php

$envPath = __DIR__ . '/.env';

if (!file_exists($envPath)) {
    die("Arquivo .env não encontrado.");
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    $trimmed = trim($line);

    if ($trimmed === '' || strpos($trimmed, '#') === 0) {
        continue;
    }

    if (strpos($trimmed, '=') === false) {
        continue;
    }

    list($name, $value) = explode('=', $trimmed, 2);

    $name = trim($name);
    $value = trim($value);

    putenv("$name=$value");
}

$config = [
    'APP_ENV'    => getenv('APP_ENV'),
    'APP_DEBUG'  => getenv('APP_DEBUG') === 'true',

    'DB_HOST'    => getenv('DB_HOST'),
    'DB_NAME'    => getenv('DB_NAME'),
    'DB_USER'    => getenv('DB_USER'),
    'DB_PASS'    => getenv('DB_PASS'),
    'DB_CHARSET' => getenv('DB_CHARSET'),
];


return $config;

