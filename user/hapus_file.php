<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

if (isset($_GET['id'])) {
    $file_id = $_GET['id'];
    
    // Mulai transaksi
    mysqli_begin_transaction($koneksi);
    
    try {
        // Ambil informasi file sebelum dihapus
        $query = "SELECT f.file_path, fo.folder_name 
                 FROM files f 
                 LEFT JOIN folders fo ON f.folder_id = fo.folder_id 
                 WHERE f.file_id = ?";
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "i", $file_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $file_data = mysqli_fetch_assoc($result);
        
        if ($file_data) {
            // Hapus file fisik
            $file_path = '../uploads/' . $file_data['folder_name'] . '/' . $file_data['file_path'];
            if (file_exists($file_path)) {
                if (!unlink($file_path)) {
                    throw new Exception("Gagal menghapus file fisik");
                }
            }
            
            // Hapus record dari database
            $delete_query = "DELETE FROM files WHERE file_id = ?";
            $delete_stmt = mysqli_prepare($koneksi, $delete_query);
            mysqli_stmt_bind_param($delete_stmt, "i", $file_id);
            
            if (!mysqli_stmt_execute($delete_stmt)) {
                throw new Exception("Gagal menghapus data file dari database");
            }
            
            mysqli_commit($koneksi);
            $_SESSION['success_message'] = "File berhasil dihapus!";
        } else {
            throw new Exception("File tidak ditemukan");
        }
        
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }
}

header("Location: file.php");
exit();
?> 