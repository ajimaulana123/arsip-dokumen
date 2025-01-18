<?php
session_start();
include 'includes/db.php';

$username = $_POST['username'];
$password = $_POST['password'];

// Menggunakan prepared statement untuk menghindari SQL Injection
$sql = "SELECT * FROM users WHERE username=?";
$stmt = $koneksi->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Verifikasi password
    if (password_verify($password, $user['password'])) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header("Location: admin/dashboard.php");
        } else {
            header("Location: user/dashboard.php");
        }
        exit;
    } else {
        echo "Login gagal. Username atau password salah.";
    }
} else {
    echo "Login gagal. Username atau password salah.";
}
?>
