<?php
session_start();
if (!isset($_SESSION['username'])) {
    echo "<script>alert('Harap login terlebih dahulu!'); window.location.href='login.php';</script>";
    exit();
}
include '../includes/db.php';
if (!$koneksi) {
    die("Koneksi database GAGAL: " . mysqli_connect_error());
}

// Query untuk menghitung total user
$totalUsers = 0;
$result = $koneksi->query("SHOW TABLES LIKE 'users'");
if ($result && $result->num_rows > 0) {
    $result = $koneksi->query("SELECT COUNT(*) AS total FROM users");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalUsers = $row['total'];
    }
}

// Query untuk menghitung total folder
$totalFolders = 0;
$result = $koneksi->query("SHOW TABLES LIKE 'folders'");
if ($result && $result->num_rows > 0) {
    $result = $koneksi->query("SELECT COUNT(*) AS total FROM folders");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalFolders = $row['total'];
    }
}

// Query untuk menghitung total file
$totalFiles = 0;
$result = $koneksi->query("SHOW TABLES LIKE 'files'");
if ($result && $result->num_rows > 0) {
    $result = $koneksi->query("SELECT COUNT(*) AS total FROM files");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalFiles = $row['total'];
    }
}

// Tambahkan query untuk mengambil riwayat update setelah query menghitung total
// Riwayat Files
$sql_files_history = "SELECT f.document_number, f.document_type, f.upload_date, 
                      fo.folder_name
                      FROM files f 
                      LEFT JOIN folders fo ON f.folder_id = fo.folder_id
                      ORDER BY f.upload_date DESC LIMIT 5";
$files_history = mysqli_query($koneksi, $sql_files_history);

// Riwayat Folders
$sql_folders_history = "SELECT folder_name, created_by, created_at 
                       FROM folders 
                       ORDER BY created_at DESC LIMIT 5";
$folders_history = mysqli_query($koneksi, $sql_folders_history);

// Riwayat Users
$sql_users_history = "SELECT username, role, created_at 
                     FROM users 
                     ORDER BY created_at DESC LIMIT 5";
$users_history = mysqli_query($koneksi, $sql_users_history);

// Ganti bagian logika pencarian
$searchQuery = '';
$searchResults = [
    'users' => [],
    'folders' => [],
    'files' => []
];

if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['search'])) {
    $searchQuery = mysqli_real_escape_string($koneksi, $_POST['search']);
    
    // Cari di tabel users
    $sql_search_users = "SELECT * 
                        FROM users 
                        WHERE LOWER(username) LIKE LOWER('%$searchQuery%') 
                        OR LOWER(role) LIKE LOWER('%$searchQuery%')
                        OR LOWER(name) LIKE LOWER('%$searchQuery%')  
                        OR LOWER(nik) LIKE LOWER('%$searchQuery%')
                        OR LOWER(jabatan) LIKE LOWER('%$searchQuery%')
                        ORDER BY created_at DESC
                        LIMIT 5";
    $result_users = mysqli_query($koneksi, $sql_search_users);
    if ($result_users) {
        while ($row = mysqli_fetch_assoc($result_users)) {
            $searchResults['users'][] = $row;
        }
    }

    // Cari di tabel folders
    $sql_search_folders = "SELECT * 
                          FROM folders 
                          WHERE LOWER(folder_name) LIKE LOWER('%$searchQuery%') 
                          OR LOWER(description) LIKE LOWER('%$searchQuery%')
                          OR LOWER(created_by) LIKE LOWER('%$searchQuery%')
                          ORDER BY created_at DESC
                          LIMIT 5";
    $result_folders = mysqli_query($koneksi, $sql_search_folders);
    if ($result_folders) {
        while ($row = mysqli_fetch_assoc($result_folders)) {
            $searchResults['folders'][] = $row;
        }
    }

    // Cari di tabel files
    $sql_search_files = "SELECT f.*, fo.folder_name 
                         FROM files f
                         LEFT JOIN folders fo ON f.folder_id = fo.folder_id
                         WHERE LOWER(f.document_number) LIKE LOWER('%$searchQuery%')
                         OR LOWER(f.document_type) LIKE LOWER('%$searchQuery%')
                         OR LOWER(f.description) LIKE LOWER('%$searchQuery%')
                         OR LOWER(fo.folder_name) LIKE LOWER('%$searchQuery%')
                         ORDER BY f.upload_date DESC
                         LIMIT 5";
    $result_files = mysqli_query($koneksi, $sql_search_files);
    if ($result_files) {
        while ($row = mysqli_fetch_assoc($result_files)) {
            $searchResults['files'][] = $row;
        }
    }

    // Debug: Cek hasil query
    error_log("Search Query: " . $searchQuery);
    error_log("Users found: " . count($searchResults['users']));
    error_log("Folders found: " . count($searchResults['folders']));
    error_log("Files found: " . count($searchResults['files']));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ARSIP DOKUMEN BPB dan RFG</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
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

        .card {
            border-radius: 10px;
        }

        .header {
            background-color: #343a40;
            color: white;
            padding: 15px;
        }

        .header form {
            display: flex;
            align-items: center;
        }

        .header input {
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            .sidebar {
                height: auto;
                width: 100%;
                position: relative;
            }

            .content {
                padding-left: 15px;
                padding-right: 15px;
            }

            .header form {
                flex-direction: column;
                align-items: stretch;
            }

            .header input {
                margin-bottom: 10px;
            }
        }

        .scrollable-history {
            max-height: 400px;
            overflow-y: auto;
            scrollbar-width: thin;
        }

        /* Styling untuk scrollbar (Chrome, Edge, Safari) */
        .scrollable-history::-webkit-scrollbar {
            width: 6px;
        }

        .scrollable-history::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .scrollable-history::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .scrollable-history::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Header -->
        <div class="row header">
            <div class="col-md-12 d-flex justify-content-between align-items-center">
                <h3>ARSIP</h3>
                <form method="POST" action="">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Cari dokumen..." value="<?php echo $searchQuery; ?>">
                        <button type="submit" class="btn btn-primary">Cari</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky">
                    <ul class="nav flex-column mt-4">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link active"><i class="bi bi-house-door"></i> Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a href="users.php" class="nav-link"><i class="bi bi-person"></i> Users</a>
                        </li>
                        <li class="nav-item">
                            <a href="folder.php" class="nav-link"><i class="bi bi-folder"></i> Folder</a>
                        </li>
                        <li class="nav-item">
                            <a href="file.php" class="nav-link"><i class="bi bi-file-earmark-text"></i> File</a>
                        </li>
                    </ul>
                    <div class="mt-4">
                        <a href="../logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                    </div>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
                <!-- Hasil Pencarian -->
                <?php if ($searchQuery): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <h4>Hasil Pencarian untuk "<?php echo htmlspecialchars($searchQuery); ?>"</h4>
                            
                            <?php if (empty($searchResults['users']) && empty($searchResults['folders']) && empty($searchResults['files'])): ?>
                                <div class="alert alert-info">Tidak ada hasil ditemukan.</div>
                            <?php else: ?>
                                <!-- Hasil Pencarian Users -->
                                <?php if (!empty($searchResults['users'])): ?>
                                    <div class="card mb-3">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="card-title mb-0">Users</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="list-group">
                                                <?php foreach ($searchResults['users'] as $user): ?>
                                                    <div class="list-group-item">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h6>
                                                        <p class="mb-1">
                                                            Role: <?php echo htmlspecialchars($user['role']); ?>
                                                        </p>
                                                        <small>
                                                            Dibuat: <?php echo htmlspecialchars($user['created_at']); ?>
                                                            <?php if(isset($user['last_login'])): ?>
                                                                <br>Login terakhir: <?php echo htmlspecialchars($user['last_login']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Hasil Pencarian Folders -->
                                <?php if (!empty($searchResults['folders'])): ?>
                                    <div class="card mb-3">
                                        <div class="card-header bg-success text-white">
                                            <h5 class="card-title mb-0">Folders</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="list-group">
                                                <?php foreach ($searchResults['folders'] as $folder): ?>
                                                    <div class="list-group-item">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($folder['folder_name']); ?></h6>
                                                        <p class="mb-1">
                                                            <?php if(!empty($folder['description'])): ?>
                                                                Deskripsi: <?php echo htmlspecialchars($folder['description']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                        <small>
                                                            Dibuat oleh: <?php echo htmlspecialchars($folder['created_by']); ?><br>
                                                            Tanggal: <?php echo htmlspecialchars($folder['created_at']); ?>
                                                        </small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Hasil Pencarian Files -->
                                <?php if (!empty($searchResults['files'])): ?>
                                    <div class="card mb-3">
                                        <div class="card-header bg-danger text-white">
                                            <h5 class="card-title mb-0">Files</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="list-group">
                                                <?php foreach ($searchResults['files'] as $file): ?>
                                                    <div class="list-group-item">
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($file['document_number']); ?></h6>
                                                        <p class="mb-1">
                                                            Folder: <?php echo htmlspecialchars($file['folder_name']); ?><br>
                                                            Tipe: <?php echo htmlspecialchars($file['document_type']); ?><br>
                                                            Deskripsi: <?php echo htmlspecialchars($file['description']); ?>
                                                        </p>
                                                        <small>
                                                            Tanggal Upload: <?php echo htmlspecialchars($file['upload_date']); ?>
                                                            <?php if(isset($file['created_by'])): ?>
                                                                <br>Upload oleh: <?php echo htmlspecialchars($file['created_by']); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Info Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-header">Total Users</div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $totalUsers; ?></h5>
                                <p class="card-text">Jumlah user terdaftar.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-header">Folder</div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $totalFolders; ?></h5>
                                <p class="card-text">Jumlah folder yang dibuat.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-danger mb-3">
                            <div class="card-header">File</div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $totalFiles; ?></h5>
                                <p class="card-text">Jumlah file yang diunggah.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ganti bagian grafik dengan riwayat update -->
                <div class="row mt-4">
                      <!-- Riwayat Users -->
                      <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">Riwayat Users Terbaru</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush scrollable-history">
                                    <?php while ($user = mysqli_fetch_assoc($users_history)): ?>
                                        <div class="list-group-item">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h6>
                                            <p class="mb-1">
                                                Role: <?php echo htmlspecialchars($user['role']); ?>
                                            </p>
                                            <small>
                                                Dibuat: <?php echo htmlspecialchars($user['created_at']); ?>
                                                <?php if(isset($user['last_login'])): ?>
                                                    <br>Login terakhir: <?php echo htmlspecialchars($user['last_login']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                      <!-- Riwayat Folders -->
                      <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">Riwayat Folders Terbaru</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush scrollable-history">
                                    <?php while ($folder = mysqli_fetch_assoc($folders_history)): ?>
                                        <div class="list-group-item">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($folder['folder_name']); ?></h6>
                                            <p class="mb-1">
                                                <?php if(!empty($folder['description'])): ?>
                                                    Deskripsi: <?php echo htmlspecialchars($folder['description']); ?>
                                                <?php endif; ?>
                                            </p>
                                            <small>
                                                Dibuat oleh: <?php echo htmlspecialchars($folder['created_by']); ?><br>
                                                Tanggal: <?php echo htmlspecialchars($folder['created_at']); ?>
                                            </small>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Riwayat Files -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0">Riwayat Files Terbaru</h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush scrollable-history">
                                    <?php while ($file = mysqli_fetch_assoc($files_history)): ?>
                                        <div class="list-group-item">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($file['document_number']); ?></h6>
                                            <p class="mb-1">
                                                Folder: <?php echo htmlspecialchars($file['folder_name']); ?><br>
                                                Tipe: <?php echo htmlspecialchars($file['document_type']); ?>
                                            </p>
                                            <small>
                                                Tanggal Upload: <?php echo htmlspecialchars($file['upload_date']); ?>
                                            </small>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>