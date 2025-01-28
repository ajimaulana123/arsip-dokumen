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

$error_message = "";
$success_message = "";
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

// Di awal file, tambahkan konstanta untuk path upload
define('UPLOAD_DIR', '../uploads/');

// Mengambil daftar folder dari database dengan fitur pencarian
$sql_folders = "SELECT folder_id, folder_name as nama_folder, description as deskripsi FROM folders";

// Tambahkan logika pencarian
if (isset($_POST['search']) && !empty($_POST['search'])) {
    $search = mysqli_real_escape_string($koneksi, $_POST['search']);
    $sql_folders .= " WHERE folder_name LIKE '%$search%' OR description LIKE '%$search%'";
}

$result_folders = mysqli_query($koneksi, $sql_folders);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Folder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Styling untuk sidebar */
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

        /* Styling untuk pesan alert */
        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
            transition: opacity 0.5s ease-out;
        }

        /* Styling untuk tabel (PERUBAHAN UTAMA) */
        .table-container { /* Kontainer untuk tabel agar responsif */
            width: 100%;
            border-collapse: collapse;
        }

        .table thead th {
            background-color: #007bff; /* Warna hijau untuk header tabel */
            color: white; /* Warna teks putih agar kontras */
            text-align: center; /* Teks di tengah header */
            border: 1px solid #dee2e6; /* Border pada header */
        }
        .table tbody td {
            border: 1px solid #dee2e6;
        }
        .table {
          border-collapse: collapse; /* Menggabungkan border tabel */
        }

        .btn-action {
            display: flex;
            justify-content: center;
            gap: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar" id="sidebar">
                <div class="position-sticky">
                    <h3 class="text-center">ARSIP</h3>
                    <ul class="nav flex-column mt-4">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link"><i class="bi bi-house-door me-2"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a href="Users.php" class="nav-link"><i class="bi bi-person me-2"></i> Users</a>
                        </li>
                        <li class="nav-item">
                            <a href="Folder.php" class="nav-link active"><i class="bi bi-folder me-2"></i> Folder</a>
                        </li>
                        <li class="nav-item">
                            <a href="File.php" class="nav-link"><i class="bi bi-file-earmark-text me-2"></i> File</a>
                        </li>
                    </ul>
                    <div class="mt-4">
                        <a href="../logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                    </div>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h2>Data Folder</h2>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-message"><?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-message"><?php echo htmlspecialchars($success_message); ?></div>
                <?php endif; ?>

                <!-- Tambahkan form pencarian -->
                <div class="d-flex mb-3">
                        <form method="POST" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Cari folder..." value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>">
                            <button type="submit" class="btn btn-secondary ms-2">Cari</button>
                        </form>
                
                </div>

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Folder</th>
                            <th>Deskripsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result_folders && mysqli_num_rows($result_folders) > 0) {
                            $no = 1;
                            while ($row = mysqli_fetch_assoc($result_folders)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($no++) . "</td>";
                                echo "<td>" . htmlspecialchars($row['nama_folder']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['deskripsi']) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' class='text-center'>Tidak ada data folder.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </main>
        </div>
    </div>

    <div class="modal fade" id="tambahFolderModal" tabindex="-1" aria-labelledby="tambahFolderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tambahFolderModalLabel">Tambah Folder Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="?action=tambah">
                        <div class="mb-3">
                            <label for="nama_folder" class="form-label">Nama Folder</label>
                            <input type="text" class="form-control" name="nama_folder" id="nama_folder" required>
                        </div>
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" id="deskripsi"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
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

</body>

</html>