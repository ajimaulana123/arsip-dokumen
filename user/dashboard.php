<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'user') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

// Tambahkan fungsi ini di bagian atas file setelah koneksi database
function countFiles($dir) {
    $total_files = 0;
    
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if ($file != '.' && $file != '..') {
                if (is_file($path)) {
                    $total_files++;
                } elseif (is_dir($path)) {
                    $total_files += countFiles($path);
                }
            }
        }
    }
    
    return $total_files;
}

// Cek apakah kolom user_id ada
$check_column = mysqli_query($koneksi, "SHOW COLUMNS FROM files LIKE 'user_id'");
if (mysqli_num_rows($check_column) == 0) {
    // Tambah kolom user_id jika belum ada
    mysqli_query($koneksi, "ALTER TABLE files ADD COLUMN user_id INT");
}

// Hitung jumlah file
$sql_count_files = "SELECT COUNT(*) as total FROM files";
$result = mysqli_query($koneksi, $sql_count_files);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $jumlah_file = $row['total'];
} else {
    $jumlah_file = 0;
    error_log("Error in query: " . mysqli_error($koneksi));
}

// Statistik dasar dengan error handling
$jumlah_user = 0;
$jumlah_folder = 0;
$new_users = 0;

// Ubah query untuk hanya mengambil user dengan role 'user'
$sql = "SELECT id, username, name, nik, jabatan FROM users WHERE role = 'user'";
$result = mysqli_query($koneksi, $sql);

if (!$result) {
    die("Error dalam query: " . mysqli_error($koneksi));
}

// Hitung total user (hanya role 'user')
$query_users = mysqli_query($koneksi, "SELECT COUNT(*) as total FROM users WHERE role = 'user'");
if ($query_users) {
    $row = mysqli_fetch_assoc($query_users);
    $jumlah_user = $row['total'];
} else {
    $jumlah_user = 0;
}

// Query untuk trend aktivitas yang lebih akurat
$sql_trend = "SELECT 
    dates.month_date as month,
    COALESCE(new_users.count, 0) as new_users,
    COALESCE(active_users.count, 0) as active_users,
    COALESCE(login_stats.total_logins, 0) as total_logins,
    COALESCE(login_stats.unique_logins, 0) as unique_logins
FROM (
    SELECT 
        DATE_FORMAT(CURRENT_DATE - INTERVAL (n-1) MONTH, '%Y-%m-01') as month_date
    FROM (
        SELECT 1 as n UNION SELECT 2 UNION SELECT 3 
        UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
    ) numbers
) dates
LEFT JOIN (
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m-01') as month,
        COUNT(*) as count
    FROM users 
    WHERE role = 'user'
    GROUP BY month
) new_users ON dates.month_date = new_users.month
LEFT JOIN (
    SELECT 
        DATE_FORMAT(last_login, '%Y-%m-01') as month,
        COUNT(DISTINCT id) as count
    FROM users 
    WHERE role = 'user' AND last_login IS NOT NULL
    GROUP BY month
) active_users ON dates.month_date = active_users.month
LEFT JOIN (
    SELECT 
        DATE_FORMAT(login_time, '%Y-%m-01') as month,
        COUNT(*) as total_logins,
        COUNT(DISTINCT user_id) as unique_logins
    FROM login_history
    INNER JOIN users ON users.id = login_history.user_id
    WHERE users.role = 'user'
    GROUP BY month
) login_stats ON dates.month_date = login_stats.month
ORDER BY dates.month_date DESC
LIMIT 6";

// Buat tabel login_history jika belum ada
$check_login_history = mysqli_query($koneksi, "SHOW TABLES LIKE 'login_history'");
if (mysqli_num_rows($check_login_history) == 0) {
    $sql_create_table = "CREATE TABLE login_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(50) NOT NULL,
        login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    mysqli_query($koneksi, $sql_create_table);
}

// Tambahkan record login saat user melakukan login
function recordLogin($user_id, $username) {
    global $koneksi;
    $sql = "INSERT INTO login_history (user_id, username) VALUES (?, ?)";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, "is", $user_id, $username);
    mysqli_stmt_execute($stmt);
}

// Update last_login di tabel users
function updateLastLogin($user_id) {
    global $koneksi;
    $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
    $stmt = mysqli_prepare($koneksi, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
}

// Panggil fungsi ini saat user login (tambahkan di file login.php)
if (isset($_SESSION['user_id'])) {
    recordLogin($_SESSION['user_id'], $_SESSION['username']);
    updateLastLogin($_SESSION['user_id']);
}

$result_trend = mysqli_query($koneksi, $sql_trend);
$trend_data = [];

if ($result_trend) {
    while ($row = mysqli_fetch_assoc($result_trend)) {
        // Format tanggal untuk display
        $date = DateTime::createFromFormat('Y-m-d', $row['month']);
        $row['month_display'] = $date->format('M Y');
        $trend_data[] = $row;
    }
}

// Reverse array untuk menampilkan dari bulan terlama
$trend_data = array_reverse($trend_data);

// Update query jabatan untuk hanya user dengan role 'user'
$sql_jabatan = "SELECT 
    COALESCE(jabatan, 'Tidak Ada') as jabatan, 
    COUNT(*) as total 
FROM users 
WHERE role = 'user'
GROUP BY jabatan 
ORDER BY total DESC 
LIMIT 5";

$result_jabatan = mysqli_query($koneksi, $sql_jabatan);
if ($result_jabatan) {
    while ($row = mysqli_fetch_assoc($result_jabatan)) {
        $jabatan_data[] = $row;
    }
}

// Jika tidak ada data jabatan, buat data dummy
if (empty($jabatan_data)) {
    $jabatan_data[] = [
        'jabatan' => 'No Data',
        'total' => 0
    ];
}

// Query untuk user baru bulan ini
$sql_new_users = "SELECT COUNT(*) as total 
                  FROM users 
                  WHERE created_at IS NOT NULL 
                  AND MONTH(created_at) = MONTH(CURRENT_DATE())
                  AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$result_new_users = mysqli_query($koneksi, $sql_new_users);
if ($result_new_users) {
    $new_users = mysqli_fetch_assoc($result_new_users)['total'];
}

// Cek apakah kolom yang diperlukan sudah ada
$check_columns = mysqli_query($koneksi, "SHOW COLUMNS FROM users LIKE 'created_at'");
if (mysqli_num_rows($check_columns) == 0) {
    // Tambahkan kolom created_at jika belum ada
    mysqli_query($koneksi, "ALTER TABLE users ADD created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

$check_last_login = mysqli_query($koneksi, "SHOW COLUMNS FROM users LIKE 'last_login'");
if (mysqli_num_rows($check_last_login) == 0) {
    // Tambahkan kolom last_login jika belum ada
    mysqli_query($koneksi, "ALTER TABLE users ADD last_login DATETIME NULL");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
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
            color: rgba(255, 255, 255, 0.8);
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .card {
            background-color: #343a40;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .stat-card {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
        }
        .stat-info h3 {
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
        }
        .stat-info p {
            opacity: 0.8;
            margin: 0;
        }
        .chart-container {
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .progress {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            background-color: #0d6efd;
        }
        .table-dark {
            background-color: #343a40;
            border-radius: 10px;
        }
        .user-info span {
            color: white !important;
        }
        /* Global text color */
        body, 
        h1, h2, h3, h4, h5, h6,
        p, span, a:not(.nav-link),
        .card-title,
        .stat-info p,
        .stat-info h3,
        .department-stats span,
        .user-info span,
        .chart-container .card-title {
            color: white !important;
        }
        /* Sidebar styles */
        .sidebar {
            background-color: #283149;
            min-height: 100vh;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
        }
        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .user-content {
            background-color:rgb(84, 109, 134);
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }
        /* Card styles */
        .card {
            background-color: #343a40;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border-radius: 10px;
            transition: transform 0.3s ease;
        }
        /* Stats card specific styles */
        .stat-card {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stat-info p {
            opacity: 0.8;
        }
        /* Progress bar styles */
        .progress {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .progress-bar {
            background-color: #0d6efd;
        }
        /* Chart container styles */
        .chart-container {
            padding: 1.5rem;
        }
        /* Department stats styles */
        .department-stats .mb-3 {
            color: white;
        }
        /* Override any Bootstrap text colors */
        .text-muted,
        .text-secondary {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        /* Make sure icons are white */
        .fas {
            color: white;
        }
        /* Ensure chart legends are white */
        canvas {
            color: white !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <a class="navbar-brand ms-3" href="#">
                    <span class="fs-4 fw-bold">ARSIP</span>
                </a>
                <ul class="nav flex-column mt-4">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php"><i class="fas fa-users me-2"></i> User</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="file_manager.php"><i class="fas fa-file me-2"></i> File Manager</a>
                    </li>
                    <li class="nav-item mt-auto mb-3">
                        <a class="nav-link logout text-danger" href="../logout.php"><i class="fas text-danger fa-sign-out-alt me-2"></i> Logout</a>
                    </li>
                </ul>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Header Section -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h2 mb-0">Dashboard Overview</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="#" class="text-white text-decoration-none">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="user-info d-flex align-items-center .user-content p-2 rounded">
                        <span class="me-3"><?php echo $_SESSION['username']; ?></span>
                        <div class="avatar">
                            <i class="fas fa-user-circle fs-4"></i>
                        </div>
                    </div>
                </div>

                <!-- Stats Cards Row -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-primary rounded-3 p-3 me-3">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-info">
                                        <p class="text-secondary mb-1">Total Users</p>
                                        <h3 class="mb-0"><?php echo $jumlah_user; ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card stat-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="stat-icon bg-danger rounded-3 p-3 me-3">
                                        <i class="fas fa-file"></i>
                                    </div>
                                    <div class="stat-info">
                                        <p class="text-secondary mb-1">Total Files</p>
                                        <h3 class="mb-0"><?php echo $jumlah_file; ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row g-4 mb-4">
                    <!-- Activity Trend Chart -->
                    <div class="col-12 col-xl-8">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="card-title mb-0">User Activity Trend</h5>
                                </div>
                                <canvas id="activityTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <!-- User Distribution -->
                </div>

                <!-- Department Stats Row -->
                <div class="row g-4">
                    <div class="col-12 col-xl-7">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="card-title mb-0">Department Distribution</h5>
                                </div>
                                <canvas id="departmentChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-5">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Top Departments</h5>
                                <div class="department-stats w-100">
                                    <?php foreach ($jabatan_data as $jabatan): ?>
                                    <div class="mb-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <h6 class="mb-0"><?php echo $jabatan['jabatan']; ?></h6>
                                                <small class="text-secondary"><?php echo $jabatan['total']; ?> users</small>
                                            </div>
                                            <span class="badge bg-primary"><?php echo round(($jabatan['total']/$jumlah_user*100), 1); ?>%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo ($jabatan['total']/$jumlah_user*100); ?>%" 
                                                 aria-valuenow="<?php echo $jabatan['total']; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="<?php echo $jumlah_user; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Update chart options to ensure all text is white
        const chartOptions = {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { 
                        color: 'white',
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                title: {
                    color: 'white',
                    font: {
                        size: 14,
                        weight: '600'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: { 
                        color: 'white',
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: { 
                        color: 'white',
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                }
            }
        };

        // Activity Trend Chart dengan informasi yang lebih detail
        new Chart(document.getElementById('activityTrendChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($trend_data, 'month_display')); ?>,
                datasets: [{
                    label: 'New Users',
                    data: <?php echo json_encode(array_column($trend_data, 'new_users')); ?>,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true,
                    order: 1
                }, {
                    label: 'Active Users',
                    data: <?php echo json_encode(array_column($trend_data, 'active_users')); ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25, 135, 84, 0.1)',
                    tension: 0.4,
                    fill: true,
                    order: 2
                }, {
                    label: 'Total Logins',
                    data: <?php echo json_encode(array_column($trend_data, 'total_logins')); ?>,
                    borderColor: '#ffc107',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.4,
                    fill: true,
                    order: 3
                }, {
                    label: 'Unique Logins',
                    data: <?php echo json_encode(array_column($trend_data, 'unique_logins')); ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: true,
                    order: 4
                }]
            },
            options: {
                ...chartOptions,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { 
                            padding: 20,
                            color: 'white',
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += context.parsed.y + (
                                        label.includes('Login') ? ' logins' : ' users'
                                    );
                                }
                                return label;
                            },
                            title: function(context) {
                                // Format bulan menjadi lebih readable
                                const month = context[0].label;
                                return new Date(month + '-01').toLocaleDateString('id-ID', {
                                    year: 'numeric',
                                    month: 'long'
                                });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: { 
                            color: 'white',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            callback: function(value) {
                                if (value % 1 === 0) {
                                    return value;
                                }
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: { 
                            color: 'white',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            callback: function(value, index) {
                                const label = this.getLabelForValue(value);
                                return new Date(label + '-01').toLocaleDateString('id-ID', {
                                    year: 'numeric',
                                    month: 'short'
                                });
                            }
                        }
                    }
                }
            }
        });

        // Department Chart
        new Chart(document.getElementById('departmentChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($jabatan_data, 'jabatan')); ?>,
                datasets: [{
                    label: 'Users per Department',
                    data: <?php echo json_encode(array_column($jabatan_data, 'total')); ?>,
                    backgroundColor: '#0d6efd',
                    borderRadius: 5
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw;
                                const total = <?php echo $jumlah_user; ?>;
                                const percentage = ((value / total) * 100).toFixed(0);
                                return `${value} users (${percentage}%)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: 'white',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            stepSize: 1,
                            callback: function(value) {
                                if (Math.floor(value) === value) {
                                    return value;
                                }
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: 'white',
                            font: {
                                size: 12,
                                weight: '500'
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>