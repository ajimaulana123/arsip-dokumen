<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
  header("Location: ../login.php");
  exit();
}

include '../includes/db.php';
if (!$koneksi) {
  die("Koneksi database GAGAL: " . mysqli_connect_error());
}

// Mengubah query untuk menampilkan hanya user yang memiliki role 'user'
$sql = "SELECT id, username, name, nik, jabatan, role FROM users WHERE role = 'user'";
$result = mysqli_query($koneksi, $sql);

if (!$result) {
  die("Error dalam query: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body {
            background-color: #1e273a;
            color: white;
        }
        .sidebar {
            background-color: #283149;
            min-height: 100vh;
            padding-top: 20px;
        }
        .sidebar .nav-link {
          color: #adb5bd;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        table {
            background-color: #343a40;
        }
        th, td {
            border-color: #495057;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 sidebar">
                <a class="navbar-brand ms-3" href="#">
                    <span class="fs-4 fw-bold">ARSIP</span>
                </a>
                <ul class="nav flex-column mt-4">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php"><i class="fas fa-users me-2"></i> User</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="file_manager.php"><i class="fas fa-file me-2"></i> File Manager</a>
                    </li>
                    <li class="nav-item mt-auto mb-3">
                        <a class="nav-link logout text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                    </li>
                </ul>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                    <h1 class="h2">Data User</h1>
                </div>
                <a href="tambah_user.php" class="btn btn-primary mb-3">Tambah User</a>
                <div class="table-responsive">
                    <table class="table table-dark table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>NIK</th>
                                <th>Jabatan</th>
                                <th>Role</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;

                            if ($result && mysqli_num_rows($result) > 0) {
                              while ($row = mysqli_fetch_assoc($result)) {
                                echo "<tr>";
                                echo "<td>" . $no . "</td>";
                                echo "<td>" . $row['username'] . "</td>";
                                echo "<td>" . $row['name'] . "</td>";
                                echo "<td>" . $row['nik'] . "</td>";
                                echo "<td>" . $row['jabatan'] . "</td>";
                                echo "<td>" . $row['role'] . "</td>";
                                echo "<td>";
                                echo "<a href='edit_user.php?id=" . $row['id'] . "' class='btn btn-sm btn-warning me-1'>Edit</a>";
                                echo "<a href='hapus_user.php?id=" . $row['id'] . "' class='btn btn-sm btn-danger' onclick=\"return confirm('Apakah Anda yakin ingin menghapus user ini?')\">Hapus</a>";
                                echo "</td>";
                                echo "</tr>";
                                $no++;
                              }
                            } else {
                              echo "<tr><td colspan='7' class='text-center'>Tidak ada data user.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
</body>
</html>
