<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

include '../includes/db.php';

if (!isset($_GET['folder_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Folder ID is required');
}

$folder_id = intval($_GET['folder_id']);

$query = "SELECT f.*, fo.folder_name 
          FROM files f 
          LEFT JOIN folders fo ON f.folder_id = fo.folder_id 
          WHERE f.folder_id = ?";

$stmt = mysqli_prepare($koneksi, $query);
mysqli_stmt_bind_param($stmt, "i", $folder_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$files = [];
while ($row = mysqli_fetch_assoc($result)) {
    $file_extension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
    $is_image = in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif']);
    
    $files[] = [
        'file_id' => $row['file_id'],
        'document_number' => $row['document_number'],
        'document_type' => $row['document_type'],
        'file_path' => '../uploads/' . $row['folder_name'] . '/' . $row['file_path'],
        'is_image' => $is_image
    ];
}

header('Content-Type: application/json');
echo json_encode($files);
?> 