<?php
if (PHP_SAPI !== 'cli-server' || getenv('LENDING_BROWSER_TEST') !== '1') { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/favicon.ico') { http_response_code(204); return; }
if ($path === '/__browser_check') { require __DIR__.'/browser-check.html'; return; }
if (preg_match('#^/assets/[a-zA-Z0-9_./-]+\.(css|js)$#D', $path) && !str_contains($path, '..')) return false;
require __DIR__.'/../public/index.php';
