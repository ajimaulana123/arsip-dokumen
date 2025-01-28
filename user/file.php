<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../includes/db.php';

// Tambahkan di awal file setelah include db.php
define('UPLOAD_DIR', '../uploads/');

// Pastikan folder uploads ada dan bisa ditulis
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

if (!$koneksi) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Ubah query untuk mengambil data files dengan fitur pencarian
$sql_files = "SELECT f.file_id, f.document_number, f.document_type, 
              f.upload_date, f.file_path, f.description, 
              fo.folder_name 
              FROM files f 
              LEFT JOIN folders fo ON f.folder_id = fo.folder_id";

// Tambahkan logika pencarian
if (isset($_POST['search']) && !empty($_POST['search'])) {
    $search = mysqli_real_escape_string($koneksi, $_POST['search']);
    $sql_files .= " WHERE f.document_number LIKE '%$search%' 
                    OR f.document_type LIKE '%$search%' 
                    OR f.description LIKE '%$search%'
                    OR fo.folder_name LIKE '%$search%'";
}

$result_files = mysqli_query($koneksi, $sql_files);

// Ambil daftar folder dengan error handling
$sql_folders = "SELECT folder_id, folder_name FROM folders";
$result_folders = mysqli_query($koneksi, $sql_folders);

if (!$result_folders) {
    die("Error mengambil data folder: " . mysqli_error($koneksi));
}

$folders = [];
while ($folder = mysqli_fetch_assoc($result_folders)) {
    $folders[$folder['folder_id']] = $folder['folder_name'];
}

// Pesan error dan sukses
$error_message = "";
$success_message = "";

// Tambahkan ini
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data File</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .sidebar a:hover,
        .sidebar a.active {
            background-color: #0056b3;
            transform: scale(1.05);
        }

        .content {
            padding: 20px;
        }

        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            min-width: 300px;
            transition: opacity 0.5s ease-out;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .table-margin {
            margin-top: 30px;
        }

        .btn-action {
            display: flex;
            gap: 5px;
        }
    </style>
</head>

<body>
<div class="container-fluid">
    <div class="row">
        <nav class="col-md-3 col-lg-2 d-md-block sidebar">
            <div class="position-sticky">
                <h3 class="text-center">ARSIP</h3>
                <ul class="nav flex-column mt-4">
                    <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="bi bi-house-door me-2"></i> Dashboard</a></li>
                    <li class="nav-item"><a href="Users.php" class="nav-link"><i class="bi bi-person me-2"></i> Users</a></li>
                    <li class="nav-item"><a href="Folder.php" class="nav-link"><i class="bi bi-folder me-2"></i> Folder</a></li>
                    <li class="nav-item"><a href="File.php" class="nav-link active"><i class="bi bi-file-earmark-text me-2"></i> File</a></li>
                </ul>
                <div class="mt-4"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></div>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h2>Data File</h2>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-message">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <div class="d-flex align-items-center">
                <form method="POST" class="d-flex">
                    <input type="text" name="search" class="form-control me-2" placeholder="Cari file..." 
                           value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>">
                    <button type="submit" class="btn btn-secondary">Cari</button>
                </form>
            </div>

            <div class="table-responsive table-margin">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th>No</th>
                        <th>Nomor Dokumen</th>
                        <th>Tipe Dokumen</th>
                        <th>Tanggal Upload</th>
                        <th>Folder</th>
                        <th>Deskripsi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($result_files && mysqli_num_rows($result_files) > 0) {
                        $no = 1;
                        while ($row = mysqli_fetch_assoc($result_files)) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($no++) . "</td>";
                            echo "<td>" . htmlspecialchars($row['document_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['document_type']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['upload_date']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['folder_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7' class='text-center'>Tidak ada data file.</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<!-- Modal Tambah File -->
<div class="modal fade" id="tambahFileModal" tabindex="-1" aria-labelledby="tambahFileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tambahFileModalLabel">Tambah File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="?action=tambah" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="folder_id" class="form-label">Folder</label>
                        <select name="folder_id" id="folder_id" class="form-select" required>
                            <?php
                            foreach ($folders as $id => $nama_folder) {
                                echo "<option value='" . htmlspecialchars($id) . "'>" . htmlspecialchars($nama_folder) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="document_number" class="form-label">Nomor Dokumen</label>
                        <input type="text" name="document_number" id="document_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="document_type" class="form-label">Tipe Dokumen</label>
                        <input type="text" name="document_type" id="document_type" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea name="description" id="description" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="scanned_image" class="form-label">Upload File</label>
                        <input type="file" name="scanned_image" id="scanned_image" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
