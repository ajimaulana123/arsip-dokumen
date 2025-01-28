<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
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

// Tambahkan fungsi editFile setelah fungsi tambahFile
function editFile($koneksi, $id) {
    global $error_message, $success_message;
    
    // Ambil data file yang akan diedit
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
    $result_select = mysqli_stmt_get_result($stmt_select);
    $file_data = mysqli_fetch_assoc($result_select);
    mysqli_stmt_close($stmt_select);

    if (!$file_data) {
        echo "File tidak ditemukan.";
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $folder_id = (int)$_POST['folder_id'];
        $document_number = mysqli_real_escape_string($koneksi, trim($_POST['document_number']));
        $document_type = mysqli_real_escape_string($koneksi, trim($_POST['document_type']));
        $description = mysqli_real_escape_string($koneksi, trim($_POST['description']));

        // Cek apakah ada file baru yang diupload
        if (!empty($_FILES['scanned_image']['name'])) {
            // Validasi file baru
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

            // Generate nama file baru
            $file_extension = pathinfo($_FILES['scanned_image']['name'], PATHINFO_EXTENSION);
            $new_filename = $document_number . '_' . time() . '.' . $file_extension;

            // Ambil nama folder
            $folder_query = "SELECT folder_name FROM folders WHERE folder_id = ?";
            $folder_stmt = mysqli_prepare($koneksi, $folder_query);
            mysqli_stmt_bind_param($folder_stmt, "i", $folder_id);
            mysqli_stmt_execute($folder_stmt);
            $folder_result = mysqli_stmt_get_result($folder_stmt);
            $folder_data = mysqli_fetch_assoc($folder_result);
            $folder_name = $folder_data['folder_name'];
            mysqli_stmt_close($folder_stmt);

            // Path untuk file baru
            $new_path = UPLOAD_DIR . $folder_name . '/' . $new_filename;

            // Pindahkan file baru
            if (!move_uploaded_file($_FILES['scanned_image']['tmp_name'], $new_path)) {
                $error_message = "Gagal memindahkan file yang diupload.";
                return;
            }

            // Hapus file lama
            $old_path = UPLOAD_DIR . $folder_name . '/' . $file_data['file_path'];
            if (file_exists($old_path)) {
                unlink($old_path);
            }

            // Update database dengan file baru
            $stmt = mysqli_prepare($koneksi, "UPDATE files SET folder_id = ?, document_number = ?, document_type = ?, file_path = ?, description = ? WHERE file_id = ?");
            mysqli_stmt_bind_param($stmt, "issssi", $folder_id, $document_number, $document_type, $new_filename, $description, $id);
        } else {
            // Update database tanpa mengubah file
            $stmt = mysqli_prepare($koneksi, "UPDATE files SET folder_id = ?, document_number = ?, document_type = ?, description = ? WHERE file_id = ?");
            mysqli_stmt_bind_param($stmt, "isssi", $folder_id, $document_number, $document_type, $description, $id);
        }

        if (mysqli_stmt_execute($stmt)) {
            $success_message = "File berhasil diubah!";
            header("Location: file.php");
            exit();
        } else {
            $error_message = "Gagal mengubah file: " . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }

    // Tampilkan form edit
    ?>
    <h2>Edit File</h2>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="folder_id" class="form-label">Folder</label>
            <select name="folder_id" id="folder_id" class="form-select" required>
                <?php
                $sql_folders = "SELECT folder_id, folder_name FROM folders";
                $result_folders = mysqli_query($koneksi, $sql_folders);
                while ($folder = mysqli_fetch_assoc($result_folders)) {
                    $selected = ($folder['folder_id'] == $file_data['folder_id']) ? 'selected' : '';
                    echo "<option value='" . htmlspecialchars($folder['folder_id']) . "' $selected>" . 
                         htmlspecialchars($folder['folder_name']) . "</option>";
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="document_number" class="form-label">Nomor Dokumen</label>
            <input type="text" class="form-control" name="document_number" id="document_number" 
                   value="<?php echo htmlspecialchars($file_data['document_number']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="document_type" class="form-label">Tipe Dokumen</label>
            <input type="text" class="form-control" name="document_type" id="document_type" 
                   value="<?php echo htmlspecialchars($file_data['document_type']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Deskripsi</label>
            <textarea class="form-control" name="description" id="description" rows="3" required><?php 
                echo htmlspecialchars($file_data['description']); 
            ?></textarea>
        </div>
        <div class="mb-3">
            <label for="scanned_image" class="form-label">File Baru (Opsional)</label>
            <input type="file" class="form-control" name="scanned_image" id="scanned_image">
            <small class="text-muted">Biarkan kosong jika tidak ingin mengubah file</small>
        </div>
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="file.php" class="btn btn-secondary">Batal</a>
    </form>
    <?php
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
    case 'edit':
        editFile($koneksi, $id);
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
                <div class="mt-4"><a href="../logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></div>
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
                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#tambahFileModal">
                    <i class="bi bi-plus-circle"></i> Tambah File
                </button>
                
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
                        <th>Aksi</th>
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
                            echo "<td>";
                            echo "<div class='btn-action'>";
                            echo "<a href='?action=edit&id=" . htmlspecialchars($row['file_id']) . "' class='btn btn-warning btn-sm'><i class='bi bi-pencil'></i> Edit</a>";
                            echo "<a href='?action=hapus&id=" . htmlspecialchars($row['file_id']) . "' class='btn btn-danger btn-sm'><i class='bi bi-trash'></i> Hapus</a>";
                            echo "</div>";
                            echo "</td>";
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
