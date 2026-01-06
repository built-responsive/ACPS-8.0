<?php
// API Router - forwards all /api requests to /vs/public/index.php
$_SERVER['SCRIPT_NAME'] = '/vs/public/index.php';
require_once __DIR__ . '/../vs/public/index.php';
