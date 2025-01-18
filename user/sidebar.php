<?php include "sidebar.php"; ?>
<nav class="col-md-3 col-lg-2 sidebar">
    <a class="navbar-brand ms-3" href="#">
        <span class="fs-4 fw-bold">ARSIP</span>
    </a>
    <ul class="nav flex-column mt-4">
        <li class="nav-item">
            <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php') echo 'active'; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'users.php') echo 'active'; ?>" href="users.php">
                <i class="fas fa-users me-2"></i> User
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php if (basename($_SERVER['PHP_SELF']) == 'folder.php') echo 'active'; ?>" href="folder.php">
                <i class="fas fa-folder me-2"></i> Folder
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#"><i class="fas fa-file me-2"></i> File Manager</a>
        </li>
        <li class="nav-item mt-auto mb-3">
            <a class="nav-link logout text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </li>
    </ul>
</nav>