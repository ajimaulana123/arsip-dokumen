<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

include '../includes/db.php';

$error_message = "";
$success_message = "";
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : '';

function tambahFolder($koneksi) {
    global $error_message, $success_message;
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $nama_folder = trim($_POST['nama_folder']);
        $deskripsi = trim($_POST['deskripsi']);

        if (empty($nama_folder)) {
            $error_message = "Nama folder harus diisi!";
        } else {
            $stmt = mysqli_prepare($koneksi, "INSERT INTO folder (nama_folder, deskripsi) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "ss", $nama_folder, $deskripsi);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Folder berhasil ditambahkan!";
            } else {
                $error_message = "Gagal menambahkan folder: " . mysqli_error($koneksi);
            }
            mysqli_stmt_close($stmt);
        }
    }
    ?>
    <h2>Tambah Folder</h2>
    <form method="POST">
        <input type="text" name="nama_folder" placeholder="Nama Folder" required><br>
        <textarea name="deskripsi" placeholder="Deskripsi"></textarea><br>
        <button type="submit">Simpan</button>
    </form>
    <?php
}
function editFolder($koneksi, $id) {
    global $error_message, $success_message;
    $stmt_select = mysqli_prepare($koneksi, "SELECT nama_folder, deskripsi FROM folder WHERE id = ?");
    mysqli_stmt_bind_param($stmt_select, "i", $id);
    mysqli_stmt_execute($stmt_select);
    $result_select = mysqli_stmt_get_result($stmt_select);
    $row = mysqli_fetch_assoc($result_select);
    $nama_folder = $row['nama_folder'];
    $deskripsi = $row['deskripsi'];
    mysqli_stmt_close($stmt_select);

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $nama_folder_post = trim($_POST['nama_folder']);
        $deskripsi_post = trim($_POST['deskripsi']);

        if (empty($nama_folder_post)) {
            $error_message = "Nama folder harus diisi!";
        } else {
            $stmt = mysqli_prepare($koneksi, "UPDATE folder SET nama_folder = ?, deskripsi = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssi", $nama_folder_post, $deskripsi_post, $id);
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Folder berhasil diubah!";
            } else {
                $error_message = "Gagal mengubah folder: " . mysqli_error($koneksi);
            }
            mysqli_stmt_close($stmt);
            $nama_folder = $nama_folder_post;
            $deskripsi = $deskripsi_post;
        }
    }
    ?>
    <h2>Edit Folder</h2>
    <form method="POST">
        <input type="text" name="nama_folder" value="<?php echo htmlspecialchars($nama_folder); ?>" required><br>
        <textarea name="deskripsi"><?php echo htmlspecialchars($deskripsi); ?></textarea><br>
        <button type="submit">Simpan</button>
    </form>
    <?php
}

function hapusFolder($koneksi, $id) {
    global $error_message, $success_message;
    $stmt = mysqli_prepare($koneksi, "DELETE FROM folder WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        $success_message = "Folder berhasil dihapus!";
    } else {
        $error_message = "Gagal menghapus folder: " . mysqli_error($koneksi);
    }
    mysqli_stmt_close($stmt);
}

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
    default:
        $sql = "SELECT id, nama_folder, deskripsi FROM folder";
        $result = mysqli_query($koneksi, $sql);
        break;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Folder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #1e273a; color: white; }
        table { background-color: #343a40; }
        th, td { border-color: #495057; }
        form input, form textarea {background-color: #343a40; color: white; border: 1px solid #495057;}
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 sidebar">
                </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <h1>Data Folder</h1>
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (empty($action)): ?>
                    <a href="?action=tambah" class="btn btn-primary mb-3">Tambah Folder</a>
                    <div class="table-responsive">
                    <table class="table table-dark table-striped">
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
                            $no = 1;
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<tr>";
                                    echo "<td>" . $no . "</td>";
                                    echo "<td>" . htmlspecialchars($row['nama_folder']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['deskripsi']) . "</td>";
                                    echo "<td>";
                                    echo "<a href='?action=edit&id=" . $row['id'] . "' class='btn btn-sm btn-warning me-1'>Edit</a>";
                                    echo "<a href='?action=hapus&id=" . $row['id'] . "' class='btn btn-sm btn-danger' onclick=\"return confirm('Apakah Anda yakin ingin menghapus folder ini?')\">Hapus</a>";
                                    echo "</td>";
                                    echo "</tr>";
                                    $no++;
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center'>Tidak ada data folder.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net