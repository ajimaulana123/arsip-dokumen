<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['file'])) {
    $file = urldecode($_GET['file']); // Decode file name
    $filePath = '../uploads/' . $file;

    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            $_SESSION['message'] = "File berhasil dihapus.";
        } else {
            $_SESSION['error'] = "Gagal menghapus file.";
        }
    } else {
        $_SESSION['error'] = "File tidak ditemukan.";
    }
} else {
    $_SESSION['error'] = "File tidak ditentukan.";
}

header("Location: file_manager.php");
exit();
?>
