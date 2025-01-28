// health.php di root folder
<?php
include 'includes/db.php';

if ($koneksi) {
    http_response_code(200);
    echo json_encode(['status' => 'healthy', 'message' => 'Database connected']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'unhealthy', 'message' => mysqli_connect_error()]);
}