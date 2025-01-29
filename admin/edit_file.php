<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['id'])) {
    $file_id = $_GET['id'];
    $document_number = mysqli_real_escape_string($koneksi, $_POST['document_number']);
    $document_type = mysqli_real_escape_string($koneksi, $_POST['document_type']);
    $description = mysqli_real_escape_string($koneksi, $_POST['description']);
    $folder_id = mysqli_real_escape_string($koneksi, $_POST['folder_id']);

    // Mulai transaksi
    mysqli_begin_transaction($koneksi);
    try {
        // Ambil data file lama
        $old_file_query = "SELECT f.file_path, fo.folder_name 
                          FROM files f 
                          LEFT JOIN folders fo ON f.folder_id = fo.folder_id 
                          WHERE f.file_id = ?";
        $old_file_stmt = mysqli_prepare($koneksi, $old_file_query);
        mysqli_stmt_bind_param($old_file_stmt, "i", $file_id);
        mysqli_stmt_execute($old_file_stmt);
        $old_file_result = mysqli_stmt_get_result($old_file_stmt);
        $old_file_data = mysqli_fetch_assoc($old_file_result);
        
        // Update informasi file
        $query = "UPDATE files SET 
                  document_number = ?,
                  document_type = ?,
                  description = ?,
                  folder_id = ?
                  WHERE file_id = ?";
                  
        $stmt = mysqli_prepare($koneksi, $query);
        mysqli_stmt_bind_param($stmt, "ssssi", 
            $document_number, 
            $document_type, 
            $description, 
            $folder_id,
            $file_id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Gagal mengupdate informasi file: " . mysqli_error($koneksi));
        }

        // Jika ada file baru yang diupload
        if (isset($_FILES['scanned_image']) && $_FILES['scanned_image']['size'] > 0) {
            // Hapus file lama
            $old_file_path = '../uploads/' . $old_file_data['folder_name'] . '/' . $old_file_data['file_path'];
            if (file_exists($old_file_path)) {
                unlink($old_file_path);
            }

            // Ambil informasi folder baru
            $folder_query = "SELECT folder_name FROM folders WHERE folder_id = ?";
            $folder_stmt = mysqli_prepare($koneksi, $folder_query);
            mysqli_stmt_bind_param($folder_stmt, "i", $folder_id);
            mysqli_stmt_execute($folder_stmt);
            $folder_result = mysqli_stmt_get_result($folder_stmt);
            $folder_data = mysqli_fetch_assoc($folder_result);
            
            // Generate nama file baru
            $file_extension = pathinfo($_FILES['scanned_image']['name'], PATHINFO_EXTENSION);
            $new_filename = $document_number . '_' . time() . '.' . $file_extension;
            $upload_path = '../uploads/' . $folder_data['folder_name'] . '/' . $new_filename;

            // Upload file baru
            if (move_uploaded_file($_FILES['scanned_image']['tmp_name'], $upload_path)) {
                // Update nama file di database
                $update_file_query = "UPDATE files SET file_path = ? WHERE file_id = ?";
                $update_file_stmt = mysqli_prepare($koneksi, $update_file_query);
                mysqli_stmt_bind_param($update_file_stmt, "si", $new_filename, $file_id);
                
                if (!mysqli_stmt_execute($update_file_stmt)) {
                    throw new Exception("Gagal mengupdate nama file: " . mysqli_error($koneksi));
                }
            } else {
                throw new Exception("Gagal mengupload file baru");
            }
        }

        mysqli_commit($koneksi);
        $_SESSION['success_message'] = "File berhasil diperbarui!";
        header("Location: file.php");
        exit();
        
    } catch (Exception $e) {
        mysqli_rollback($koneksi);
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
        header("Location: file.php");
        exit();
    }
}

// Jika sampai di sini, berarti bukan POST request
$_SESSION['error_message'] = "Metode request tidak valid";
header("Location: file.php");
exit();
?> 