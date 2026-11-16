<?php

declare(strict_types=1);

use Illuminate\Http\Request;

require __DIR__.'/bootstrap.php';
$app = loadTestApplication();
header('X-IPG-Load-Worker: '.getmypid());
header('X-IPG-Load-Memory-Limit: '.ini_get('memory_limit'));
$app->handleRequest(Request::capture());
