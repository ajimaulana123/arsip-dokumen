<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $name = trim($_POST['name']);
    $nik = trim($_POST['nik']);
    $jabatan = trim($_POST['jabatan']);
    $role = trim($_POST['role']);

    $error_message = "";

    if (empty($username) || empty($password) || empty($name) || empty($nik) || empty($jabatan) || empty($role)) {
        $error_message .= "Semua field harus diisi!<br>";
    }

    if (strlen($username) < 4) {
        $error_message .= "Username minimal 4 karakter!<br>";
    }

    if (strlen($password) < 4) {
        $error_message .= "Password minimal 4 karakter!<br>";
    }

    if (!ctype_digit($nik)) {
        $error_message .= "NIK harus berupa angka!<br>";
    }

    if (strlen($nik) != 14) {
        $error_message .= "NIK harus 14 digit!<br>";
    }

    $check_username_query = "SELECT username FROM users WHERE username = ?";
    $check_username_stmt = mysqli_prepare($koneksi, $check_username_query);
    mysqli_stmt_bind_param($check_username_stmt, "s", $username);
    mysqli_stmt_execute($check_username_stmt);
    mysqli_stmt_store_result($check_username_stmt);
    if (mysqli_stmt_num_rows($check_username_stmt) > 0) {
        $error_message .= "Username sudah terdaftar!<br>";
    }
    mysqli_stmt_close($check_username_stmt);


    if (empty($error_message)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $insert_query = "INSERT INTO users (username, password, name, nik, jabatan, role) VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = mysqli_prepare($koneksi, $insert_query);
        mysqli_stmt_bind_param($insert_stmt, "ssssss", $username, $hashed_password, $name, $nik, $jabatan, $role);

        if (mysqli_stmt_execute($insert_stmt)) {
            $success_message = "User berhasil ditambahkan!";
        } else {
            $error_message = "Terjadi kesalahan: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($insert_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body {
            background-color: #1e273a;
            color: white;
        }
        .card {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8"> <div class="card bg-dark text-white">
                    <div class="card-header">
                        <h1 class="h5">Tambah User</h1>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="row"> <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control form-control-sm" name="username" id="username" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" class="form-control form-control-sm" name="password" id="password" required>
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
                                            <option value="user">User</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Tambah</button>
                            <a href="users.php" class="btn btn-secondary btn-sm">Batal</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="successModal" ...></div> <script>
        <?php if (!empty($success_message)): ?>
            var myModal = new bootstrap.Modal(document.getElementById('successModal'));
            myModal.show();
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            alert("<?php echo str_replace(array("\r", "\n"), '', $error_message); ?>");
        <?php endif; ?>
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>