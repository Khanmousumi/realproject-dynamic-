<?php
session_start();

// Login check
if (!isset($_SESSION['role'])) {
    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit;
}

require_once '../../config/db.php';

// লগইন করা শিক্ষার্থীর তথ্য আনা
$student_id = $_SESSION['student_id'];

$stmt = $conn->prepare(
    "SELECT fullname, profile_image
     FROM registration
     WHERE studentid = ?"
);

$stmt->bind_param("s", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>

<div class="dashboard-container">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">

        <div class="logo">
            <span class="logo-icon">D</span>
            <span>Dynamic</span>
        </div>

        <div class="menu-title">MAIN MENU</div>

        <nav class="menu">

            <a href="admin_index.php" class="menu-link active">
                <span class="menu-icon">▦</span>
                <span>Dashboard</span>
            </a>

            <a href="dashboard.php" class="menu-link">
                <span class="menu-icon">♙</span>
                <span>Students</span>
            </a>

            <a href="../register/register.php" class="menu-link">
                <span class="menu-icon">＋</span>
                <span>Registration</span>
            </a>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>

               <div class="menu-title">ADMIN MANAGEMENT</div>

                <a href="create_admin.php" class="menu-link">
                   <span class="menu-icon">♙</span>
                  <span>Create Admin</span>
                 </a>

            <?php endif; ?>

            <div class="menu-title">PREFERENCES</div>

            <a href="#" class="menu-link">
                <span class="menu-icon">⚙</span>
                <span>Settings</span>
            </a>

        </nav>

        <div class="sidebar-bottom">
            <div class="help-box">
                <div class="help-icon">?</div>
                <strong>Need Help?</strong>
                <p>Contact your administrator.</p>
            </div>

            <a href="logout.php" class="menu-link logout">
                <span class="menu-icon">⇥</span>
                <span>Logout</span>
            </a>
        </div>

    </aside>

    <!-- Main Area -->
    <div class="main-area">

    
<!-- Top Navbar -->
<header class="topbar">

    <div class="topbar-left">
        <button class="menu-toggle"
                id="menuToggle"
                type="button"
                aria-label="Toggle sidebar">
            ☰
        </button>

        <div>
            <h2>Dashboard</h2>
            <p>Welcome to your workspace</p>
        </div>
    </div>

    <div class="topbar-right">

        <!-- Notification Bell -->
        <button type="button"
                class="notification-btn"
                aria-label="Notifications"
                title="Notifications">
            🔔
        </button>

        <!-- Student Profile Photo -->
        <?php if (!empty($student['profile_image'])): ?>

            <img
                class="user-avatar"
                src="../../Uploads_Photo/<?php
                    echo htmlspecialchars(
                        $student['profile_image'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                ?>"
                alt="Student Photo"
            >

        <?php else: ?>

            <div class="user-avatar">
                <?php
                echo strtoupper(
                    substr($student['fullname'] ?? 'S', 0, 1)
                );
                ?>
            </div>

        <?php endif; ?>

        <!-- Student Name -->
        <div class="user-info">
            <strong>
                <?php
                echo htmlspecialchars(
                    $student['fullname'] ?? 'Student',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </strong>

            <span>
                 <?php
                 echo (($_SESSION['role'] ?? '') === 'admin')
                      ? 'Admin Account'
                    : 'Student Account';
                          ?>
                   </span>

        
        </div>

    </div>

</header>


       

        <!-- Empty Main Content -->
        <main class="content-area">
            <?php include 'dashboard.php'; ?>
        </main>

        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Dynamic. All rights reserved.</p>
        </footer>

    </div>

</div>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');

menuToggle.addEventListener('click', function () {
    sidebar.classList.toggle('show');
});
</script>

</body>
</html>
