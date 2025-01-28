<?php
header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('Y-m-d H:i:s'),
    'services' => [
        'web' => 'up',
        'database' => 'checking'
    ]
];

// Check database connection
if (file_exists('includes/db.php')) {
    include 'includes/db.php';
    if ($koneksi) {
        $health['services']['database'] = 'up';
    } else {
        $health['services']['database'] = 'down';
        $health['status'] = 'unhealthy';
    }
}

http_response_code($health['status'] === 'healthy' ? 200 : 500);
echo json_encode($health, JSON_PRETTY_PRINT);