<?php
$host = 'd-hd9.h.filess.io';
$username = 'arsipdokumen_woodenwall';
$password = 'bb9afc0d77c1297312ec865b20c79224042a2d03'; // Kosongkan jika tidak ada password
$dbname = 'arsipdokumen_woodenwall'; // Nama database yang telah dibuat
$port = 3307;

// Membuat koneksi ke database
$koneksi = mysqli_connect($host, $username, $password, $dbname, $port);

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Set charset ke UTF-8
mysqli_set_charset($koneksi, "utf8");
?>
