<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$uploadDir = '../uploads/'; // Directory for uploaded files

// Buat folder jika belum ada
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Proses unggah file
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $fileName = basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    // Validasi jika file sudah ada
    if (file_exists($targetPath)) {
        $_SESSION['error'] = "File dengan nama yang sama sudah ada.";
    } elseif (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $_SESSION['message'] = "File berhasil diunggah.";
    } else {
        $_SESSION['error'] = "Terjadi kesalahan saat mengunggah file.";
    }
    header("Location: file_manager.php");
    exit();
}

// Ambil daftar file dari folder
$files = is_dir($uploadDir) ? scandir($uploadDir) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
                        <a class="nav-link" href="users.php"><i class="fas fa-users me-2"></i> User</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="file_manager.php"><i class="fas fa-file me-2"></i> File Manager</a>
                    </li>
                    <li class="nav-item mt-auto mb-3">
                        <a class="nav-link logout text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
                    </li>
                </ul>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <?php
                if (isset($_SESSION['message'])) {
                    echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION['message']) . "</div>";
                    unset($_SESSION['message']);
                }
                if (isset($_SESSION['error'])) {
                    echo "<div class='alert alert-danger'>" . htmlspecialchars($_SESSION['error']) . "</div>";
                    unset($_SESSION['error']);
                }
                ?>
                <h1>File Manager</h1>
                <form action="" method="POST" enctype="multipart/form-data" class="mb-4">
                    <div class="input-group">
                        <input type="file" name="file" class="form-control" required>
                        <button class="btn btn-primary" type="submit"><i class="fas fa-upload"></i> Unggah</button>
                    </div>
                </form>
                <table class="table table-dark table-striped">
                    <thead>
                        <tr>
                            <th>Nama File</th>
                            <th>Ukuran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($files)) {
                            foreach ($files as $file) {
                                if ($file != "." && $file != "..") {
                                    $filePath = $uploadDir . $file;
                                    $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
                                    $fileSize = round($fileSize / 1024, 2); // Convert to KB
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($file) . "</td>";
                                    echo "<td>" . $fileSize . " KB</td>";
                                    echo "<td>";
                                    echo "<a href='" . htmlspecialchars($uploadDir . $file) . "' download class='btn btn-sm btn-primary'><i class='fas fa-download'></i> Unduh</a> ";
                                    echo "<a href='hapus_file.php?file=" . urlencode($file) . "' class='btn btn-sm btn-danger' onclick=\"return confirm('Apakah Anda yakin ingin menghapus file ini?')\"><i class='fas fa-trash'></i> Hapus</a>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            }
                        } else {
                            echo "<tr><td colspan='3'>Tidak ada file yang tersedia.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
