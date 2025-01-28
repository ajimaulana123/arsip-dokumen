<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
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
    
    // Ambil nama folder sebelum dihapus
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
            // Hapus semua file dalam folder
            $files = glob($folder_path . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            // Hapus folder
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
            $error_message = "Gagal menghapus folder dari database: " . mysqli_error($koneksi);
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
                        <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
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

                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Folder</th>
                            <th>Deskripsi</th>
                            <th>Aksi</th>
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
                                echo "<td>";
                                echo "<div class='btn-action'>"; // Wrap buttons in a container for better styling
                                 echo "<a href='?action=edit&id=" . htmlspecialchars($row['folder_id']) . "' class='btn btn-warning btn-sm'><i class='fas fa-edit'></i> Edit</a>";
                                 echo "<a href='?action=hapus&id=" . htmlspecialchars($row['folder_id']) . "' class='btn btn-danger btn-sm' onclick=\"return confirm('Apakah Anda yakin ingin menghapus folder ini?')\"><i class='fas fa-trash-alt'></i> Hapus</a>";
                                 echo "</div>";
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