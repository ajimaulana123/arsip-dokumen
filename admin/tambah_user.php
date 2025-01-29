<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/db.php';

// Menangani form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $name = mysqli_real_escape_string($koneksi, $_POST['name']);
    $nik = mysqli_real_escape_string($koneksi, $_POST['nik']);
    $jabatan = mysqli_real_escape_string($koneksi, $_POST['jabatan']);
    $role = mysqli_real_escape_string($koneksi, $_POST['role']);

    $error_message = "";

    // Validasi input
    if (empty($username) || empty($_POST['password']) || empty($name) || empty($nik) || empty($jabatan) || empty($role)) {
        $_SESSION['error_message'] = "Semua field harus diisi!";
        header("Location: users.php");
        exit();
    }

    // Cek apakah username sudah terdaftar
    $check_username_query = "SELECT username FROM users WHERE username = ?";
    $check_username_stmt = mysqli_prepare($koneksi, $check_username_query);
    mysqli_stmt_bind_param($check_username_stmt, "s", $username);
    mysqli_stmt_execute($check_username_stmt);
    mysqli_stmt_store_result($check_username_stmt);
    
    if (mysqli_stmt_num_rows($check_username_stmt) > 0) {
        $_SESSION['error_message'] = "Username sudah terdaftar!";
        header("Location: users.php");
        exit();
    }
    mysqli_stmt_close($check_username_stmt);

    // Insert data ke database
    $query = "INSERT INTO users (username, password, name, nik, jabatan, role) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($koneksi, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssss", $username, $password, $name, $nik, $jabatan, $role);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "User baru berhasil ditambahkan!";
        } else {
            $_SESSION['error_message'] = "Gagal menambahkan user: " . mysqli_error($koneksi);
        }
        
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Error dalam prepared statement: " . mysqli_error($koneksi);
    }
    
    header("Location: users.php");
    exit();
}

// Jika bukan POST request, redirect ke users.php
header("Location: users.php");
exit();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa; /* Warna latar belakang lebih cerah */
            color: #333; /* Warna teks gelap untuk kontras */
        }
        .card {
            margin: 120px auto; /* Margin atas dan bawah */
            background-color: #ffffff; /* Warna kartu putih */
            border-radius: 10px; /* Sudut melengkung */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Bayangan lembut */
        }
        .card-header {
            background-color: #007bff; /* Warna biru cerah untuk header */
            color: white;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .form-label {
            font-weight: bold; /* Teks label tebal */
        }
        .btn-primary {
            background-color: #007bff; /* Warna tombol biru cerah */
            border-color: #007bff; /* Border tombol biru cerah */
        }
        .btn-primary:hover {
            background-color: #0056b3; /* Warna saat hover */
            border-color: #0056b3; /* Border saat hover */
        }
        .input-group-text {
            cursor: pointer; /* Menunjukkan bahwa ikon bisa diklik */
        }
        
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8"> 
                <div class="card">
                    <div class="card-header">
                        <h1 class="h5">Tambah User</h1>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo nl2br(htmlspecialchars($error_message)); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo htmlspecialchars($success_message); ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="row"> 
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control form-control-sm" name="username" id="username" required>
                                    </div>
                                    <div class="mb-3 input-group">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control form-control-sm" name="password" id="password" required>
                                        <span class="input-group-text" id="togglePassword"><i class="fas fa-eye"></i></span> <!-- Ikon mata -->
                                    </div>
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nama Lengkap</label>
                                        <input type="text" class="form-control form-control-sm" name="name" id="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="nik" class="form-label">NIK</label>
                                        <input type="text" class="form-control form-control-sm" name="nik" id="nik" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="jabatan" class="form-label">Jabatan</label>
                                        <input type="text" class="form-control form-control-sm" name="jabatan" id="jabatan" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <select class="form-select form-select-sm" name="role" id="role" required>
                                            <option value="">Pilih Role</option> <!-- Tambahkan opsi default -->
                                            <option value="user">User</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!-- Tombol tambah dan batal -->
                            <button type="submit" class="btn btn-primary btn-sm">Tambah</button>
                            <a href="users.php" onclick='window.history.back();' class='btn btn-secondary btn-sm'>Tutup</a> <!-- Tombol Batal -->
                        </form>

                    </div> <!-- End of card-body -->
                </div> <!-- End of card -->
            </div> <!-- End of col-md-8 -->
        </div> <!-- End of row -->
    </div> <!-- End of container -->

    <!-- Script untuk toggle password visibility -->
    <script src="//cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Menambahkan fungsionalitas untuk ikon mata -->
    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function () {
            // Toggle antara tipe password dan text
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon mata
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    </script>

</body>
</html>

