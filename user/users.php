<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: ../index.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/db.php';

// Periksa koneksi ke database
if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Logika pencarian
$search = '';
if (isset($_GET['search'])) {
    $search = htmlspecialchars($_GET['search']);
}

// Ambil data dari tabel users dengan pencarian jika ada
// Ambil data pencarian
$searchQuery = '';
if (isset($_GET['search'])) {
    $searchQuery = mysqli_real_escape_string($koneksi, $_GET['search']);
}

// Query pencarian
if (!empty($searchQuery)) {
    $query = "SELECT * FROM users 
              WHERE username LIKE '%$searchQuery%' 
              OR name LIKE '%$searchQuery%' 
              OR nik LIKE '%$searchQuery%' 
              OR jabatan LIKE '%$searchQuery%' 
              OR role LIKE '%$searchQuery%'";
} else {
    $query = "SELECT * FROM users";
}
$result = mysqli_query($koneksi, $query);

if (!$result) {
    die("Query gagal: " . mysqli_error($koneksi));
}

// Tambahkan di bagian awal file, setelah include db.php
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .sidebar {
            height: 100vh;
            background-color: rgb(32, 38, 44);
            color: white;
            padding-top: 20px;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            border-radius: 5px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .sidebar .nav-link {
            color: #adb5bd;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        .content {
            padding: 20px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }

        .table tbody tr:hover {
            background-color: #d1ecf1;
            transition: background-color 0.3s ease;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                height: auto;
                width: 100%;
                display: block;
            }

            .content {
                padding-left: 15px;
                padding-right: 15px;
            }
        }

        /* Styling untuk pesan alert */
        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
            transition: opacity 0.5s ease-out;
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .user-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .user-avatar {
            text-align: center;
            margin-bottom: 15px;
        }

        .user-avatar i {
            font-size: 3rem;
            color: #007bff;
        }

        .user-info {
            text-align: center;
            margin-bottom: 15px;
        }

        .user-name {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
            color: #333;
        }

        .user-details {
            text-align: left;
            padding: 0 10px;
        }

        .user-details p {
            margin: 5px 0;
            font-size: 0.9rem;
            color: #666;
            display: flex;
            align-items: center;
        }

        .user-details i {
            width: 20px;
            color: #007bff;
        }

        .user-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .no-users {
            grid-column: 1 / -1;
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #666;
        }

        /* Responsif untuk mobile */
        @media (max-width: 576px) {
            .users-grid {
                grid-template-columns: 1fr;
            }

            .user-card {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar" id="sidebar">
                <div class="position-sticky">
                    <h3 class="text-center">ARSIP</h3>
                    <ul class="nav flex-column mt-4">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link"><i class="bi bi-house-door me-2"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a href="users.php" class="nav-link active"><i class="bi bi-person me-2"></i> Users</a>
                        </li>
                        <li class="nav-item">
                            <a href="folder.php" class="nav-link"><i class="bi bi-folder me-2"></i> Folder</a>
                        </li>
                        <li class="nav-item">
                            <a href="file.php" class="nav-link"><i class="bi bi-file-earmark-text me-2"></i> File</a>
                        </li>
                    </ul>
                    <div class="mt-4">
                        <a href="../logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-message" role="alert">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-message" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                    <h1 class="h2">Data User</h1>
                </div>
                <div class="d-flex mb-3">
                    <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#tambahUserModal">
                        <i class="bi bi-person-plus-fill me-2"></i> Tambah User
                    </button>
                    <form action="" method="GET" class="d-flex">
                        <input type="text" name="search" class="form-control" placeholder="Cari user..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="btn btn-secondary ms-2">Cari</button>
                    </form>
                </div>

                <div class="users-grid">
                    <?php
                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                            <div class="user-card">
                                <div class="user-avatar">
                                    <i class="bi bi-person-circle"></i>
                                </div>
                                <div class="user-info">
                                    <h5 class="user-name"><?php echo htmlspecialchars($row['name']); ?></h5>
                                    <div class="user-details">
                                        <p><i class="bi bi-person-badge me-2"></i><?php echo htmlspecialchars($row['username']); ?></p>
                                        <p><i class="bi bi-credit-card me-2"></i><?php echo htmlspecialchars($row['nik']); ?></p>
                                        <p><i class="bi bi-briefcase me-2"></i><?php echo htmlspecialchars($row['jabatan']); ?></p>
                                        <p><i class="bi bi-shield-lock me-2"></i><?php echo htmlspecialchars($row['role']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='no-users'>Tidak ada data user.</div>";
                    }
                    ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Tambah User -->
    <div class="modal fade" id="tambahUserModal" tabindex="-1" aria-labelledby="tambahUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahUserModalLabel">Tambah User Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="tambah_user.php">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="nik" class="form-label">NIK</label>
                            <input type="text" class="form-control" name="nik" required>
                        </div>
                        <div class="mb-3">
                            <label for="jabatan" class="form-label">Jabatan</label>
                            <input type="text" class="form-control" name="jabatan" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editUserForm">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" name="name" id="edit_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_nik" class="form-label">NIK</label>
                            <input type="text" class="form-control" name="nik" id="edit_nik" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_jabatan" class="form-label">Jabatan</label>
                            <input type="text" class="form-control" name="jabatan" id="edit_jabatan" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Role</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                        <!-- Tambahkan hidden input untuk password fields -->
                        <input type="hidden" name="old_password" value="">
                        <input type="hidden" name="new_password" value="">
                        <input type="hidden" name="confirm_password" value="">
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="hapusUserModal" tabindex="-1" aria-labelledby="hapusUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hapusUserModalLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus user "<span id="nama_user_hapus"></span>"?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" class="btn btn-danger" id="btn_hapus">Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Script untuk mengisi data modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Script untuk modal edit
            const editUserModal = document.getElementById('editUserModal');
            editUserModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const username = button.getAttribute('data-username');
                const name = button.getAttribute('data-name');
                const nik = button.getAttribute('data-nik');
                const jabatan = button.getAttribute('data-jabatan');
                const role = button.getAttribute('data-role');
                
                const modalForm = this.querySelector('#editUserForm');
                modalForm.action = `edit_user.php?id=${id}`;
                modalForm.querySelector('#edit_username').value = username;
                modalForm.querySelector('#edit_name').value = name;
                modalForm.querySelector('#edit_nik').value = nik;
                modalForm.querySelector('#edit_jabatan').value = jabatan;
                modalForm.querySelector('#edit_role').value = role;
            });

            // Script untuk modal hapus
            const hapusUserModal = document.getElementById('hapusUserModal');
            hapusUserModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const name = button.getAttribute('data-name');
                
                this.querySelector('#nama_user_hapus').textContent = name;
                this.querySelector('#btn_hapus').href = `hapus_user.php?id=${id}`;
            });
        });

        // Auto-hide the alert message after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-message');
            alerts.forEach(alert => {
                alert.style.opacity = 0;
                setTimeout(() => {
                    alert.remove();
                }, 500); // Waktu transisi opacity
            });
        }, 5000);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
