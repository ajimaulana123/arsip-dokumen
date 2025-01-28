<?php
// Redirect ke halaman login jika belum login
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
} else {
    // Redirect berdasarkan role
    if ($_SESSION['role'] == 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}