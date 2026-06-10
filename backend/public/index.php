<?php
require_once __DIR__ . '/../dbconnect.php';
require_once __DIR__ . '/../middleware/ValidateTenantKey.php';
require_once __DIR__ . '/../middleware/AuthJwt.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Controllers/ContactController.php';
require_once __DIR__ . '/../src/Controllers/SubmissionController.php';
require_once __DIR__ . '/../src/Router.php';

$method = $_SERVER['REQUEST_METHOD'];
$path   = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

Router::dispatch($method, $path);