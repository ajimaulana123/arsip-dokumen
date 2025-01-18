<?php
$host = 'localhost';
$username = 'root';
$password = ''; // Kosongkan jika tidak ada password
$dbname = 'arsip_itec'; // Nama database yang telah dibuat

// Membuat koneksi ke database
$koneksi = mysqli_connect($host, $username, $password, $dbname);

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}
?>
