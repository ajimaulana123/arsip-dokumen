<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$error_message = "";
$success_message = "";
$id = "";
$username = "";
$name = "";
$nik = "";
$jabatan = "";
$role = "";

// Mengambil data pengguna berdasarkan ID
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt_select = mysqli_prepare($koneksi, "SELECT username, name, nik, jabatan, role FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt_select, "i", $id);
    mysqli_stmt_execute($stmt_select);
    $result_select = mysqli_stmt_get_result($stmt_select);

    if ($result_select && mysqli_num_rows($result_select) == 1) {
        $row = mysqli_fetch_assoc($result_select);
        $username = $row['username'];
        $name = $row['name'];
        $nik = $row['nik'];
        $jabatan = $row['jabatan'];
        $role = $row['role'];
    } else {
        $error_message = "User tidak ditemukan!";
    }
    mysqli_stmt_close($stmt_select);

    // Menangani form submission untuk update
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $username_post = trim($_POST['username']);
        $name_post = trim($_POST['name']);
        $nik_post = trim($_POST['nik']);
        $jabatan_post = trim($_POST['jabatan']);
        $role_post = trim($_POST['role']);
        $old_password = trim($_POST['old_password']);
        $new_password = trim($_POST['new_password']);
        $confirm_password = trim($_POST['confirm_password']);

        // Validasi
        if (empty($username_post) || empty($name_post) || empty($nik_post) || empty($jabatan_post) || empty($role_post)) {
            $error_message .= "Semua field harus diisi!<br>";
        }

        if (!empty($new_password)) {
            if (strlen($new_password) < 8) {
                $error_message .= "Password baru minimal 8 karakter!<br>";
            }
            if ($new_password != $confirm_password) {
                $error_message .= "Konfirmasi password baru tidak cocok!<br>";
            }
        }

        // Cek password lama
        if (!empty($old_password)) {
            // Ambil password dari database untuk validasi
            $stmt_check_password = mysqli_prepare($koneksi, "SELECT password FROM users WHERE id = ?");
            mysqli_stmt_bind_param($stmt_check_password, "i", $id);
            mysqli_stmt_execute($stmt_check_password);
            mysqli_stmt_bind_result($stmt_check_password, $hashed_password);
            mysqli_stmt_fetch($stmt_check_password);
            mysqli_stmt_close($stmt_check_password);

            if (!password_verify($old_password, $hashed_password)) {
                $error_message .= "Password lama tidak cocok!<br>";
            }
        }

        if (empty($error_message)) {
            // Update query
            $sql_update = "UPDATE users SET username=?, name=?, nik=?, jabatan=?, role=?";
            $bind_types = "sssss";
            $bind_params = array(&$username_post, &$name_post, &$nik_post, &$jabatan_post, &$role_post);

            if (!empty($new_password)) {
                // Hash password baru jika diubah
                $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql_update .= ", password=?";
                $bind_types .= "s";
                $bind_params[] = &$hashed_new_password;
            }

            // Tambahkan ID ke query update
            $sql_update .= " WHERE id=?";
            $bind_types .= "i";
            $bind_params[] = &$id;

            // Prepare and execute the statement
            $stmt_update = mysqli_prepare($koneksi, $sql_update);
            mysqli_stmt_bind_param($stmt_update, $bind_types, ...$bind_params);

            if (mysqli_stmt_execute($stmt_update)) {
                $success_message = "User berhasil diupdate!";
                // Refresh data setelah update berhasil
                $username = htmlspecialchars($username_post);
                $name = htmlspecialchars($name_post);
                $nik = htmlspecialchars($nik_post);
                $jabatan = htmlspecialchars($jabatan_post);
                $role = htmlspecialchars($role_post);
            } else {
                $error_message = "Error: " . mysqli_error($koneksi);
            }
            mysqli_stmt_close($stmt_update);
        }
    }
} else {
    header("Location: users.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa; /* Warna latar belakang lebih cerah */
            color: #333; /* Warna teks gelap untuk kontras */
        }
        .card {
            margin-top: 20px;
            background-color: #ffffff; /* Warna kartu putih */
            border-radius: 10px; /* Sudut melengkung */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Bayangan lembut */
        }
        .card-header {
            background-color: #ffc107; /* Warna kuning untuk header */
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
        
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10"> <!-- Menggunakan col-md-10 untuk lebar yang lebih baik -->
                <div class="card">
                    <div class="card-header">
                        <h1 class="h5">Edit User</h1>
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
                                <div class="col-md-6"> <!-- Kolom pertama -->
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control form-control-sm" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="old_password" class="form-label">Password Lama</label> <!-- Label di atas input -->
                                        <input type="password" class="form-control form-control-sm" name="old_password" id="old_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">Password Baru</label> <!-- Label di atas input -->
                                        <input type="password" class="form-control form-control-sm" name="new_password" id="new_password" placeholder="Kosongkan jika tidak ingin mengubah">
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Konfirmasi Password</label> <!-- Label di atas input -->
                                        <input type="password" class="form-control form-control-sm" name="confirm_password" id="confirm_password" placeholder="Kosongkan jika tidak ingin mengubah">
                                    </div>
                                </div> <!-- End of col-md-6 -->

                                <div class="col-md-6"> <!-- Kolom kedua -->
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nama Lengkap</label>
                                        <input type="text" class="form-control form-control-sm" name="name" id="name" value="<?php echo htmlspecialchars($name); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="nik" class="form-label">NIK</label>
                                        <input type="text" class="form-control form-control-sm" name="nik" id="nik" value="<?php echo htmlspecialchars($nik); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="jabatan" class="form-label">Jabatan</label>
                                        <input type="text" class="form-control form-control-sm" name="jabatan" id="jabatan" value="<?php echo htmlspecialchars($jabatan); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Role</label>
                                        <select class='form-select form-select-sm' name='role' id='role' required>
                                            <option value=''>Pilih Role</option> <!-- Tambahkan opsi default -->
                                            <option value='user' <?php if ($role == 'user') echo 'selected'; ?>>User</option>
                                            <option value='admin' <?php if ($role == 'admin') echo 'selected'; ?>>Admin</option>
                                        </select>
                                    </div>
                                </div> <!-- End of col-md-6 -->
                            </div> <!-- End of row -->

                            <!-- Tombol simpan dan batal -->
                            <button type='submit' class='btn btn-primary btn-sm'>Simpan</button>
                            <a href='users.php' class='btn btn-secondary btn-sm'>Batal</a> <!-- Tombol Batal -->
                        </form>

                    </div> <!-- End of card-body -->
                </div> <!-- End of card -->
            </div> <!-- End of col-md-10 -->
        </div> <!-- End of row -->
    </div> <!-- End of container -->

    <!-- Script untuk toggle password visibility -->
    <script src="//cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>

