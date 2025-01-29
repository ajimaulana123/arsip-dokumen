<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
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

// Ambil dan hapus pesan session
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Ubah query untuk mengambil data files dengan fitur pencarian
$sql_files = "SELECT f.file_id, f.folder_id, f.document_number, f.document_type, 
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

// Simpan data folder dalam array untuk digunakan di beberapa tempat
$folders = array();
while ($folder = mysqli_fetch_assoc($result_folders)) {
    $folders[] = array(
        'folder_id' => $folder['folder_id'],
        'folder_name' => $folder['folder_name']
    );
}
mysqli_free_result($result_folders);

// Hapus variabel yang tidak digunakan lagi
unset($result_folders);

// Tambahkan ini
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

// Fungsi untuk menambah file
function tambahFile($koneksi)
{
    global $error_message, $success_message;

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        error_log("Debug - POST data: " . print_r($_POST, true));
        error_log("Debug - FILES data: " . print_r($_FILES, true));
        
        // Validasi input
        if (empty($_POST['folder_id']) || empty($_POST['document_number']) || empty($_POST['document_type']) || empty($_FILES['scanned_image'])) {
            $error_message = "Semua field harus diisi!";
            return;
        }

        $folder_id = (int)$_POST['folder_id'];
        $document_number = $_POST['document_number'];
        $document_type = $_POST['document_type'];
        $description = $_POST['description'];
        $upload_date = date('Y-m-d');

        // Ambil nama folder untuk menyimpan file
        $folder_query = "SELECT folder_name FROM folders WHERE folder_id = ?";
        $folder_stmt = mysqli_prepare($koneksi, $folder_query);
        mysqli_stmt_bind_param($folder_stmt, "i", $folder_id);
        mysqli_stmt_execute($folder_stmt);
        $folder_result = mysqli_stmt_get_result($folder_stmt);
        $folder_data = mysqli_fetch_assoc($folder_result);
        $folder_name = $folder_data['folder_name'];
        mysqli_stmt_close($folder_stmt);

        // Validasi file upload
        if ($_FILES['scanned_image']['error'] !== UPLOAD_ERR_OK) {
            $error_message = "Error dalam upload file: " . $_FILES['scanned_image']['error'];
            return;
        }

        // Validasi tipe file
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $file_type = mime_content_type($_FILES['scanned_image']['tmp_name']);
        if (!in_array($file_type, $allowed_types)) {
            $error_message = "Tipe file tidak diizinkan. Hanya JPG, PNG, GIF, dan PDF yang diperbolehkan.";
            return;
        }

        // Validasi ukuran file
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($_FILES['scanned_image']['size'] > $max_size) {
            $error_message = "Ukuran file terlalu besar. Maksimal 2MB.";
            return;
        }

        // Generate nama file unik
        $file_extension = pathinfo($_FILES['scanned_image']['name'], PATHINFO_EXTENSION);
        $unique_filename = $document_number . '_' . time() . '.' . $file_extension;
        
        try {
            // Pastikan folder tujuan ada
            if (!file_exists(UPLOAD_DIR . $folder_name)) {
                mkdir(UPLOAD_DIR . $folder_name, 0777, true);
            }

            $upload_path = UPLOAD_DIR . $folder_name . '/' . $unique_filename;
            error_log("Debug - Upload path: " . $upload_path);

            if (!move_uploaded_file($_FILES['scanned_image']['tmp_name'], $upload_path)) {
                throw new Exception("Gagal memindahkan file yang diupload. Error: " . error_get_last()['message']);
            }

            // Simpan informasi file ke database
            $query = "INSERT INTO files (folder_id, document_number, upload_date, document_type, file_path, description) 
                     VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($koneksi, $query);
            
            if (!$stmt) {
                unlink($upload_path); // Hapus file jika query gagal
                throw new Exception("Error preparing statement: " . mysqli_error($koneksi));
            }

            mysqli_stmt_bind_param($stmt, "isssss", 
                $folder_id, 
                $document_number, 
                $upload_date, 
                $document_type, 
                $unique_filename, 
                $description
            );

            if (!mysqli_stmt_execute($stmt)) {
                error_log("Debug - SQL Error: " . mysqli_error($koneksi));
                throw new Exception("Error executing statement: " . mysqli_stmt_error($stmt));
            }

            mysqli_stmt_close($stmt);
            $success_message = "File berhasil ditambahkan!";
            header("Location: file.php");
            exit();

        } catch (Exception $e) {
            error_log("Debug - Exception: " . $e->getMessage());
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

// Tambahkan fungsi hapusFile sebelum switch case
function hapusFile($koneksi, $id) {
    global $error_message, $success_message;
    
    // Ambil informasi file sebelum dihapus
    $stmt_select = mysqli_prepare($koneksi, "SELECT f.*, fo.folder_name 
                                           FROM files f 
                                           LEFT JOIN folders fo ON f.folder_id = fo.folder_id 
                                           WHERE f.file_id = ?");
    
    if (!$stmt_select) {
        $error_message = "Error dalam prepared statement: " . mysqli_error($koneksi);
        return;
    }

    mysqli_stmt_bind_param($stmt_select, "i", $id);
    mysqli_stmt_execute($stmt_select);
    $result = mysqli_stmt_get_result($stmt_select);
    $file_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt_select);

    if ($file_data) {
        // Hapus file fisik
        $file_path = UPLOAD_DIR . $file_data['folder_name'] . '/' . $file_data['file_path'];
        if (file_exists($file_path)) {
            if (!unlink($file_path)) {
                $error_message = "Gagal menghapus file fisik!";
                return;
            }
        }

        // Hapus record dari database
        $stmt = mysqli_prepare($koneksi, "DELETE FROM files WHERE file_id = ?");
        
        if (!$stmt) {
            $error_message = "Error dalam prepared statement: " . mysqli_error($koneksi);
            return;
        }
        
        mysqli_stmt_bind_param($stmt, "i", $id);
        
        if (mysqli_stmt_execute($stmt)) {
            $success_message = "File berhasil dihapus!";
            header("Location: file.php");
            exit();
        } else {
            $error_message = "Gagal menghapus file dari database: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    } else {
        $error_message = "File tidak ditemukan!";
    }
}

// Menangani aksi tambah file
if (isset($_GET['action']) && $_GET['action'] === 'tambah') {
    tambahFile($koneksi);
}

// Update switch case untuk menangani aksi edit
switch ($action) {
    case 'tambah':
        tambahFile($koneksi);
        break;
    case 'hapus':
        hapusFile($koneksi, $id);
        break;
}
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

        .file-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }

        .file-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .file-card:hover {
            transform: translateY(-5px);
        }

        .file-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .file-content {
            padding: 15px;
        }

        .file-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .file-info {
            font-size: 0.9rem;
            color: #666;
        }

        .file-actions {
            padding: 10px 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }

        .zoom-container {
            position: relative;
            overflow: hidden;
        }

        .zoom-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .zoom-btn:hover {
            background: rgba(0,0,0,0.7);
        }

        .modal-zoom {
            max-width: 90vw;
            margin: 20px auto;
        }

        .modal-zoom .modal-body {
            padding: 0;
            background: #f8f9fa;
            position: relative;
            height: 80vh;
            overflow: hidden;
        }

        #zoomContainer {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        #zoomImage {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            margin: auto;
            display: block;
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
            pointer-events: auto;
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
            transition: all 0.3s ease;
        }

        .zoom-controls button:hover {
            background: #e9ecef;
            transform: scale(1.1);
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
                    <li class="nav-item"><a href="users.php" class="nav-link"><i class="bi bi-person me-2"></i> Users</a></li>
                    <li class="nav-item"><a href="folder.php" class="nav-link"><i class="bi bi-folder me-2"></i> Folder</a></li>
                    <li class="nav-item"><a href="file.php" class="nav-link active"><i class="bi bi-file-earmark-text me-2"></i> File</a></li>
                </ul>
                <div class="mt-4"><a href="../logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></div>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <h2>Data File</h2>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-message" role="alert">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-message" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="d-flex align-items-center">
                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#tambahFileModal">
                    <i class="bi bi-plus-circle"></i> Tambah File
                </button>
                
                <form method="POST" class="d-flex">
                    <input type="text" name="search" class="form-control me-2" placeholder="Cari file..." 
                           value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>">
                    <button type="submit" class="btn btn-secondary">Cari</button>
                </form>
            </div>

            <div class="file-grid">
                <?php
                if ($result_files && mysqli_num_rows($result_files) > 0) {
                    while ($row = mysqli_fetch_assoc($result_files)) {
                        $file_path = '../uploads/' . $row['folder_name'] . '/' . $row['file_path'];
                        $file_extension = pathinfo($row['file_path'], PATHINFO_EXTENSION);
                        $is_image = in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif']);
                        ?>
                        <div class="file-card">
                            <?php if ($is_image): ?>
                                <div class="zoom-container">
                                    <?php $file_url = htmlspecialchars($file_path); ?>
                                    <img src="<?php echo $file_url; ?>" class="file-image" alt="Document Preview">
                                    <button type="button" class="zoom-btn" data-bs-toggle="modal" data-bs-target="#zoomModal" 
                                        data-image="<?php echo $file_url; ?>" 
                                        data-title="<?php echo htmlspecialchars($row['document_number']); ?>">
                                        <i class="bi bi-zoom-in"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="file-image d-flex align-items-center justify-content-center bg-light">
                                    <i class="bi bi-file-earmark-text" style="font-size: 4rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="file-content">
                                <div class="file-title"><?php echo htmlspecialchars($row['document_number']); ?></div>
                                <div class="file-info">
                                    <p><strong>Tipe:</strong> <?php echo htmlspecialchars($row['document_type']); ?></p>
                                    <p><strong>Folder:</strong> <?php echo htmlspecialchars($row['folder_name']); ?></p>
                                    <p><strong>Tanggal:</strong> <?php echo htmlspecialchars($row['upload_date']); ?></p>
                                    <p><strong>Deskripsi:</strong> <?php echo htmlspecialchars($row['description']); ?></p>
                                </div>
                            </div>
                            
                            <div class="file-actions">
                                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editFileModal"
                                    data-id="<?php echo $row['file_id']; ?>"
                                    data-docnumber="<?php echo htmlspecialchars($row['document_number']); ?>"
                                    data-doctype="<?php echo htmlspecialchars($row['document_type']); ?>"
                                    data-deskripsi="<?php echo htmlspecialchars($row['description']); ?>"
                                    data-folder="<?php echo htmlspecialchars($row['folder_id']); ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#hapusFileModal"
                                    data-id="<?php echo $row['file_id']; ?>"
                                    data-nama="<?php echo htmlspecialchars($row['document_number']); ?>">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='col-12 text-center'>Tidak ada data file.</div>";
                }
                ?>
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
                            <option value="">Pilih Folder</option>
                            <?php foreach ($folders as $folder): ?>
                                <option value="<?php echo htmlspecialchars($folder['folder_id']); ?>">
                                    <?php echo htmlspecialchars($folder['folder_name']); ?>
                                </option>
                            <?php endforeach; ?>
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

<!-- Modal Edit File -->
<div class="modal fade" id="editFileModal" tabindex="-1" aria-labelledby="editFileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFileModalLabel">Edit File</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editFileForm" action="edit_file.php" enctype="multipart/form-data">
                    <input type="hidden" name="file_id" id="edit_file_id">
                    <div class="mb-3">
                        <label for="edit_document_number" class="form-label">Nomor Dokumen</label>
                        <input type="text" class="form-control" name="document_number" id="edit_document_number" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_document_type" class="form-label">Tipe Dokumen</label>
                        <input type="text" class="form-control" name="document_type" id="edit_document_type" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" name="description" id="edit_deskripsi"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_folder" class="form-label">Folder</label>
                        <select name="folder_id" id="edit_folder" class="form-select" required>
                            <option value="">Pilih Folder</option>
                            <?php foreach ($folders as $folder): ?>
                                <option value="<?php echo htmlspecialchars($folder['folder_id']); ?>">
                                    <?php echo htmlspecialchars($folder['folder_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_file" class="form-label">File Baru (Opsional)</label>
                        <input type="file" class="form-control" name="scanned_image" id="edit_file">
                        <small class="text-muted">Biarkan kosong jika tidak ingin mengubah file</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Konfirmasi Hapus -->
<div class="modal fade" id="hapusFileModal" tabindex="-1" aria-labelledby="hapusFileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hapusFileModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus file dengan nomor dokumen "<span id="nama_file_hapus"></span>"?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <a href="#" class="btn btn-danger" id="btn_hapus">Hapus</a>
            </div>
        </div>
    </div>
</div>

<!-- Modal Zoom -->
<div class="modal fade" id="zoomModal" tabindex="-1" aria-labelledby="zoomModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-zoom">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="zoomModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="zoomContainer">
                    <img id="zoomImage" src="" alt="Zoomed image">
                </div>
                <div class="zoom-controls">
                    <button type="button" id="zoomIn" title="Zoom In">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                    <button type="button" id="zoomOut" title="Zoom Out">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                    <button type="button" id="zoomReset" title="Reset Zoom">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/panzoom@9.4.0/dist/panzoom.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Script untuk modal edit
        const editFileModal = document.getElementById('editFileModal');
        editFileModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const docNumber = button.getAttribute('data-docnumber');
            const docType = button.getAttribute('data-doctype');
            const deskripsi = button.getAttribute('data-deskripsi');
            const folder = button.getAttribute('data-folder');
            
            const modalForm = this.querySelector('#editFileForm');
            modalForm.querySelector('#edit_file_id').value = id;
            modalForm.action = `edit_file.php?id=${id}`;
            modalForm.querySelector('#edit_document_number').value = docNumber;
            modalForm.querySelector('#edit_document_type').value = docType;
            modalForm.querySelector('#edit_deskripsi').value = deskripsi;
            modalForm.querySelector('#edit_folder').value = folder;
        });

        // Script untuk modal hapus
        const hapusFileModal = document.getElementById('hapusFileModal');
        hapusFileModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const nama = button.getAttribute('data-nama');
            
            this.querySelector('#nama_file_hapus').textContent = nama;
            this.querySelector('#btn_hapus').href = `hapus_file.php?id=${id}`;
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
        }, 5000); // Ubah angka ini jika ingin pesan tampil lebih lama

        // Script untuk modal zoom
        const zoomModal = document.getElementById('zoomModal');
        let panzoomInstance = null;
        const ZOOM_SPEED = 0.2; // Kecepatan zoom yang lebih halus

        zoomModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const imageSrc = button.getAttribute('data-image');
            const title = button.getAttribute('data-title');
            
            const modalTitle = this.querySelector('.modal-title');
            const zoomImage = this.querySelector('#zoomImage');
            
            modalTitle.textContent = title;
            zoomImage.src = imageSrc;

            // Initialize panzoom setelah gambar dimuat
            zoomImage.onload = function() {
                if (panzoomInstance) {
                    panzoomInstance.dispose();
                }
                
                panzoomInstance = panzoom(zoomImage, {
                    maxZoom: 4,
                    minZoom: 0.5,
                    bounds: true,
                    boundsPadding: 0.5,
                    transformOrigin: {x: 0.5, y: 0.5},
                    smoothScroll: false,
                    beforeWheel: function(e) {
                        // Mencegah scroll default
                        e.preventDefault();
                        return true;
                    },
                    beforeMouseDown: function(e) {
                        // Nonaktifkan drag
                        return false;
                    }
                });

                // Reset zoom saat modal dibuka
                panzoomInstance.moveTo(0, 0);
                panzoomInstance.zoomAbs(0, 0, 1);
            };
        });

        // Zoom controls yang lebih konsisten
        document.getElementById('zoomIn').addEventListener('click', function() {
            if (panzoomInstance) {
                const currentZoom = panzoomInstance.getTransform().scale;
                const newZoom = currentZoom + ZOOM_SPEED;
                if (newZoom <= 4) { // Batasi maksimum zoom
                    panzoomInstance.zoomAbs(0, 0, newZoom);
                }
            }
        });

        document.getElementById('zoomOut').addEventListener('click', function() {
            if (panzoomInstance) {
                const currentZoom = panzoomInstance.getTransform().scale;
                const newZoom = currentZoom - ZOOM_SPEED;
                if (newZoom >= 0.5) { // Batasi minimum zoom
                    panzoomInstance.zoomAbs(0, 0, newZoom);
                }
            }
        });

        document.getElementById('zoomReset').addEventListener('click', function() {
            if (panzoomInstance) {
                panzoomInstance.moveTo(0, 0);
                panzoomInstance.zoomAbs(0, 0, 1);
            }
        });

        // Cleanup
        zoomModal.addEventListener('hidden.bs.modal', function() {
            if (panzoomInstance) {
                panzoomInstance.dispose();
                panzoomInstance = null;
            }
        });
    });
</script>
</body>
</html>
