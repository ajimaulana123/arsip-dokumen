<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Hapus user
    $query = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $query);
    mysqli_stmt_bind_param($stmt, "i", $id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "User berhasil dihapus!";
    } else {
        $_SESSION['error_message'] = "Gagal menghapus user!";
    }
    
    mysqli_stmt_close($stmt);
}

header("Location: users.php");
exit();
?>