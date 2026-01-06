<?php
header('Content-Type: application/json');
echo json_encode([
    'rewrite_test' => 'SUCCESS',
    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'none',
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'none'
]);
