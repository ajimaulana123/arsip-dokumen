<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

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
                        <a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right me-2"></i> Logout</a>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4">
                    <h1 class="h2">Data User</h1>
                </div>
                <div class="d-flex mb-3">
                    <form action="" method="GET" class="d-flex">
                        <input type="text" name="search" class="form-control" placeholder="Cari user..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        <button type="submit" class="btn btn-secondary ms-2">Cari</button>
                    </form>
                </div>

                <div class="table-responsive">
                    
                    <table class="table table-striped">
                        
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>NIK</th>
                                <th>Jabatan</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            if (mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<tr>";
                                    echo "<td>" . $no . "</td>";
                                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['nik']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['jabatan']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['role']) . "</td>";
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
