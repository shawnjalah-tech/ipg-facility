<?php

declare(strict_types=1);
use Illuminate\Foundation\Application;

/** Bootstrap an isolated load-test app; never load the project's .env or caches. */
function loadTestApplication(): Application
{
    $project = dirname(__DIR__, 2);
    $run = realpath((string) getenv('IPG_LOAD_RUN_DIR'));
    $token = (string) getenv('IPG_LOAD_TOKEN');
    if (! $run || strlen($token) < 32 || ! is_file($run.'/run.json')) {
        throw new RuntimeException('Missing private load-test workspace. Use node tests/Load/run.mjs.');
    }
    $marker = json_decode(file_get_contents($run.'/run.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($marker['project'] ?? '') !== realpath($project)
        || ! hash_equals($marker['token_hash'] ?? '', hash('sha256', $token))
        || str_starts_with($run, realpath($project).DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Invalid load-test isolation marker.');
    }
    $settings = [
        'APP_ENV' => 'load-testing', 'APP_DEBUG' => 'false',
        'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $run.'/load.sqlite', 'DB_URL' => '',
        'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file',
        'SESSION_COOKIE' => 'ipg_load_session', 'SESSION_SECURE_COOKIE' => 'false',
        'SESSION_DOMAIN' => '', 'LOG_CHANNEL' => 'single',
        'APP_CONFIG_CACHE' => $run.'/bootstrap/cache/config.php',
        'APP_ROUTES_CACHE' => $run.'/bootstrap/cache/routes.php',
        'APP_EVENTS_CACHE' => $run.'/bootstrap/cache/events.php',
        'APP_SERVICES_CACHE' => $run.'/bootstrap/cache/services.php',
        'APP_PACKAGES_CACHE' => $run.'/bootstrap/cache/packages.php',
        'VIEW_COMPILED_PATH' => $run.'/storage/framework/views',
    ];
    foreach ($settings as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    require_once $project.'/vendor/autoload.php';
    $app = require $project.'/bootstrap/app.php';
    $app->useStoragePath($run.'/storage');
    $app->useEnvironmentPath($run);
    $app->loadEnvironmentFrom('.env.load-test-unused');

    return $app;
}
