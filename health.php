<?php
header('Content-Type: application/json');

try {
    // Basic system check
    $health = [
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'environment' => getenv('RAILWAY_ENVIRONMENT') ?: 'local',
        'services' => [
            'web' => 'up',
            'database' => 'checking'
        ]
    ];

    // Check database connection
    if (file_exists('includes/db.php')) {
        include 'includes/db.php';
        if ($koneksi && mysqli_ping($koneksi)) {
            $health['services']['database'] = 'up';
        } else {
            $health['services']['database'] = 'down';
            $health['status'] = 'unhealthy';
        }
    }

    // Check uploads directory
    $uploadsPath = __DIR__ . '/uploads';
    if (!is_dir($uploadsPath)) {
        mkdir($uploadsPath, 0777, true);
    }
    if (!is_writable($uploadsPath)) {
        $health['status'] = 'unhealthy';
        $health['services']['uploads'] = 'not writable';
    } else {
        $health['services']['uploads'] = 'writable';
    }

    http_response_code($health['status'] === 'healthy' ? 200 : 500);
    echo json_encode($health, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}