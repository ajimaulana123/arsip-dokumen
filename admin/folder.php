<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
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

// Fungsi untuk menambahkan folder
function tambahFolder($koneksi) {
    global $error_message, $success_message;
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $nama_folder = mysqli_real_escape_string($koneksi, trim($_POST['nama_folder']));
        $deskripsi = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi']));
        $created_by = $_SESSION['username'];

        if (empty($nama_folder)) {
            $error_message = "Nama folder harus diisi!";
            return;
        }

        // Cek apakah folder sudah ada di database
        $check_query = "SELECT folder_name FROM folders WHERE folder_name = ?";
        $check_stmt = mysqli_prepare($koneksi, $check_query);
        
        if (!$check_stmt) {
            $error_message = "Error dalam prepared statement: " . mysqli_error($koneksi);
            return;
        }

        mysqli_stmt_bind_param($check_stmt, "s", $nama_folder);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);
        
        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $error_message = "Folder dengan nama tersebut sudah ada!";
            mysqli_stmt_close($check_stmt);
            return;
        }
        mysqli_stmt_close($check_stmt);

        // Buat folder fisik
        $folder_path = UPLOAD_DIR . $nama_folder;
        if (!file_exists($folder_path)) {
            if (!mkdir($folder_path, 0777, true)) {
                $error_message = "Gagal membuat folder fisik!";
                return;
            }
        }

        // Insert folder baru ke database
        $query = "INSERT INTO folders (folder_name, description, created_by, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = mysqli_prepare($koneksi, $query);
        
        if (!$stmt) {
            // Hapus folder fisik jika gagal insert ke database
            rmdir($folder_path);
            $error_message = "Error dalam prepared statement: " . mysqli_error($koneksi);
            return;
        }

        mysqli_stmt_bind_param($stmt, "sss", $nama_folder, $deskripsi, $created_by);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Folder berhasil ditambahkan!";
            header("Location: folder.php");
            exit();
        } else {
            // Hapus folder fisik jika gagal insert ke database
            rmdir($folder_path);
            $error_message = "Gagal menambahkan folder: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }
}

// Fungsi untuk mengedit folder
function editFolder($koneksi, $id) {
    global $error_message, $success_message;
    
    // Ambil nama folder lama
    $stmt_old = mysqli_prepare($koneksi, "SELECT folder_name FROM folders WHERE folder_id = ?");
    mysqli_stmt_bind_param($stmt_old, "i", $id);
    mysqli_stmt_execute($stmt_old);
    $result_old = mysqli_stmt_get_result($stmt_old);
    $old_data = mysqli_fetch_assoc($result_old);
    $old_folder_name = $old_data['folder_name'];
    mysqli_stmt_close($stmt_old);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $nama_folder_post = mysqli_real_escape_string($koneksi, trim($_POST['nama_folder']));
        $deskripsi_post = mysqli_real_escape_string($koneksi, trim($_POST['deskripsi']));

        if (empty($nama_folder_post)) {
            $error_message = "Nama folder harus diisi!";
            return;
        }

        // Rename folder fisik
        $old_path = UPLOAD_DIR . $old_folder_name;
        $new_path = UPLOAD_DIR . $nama_folder_post;
        
        if (file_exists($old_path) && $old_folder_name !== $nama_folder_post) {
            if (!rename($old_path, $new_path)) {
                $error_message = "Gagal mengubah nama folder fisik!";
                return;
            }
        }

        // Update database
        $stmt = mysqli_prepare($koneksi, "UPDATE folders SET folder_name = ?, description = ? WHERE folder_id = ?");
        
        if (!$stmt) {
            // Kembalikan nama folder jika gagal update database
            if (file_exists($new_path)) {
                rename($new_path, $old_path);
            }
            $error_message = "Error dalam prepared statement: " . mysqli_error($koneksi);
            return;
        }

        mysqli_stmt_bind_param($stmt, "ssi", $nama_folder_post, $deskripsi_post, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Folder berhasil diubah!";
            header("Location: folder.php");
            exit();
        } else {
            // Kembalikan nama folder jika gagal update database
            if (file_exists($new_path)) {
                rename($new_path, $old_path);
            }
            $error_message = "Gagal mengubah folder: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }

    // Query untuk mengambil data folder yang akan diedit
    $stmt_select = mysqli_prepare($koneksi, "SELECT folder_name as nama_folder, description as deskripsi FROM folders WHERE folder_id = ?");
    
    if (!$stmt_select) {
        $error_message = "Error dalam prepared statement: " . mysqli_error($koneksi);
        return;
    }

    mysqli_stmt_bind_param($stmt_select, "i", $id);
    mysqli_stmt_execute($stmt_select);
    $result_select = mysqli_stmt_get_result($stmt_select);
    $folder_data = mysqli_fetch_assoc($result_select);
    mysqli_stmt_close($stmt_select);

    if (!$folder_data) {
        echo "Folder tidak ditemukan.";
        return;
    }
    ?>
    <h2>Edit Folder</h2>
    <form method="POST">
        <div class="mb-3">
            <label for="nama_folder" class="form-label">Nama Folder</label>
            <input type="text" class="form-control" name="nama_folder" id="nama_folder" value="<?php echo htmlspecialchars($folder_data['nama_folder']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="deskripsi" class="form-label">Deskripsi</label>
            <textarea class="form-control" name="deskripsi" id="deskripsi"><?php echo htmlspecialchars($folder_data['deskripsi']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="folder.php" class="btn btn-secondary">Batal</a>
    </form>
    <?php
}

// Fungsi untuk menghapus folder
function hapusFolder($koneksi, $id) {
    global $error_message, $success_message;
    
    // Cek apakah folder memiliki file
    $check_files = mysqli_prepare($koneksi, "SELECT COUNT(*) as file_count FROM files WHERE folder_id = ?");
    mysqli_stmt_bind_param($check_files, "i", $id);
    mysqli_stmt_execute($check_files);
    $result = mysqli_stmt_get_result($check_files);
    $file_count = mysqli_fetch_assoc($result)['file_count'];
    mysqli_stmt_close($check_files);

    if ($file_count > 0) {
        $error_message = "Folder tidak dapat dihapus karena masih berisi file. Harap hapus semua file terlebih dahulu.";
        return;
    }
    
    // Jika tidak ada file, lanjutkan proses penghapusan
    $stmt_select = mysqli_prepare($koneksi, "SELECT folder_name FROM folders WHERE folder_id = ?");
    mysqli_stmt_bind_param($stmt_select, "i", $id);
    mysqli_stmt_execute($stmt_select);
    $result = mysqli_stmt_get_result($stmt_select);
    $folder_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_select);

    if ($folder_data) {
        $folder_path = UPLOAD_DIR . $folder_data['folder_name'];
        
        // Hapus folder fisik
        if (file_exists($folder_path)) {
            if (!rmdir($folder_path)) {
                $error_message = "Gagal menghapus folder fisik!";
                return;
            }
        }

        // Hapus dari database
        $stmt = mysqli_prepare($koneksi, "DELETE FROM folders WHERE folder_id = ?");
        
        if (!$stmt) {
            $error_message = "Error dalam prepared statement: " . mysqli_error($koneksi);
            return;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "Folder berhasil dihapus!";
            header("Location: folder.php");
            exit();
        } else {
            $error_message = "Gagal menghapus folder!";
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "Folder tidak ditemukan!";
    }
}

// Menangani aksi berdasarkan parameter GET
switch ($action) {
    case 'tambah':
        tambahFolder($koneksi);
        break;
    case 'edit':
        editFolder($koneksi, $id);
        break;
    case 'hapus':
        hapusFolder($koneksi, $id);
        break;
}

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

        .folder-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .folder-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }

        .folder-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .folder-content {
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .folder-icon {
            font-size: 2.5rem;
            color: #ffd700;
        }

        .folder-info {
            flex: 1;
        }

        .folder-name {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .folder-description {
            font-size: 0.9rem;
            color: #666;
        }

        .folder-actions {
            padding: 10px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 5px;
        }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            padding: 15px;
        }

        .file-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .file-item:hover {
            transform: translateY(-5px);
        }

        .file-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            cursor: pointer;
        }

        .file-info {
            text-align: center;
        }

        .file-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .file-type {
            color: #666;
            font-size: 0.9rem;
        }

        #filePreviewContainer {
            position: relative;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .zoom-controls {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1060;
            background: rgba(0,0,0,0.7);
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            gap: 15px;
        }

        .zoom-controls button {
            background: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .zoom-controls button:hover {
            background: #e9ecef;
            transform: scale(1.1);
        }

        .no-folders {
            grid-column: 1 / -1;
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .download-btn {
            background: #28a745 !important; /* Warna hijau untuk tombol download */
            color: white !important;
        }
        
        .download-btn:hover {
            background: #218838 !important;
            color: white !important;
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
                            <a href="users.php" class="nav-link"><i class="bi bi-person me-2"></i> Users</a>
                        </li>
                        <li class="nav-item">
                            <a href="folder.php" class="nav-link active"><i class="bi bi-folder me-2"></i> Folder</a>
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
                    
                        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#tambahFolderModal">
                            <i class="fas fa-plus me-2"></i> Tambah Folder
                        </button>
                    
                        <form method="POST" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Cari folder..." value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>">
                            <button type="submit" class="btn btn-secondary ms-2">Cari</button>
                        </form>
                
                </div>

                <div class="folder-container">
                    <?php
                    if ($result_folders && mysqli_num_rows($result_folders) > 0) {
                        while ($row = mysqli_fetch_assoc($result_folders)) {
                            ?>
                            <div class="folder-card" data-folder-id="<?php echo htmlspecialchars($row['folder_id']); ?>">
                                <div class="folder-content" onclick="showFolderFiles(<?php echo htmlspecialchars($row['folder_id']); ?>, '<?php echo htmlspecialchars($row['nama_folder']); ?>')">
                                    <div class="folder-icon">
                                        <i class="bi bi-folder-fill"></i>
                                    </div>
                                    <div class="folder-info">
                                        <div class="folder-name"><?php echo htmlspecialchars($row['nama_folder']); ?></div>
                                        <div class="folder-description"><?php echo htmlspecialchars($row['deskripsi']); ?></div>
                                    </div>
                                </div>
                                <div class="folder-actions">
                                    <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editFolderModal"
                                        data-id="<?php echo htmlspecialchars($row['folder_id']); ?>"
                                        data-nama="<?php echo htmlspecialchars($row['nama_folder']); ?>"
                                        data-deskripsi="<?php echo htmlspecialchars($row['deskripsi']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusFolderModal"
                                        data-id="<?php echo htmlspecialchars($row['folder_id']); ?>"
                                        data-nama="<?php echo htmlspecialchars($row['nama_folder']); ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='no-folders'>Tidak ada folder.</div>";
                    }
                    ?>
                </div>
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

    <!-- Modal Edit -->
    <div class="modal fade" id="editFolderModal" tabindex="-1" aria-labelledby="editFolderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFolderModalLabel">Edit Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="editFolderForm">
                        <div class="mb-3">
                            <label for="edit_nama_folder" class="form-label">Nama Folder</label>
                            <input type="text" class="form-control" name="nama_folder" id="edit_nama_folder" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" name="deskripsi" id="edit_deskripsi"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="hapusFolderModal" tabindex="-1" aria-labelledby="hapusFolderModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="hapusFolderModalLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus folder "<span id="nama_folder_hapus"></span>"?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="#" class="btn btn-danger" id="btn_hapus">Hapus</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Tambahkan Modal untuk menampilkan file dalam folder -->
    <div class="modal fade" id="folderFilesModal" tabindex="-1" aria-labelledby="folderFilesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="folderFilesModalLabel">Files in Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="filesList" class="files-grid">
                        <!-- Files will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tambahkan Modal Preview File setelah folderFilesModal -->
    <div class="modal fade" id="filePreviewModal" tabindex="-1" aria-labelledby="filePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="filePreviewModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="filePreviewContainer" class="text-center">
                        <!-- Preview content will be loaded here -->
                    </div>
                    <div class="zoom-controls">
                        <button type="button" id="zoomIn" title="Zoom In" onclick="handleZoom('in')">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                        <button type="button" id="zoomOut" title="Zoom Out" onclick="handleZoom('out')">
                            <i class="bi bi-dash-lg"></i>
                        </button>
                        <button type="button" id="zoomReset" title="Reset Zoom" onclick="handleZoom('reset')">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>
                        <button type="button" id="downloadBtn" title="Download" class="download-btn">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/panzoom@9.4.0/dist/panzoom.min.js"></script>

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

        document.addEventListener('DOMContentLoaded', function() {
            const editFolderModal = document.getElementById('editFolderModal');
            editFolderModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const nama = button.getAttribute('data-nama');
                const deskripsi = button.getAttribute('data-deskripsi');
                
                const modalForm = this.querySelector('#editFolderForm');
                modalForm.action = `?action=edit&id=${id}`;
                modalForm.querySelector('#edit_nama_folder').value = nama;
                modalForm.querySelector('#edit_deskripsi').value = deskripsi;
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const hapusFolderModal = document.getElementById('hapusFolderModal');
            hapusFolderModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const nama = button.getAttribute('data-nama');
                
                this.querySelector('#nama_folder_hapus').textContent = nama;
                this.querySelector('#btn_hapus').href = `?action=hapus&id=${id}`;
            });
        });

        function showFolderFiles(folderId, folderName) {
            const modal = new bootstrap.Modal(document.getElementById('folderFilesModal'));
            const modalTitle = document.getElementById('folderFilesModalLabel');
            const filesList = document.getElementById('filesList');
            
            modalTitle.textContent = `Files in ${folderName}`;
            filesList.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
            
            fetch(`get_folder_files.php?folder_id=${folderId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        filesList.innerHTML = '<div class="text-center">No files in this folder</div>';
                        return;
                    }

                    filesList.innerHTML = data.map(file => `
                        <div class="file-item">
                            <div class="file-icon" onclick="showFilePreview('${file.file_path}', '${file.document_number}', ${file.is_image})">
                                <i class="bi bi-file-earmark-text text-primary"></i>
                            </div>
                            <div class="file-info">
                                <div class="file-name">${file.document_number}</div>
                                <div class="file-type">${file.document_type}</div>
                                <button class="btn btn-sm btn-primary mt-2" onclick="showFilePreview('${file.file_path}', '${file.document_number}', ${file.is_image})">
                                    <i class="bi bi-eye"></i> View
                                </button>
                            </div>
                        </div>
                    `).join('');
                })
                .catch(error => {
                    filesList.innerHTML = '<div class="text-center text-danger">Error loading files</div>';
                    console.error('Error:', error);
                });
            
            modal.show();
        }

        // Tambahkan fungsi untuk menampilkan preview file
        function showFilePreview(filePath, fileName, isImage) {
            const modal = new bootstrap.Modal(document.getElementById('filePreviewModal'));
            const modalTitle = document.getElementById('filePreviewModalLabel');
            const previewContainer = document.getElementById('filePreviewContainer');
            const downloadBtn = document.getElementById('downloadBtn');
            
            modalTitle.textContent = fileName;
            
            // Setup download button
            downloadBtn.onclick = () => {
                const link = document.createElement('a');
                link.href = filePath;
                link.download = fileName;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            };
            
            if (isImage) {
                previewContainer.innerHTML = `<img id="previewImage" src="${filePath}" alt="${fileName}" style="max-width: 100%; max-height: 70vh;">`;
                
                // Initialize panzoom after image loads
                const img = previewContainer.querySelector('#previewImage');
                img.onload = function() {
                    if (window.panzoomInstance) {
                        window.panzoomInstance.dispose();
                    }
                    
                    window.panzoomInstance = panzoom(img, {
                        maxZoom: 4,
                        minZoom: 0.5,
                        bounds: true,
                        boundsPadding: 0.5,
                        transformOrigin: {x: 0.5, y: 0.5}
                    });
                    
                    // Reset zoom
                    window.panzoomInstance.moveTo(0, 0);
                    window.panzoomInstance.zoomAbs(0, 0, 1);
                };
                
                // Show zoom controls and download button
                document.querySelector('.zoom-controls').style.display = 'flex';
            } else {
                previewContainer.innerHTML = `
                    <div class="text-center">
                        <i class="bi bi-file-earmark-text display-1 text-primary"></i>
                        <p class="mt-3">This file type cannot be previewed</p>
                        <a href="${filePath}" class="btn btn-primary" target="_blank">Download File</a>
                    </div>
                `;
                
                // Hide zoom controls but keep download button
                document.querySelector('.zoom-controls').style.display = 'none';
            }
            
            modal.show();
        }

        function handleZoom(action) {
            if (!window.panzoomInstance) return;
            
            const ZOOM_SPEED = 0.2;
            const currentZoom = window.panzoomInstance.getTransform().scale;
            
            switch(action) {
                case 'in':
                    const newZoomIn = currentZoom + ZOOM_SPEED;
                    if (newZoomIn <= 4) {
                        window.panzoomInstance.zoomAbs(0, 0, newZoomIn);
                    }
                    break;
                case 'out':
                    const newZoomOut = currentZoom - ZOOM_SPEED;
                    if (newZoomOut >= 0.5) {
                        window.panzoomInstance.zoomAbs(0, 0, newZoomOut);
                    }
                    break;
                case 'reset':
                    window.panzoomInstance.moveTo(0, 0);
                    window.panzoomInstance.zoomAbs(0, 0, 1);
                    break;
            }
        }

        // Cleanup panzoom when modal is closed
        document.getElementById('filePreviewModal').addEventListener('hidden.bs.modal', function() {
            if (window.panzoomInstance) {
                window.panzoomInstance.dispose();
                window.panzoomInstance = null;
            }
        });
    </script>

</body>

</html>