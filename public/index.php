<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$page = $_GET['page'] ?? 'home';

echo "<h1>Welcome to EksporSiap MVP</h1>";
echo "<p>Current page: " . htmlspecialchars($page) . "</p>";
