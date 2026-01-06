<?php
header('Content-Type: application/json');
echo json_encode([
    'test' => 'working',
    'uri' => $_SERVER['REQUEST_URI'] ?? 'no uri',
    'script' => $_SERVER['SCRIPT_NAME'] ?? 'no script',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'no root'
]);
