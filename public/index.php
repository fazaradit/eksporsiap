<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle API requests
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($requestUri === '/api/classify') {
    require_once __DIR__ . '/../src/Controllers/ClassifyController.php';
    $controller = new ClassifyController();
    $controller->classify();
    exit;
}

// Existing fallback
$page = $_GET['page'] ?? 'home';

echo "<h1>Welcome to EksporSiap MVP</h1>";
echo "<p>Current page: " . htmlspecialchars($page) . "</p>";
