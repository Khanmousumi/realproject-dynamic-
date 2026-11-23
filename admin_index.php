
<?php

// ========================================
// 1. START SESSION
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. CHECK LOGIN SESSION
// ========================================

if (
    !isset($_SESSION['student_id']) ||
    !isset($_SESSION['role'])
) {
    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 3. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 4. GET CURRENT USER FROM DATABASE
// ========================================

$student_id = $_SESSION['student_id'];

$stmt = $conn->prepare(
    "SELECT fullname, profile_image, role, is_main_admin
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$stmt->bind_param("s", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$student = $result->fetch_assoc();

$stmt->close();


// ========================================
// 5. VERIFY USER ACCOUNT
// ========================================

if (
    !$student ||
    !in_array($student['role'], ['admin', 'student'], true)
) {
    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 6. REFRESH SESSION FROM DATABASE
// ========================================

$_SESSION['role'] = $student['role'];

$_SESSION['is_main_admin'] = (
    $student['role'] === 'admin' &&
    (int)$student['is_main_admin'] === 1
) ? 1 : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

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
            <!-- STUDENT ID CARD MENU -->

<a href="id_card.php" class="menu-link">
    <span class="menu-icon">🪪</span>
    <span>Student ID Card</span>
</a>

            <a href="dashboard.php" class="menu-link">
                <span class="menu-icon">♙</span>
                <span>Students</span>
            </a>

            <!-- NOTICE BOARD -->

            <a
                href="<?php
                    echo ($_SESSION['role'] === 'admin')
                        ? 'manage_notices.php'
                        : 'student_notices.php';
                ?>"
                class="menu-link"
            >
                <span class="menu-icon">📢</span>
                <span>Notice Board</span>
            </a>


            <!-- ATTENDANCE -->

            <a
                href="<?php
                    echo ($_SESSION['role'] === 'admin')
                        ? 'attendance.php'
                        : 'student_attendance.php';
                ?>"
                class="menu-link"
            >
                <span class="menu-icon">📅</span>
                <span>Attendance</span>
            </a>


            <?php if ($_SESSION['role'] === 'admin'): ?>

                <a href="attendance_report.php" class="menu-link">
                    <span class="menu-icon">📊</span>
                    <span>Attendance Report</span>
                </a>

            <?php endif; ?>


            <!-- RESULT MANAGEMENT -->

            <a
                href="<?php
                    echo ($_SESSION['role'] === 'admin')
                        ? 'manage_results.php'
                        : 'student_results.php';
                ?>"
                class="menu-link"
            >
                <span class="menu-icon">🎓</span>

                <span>
                    <?php
                    echo ($_SESSION['role'] === 'admin')
                        ? 'Result Management'
                        : 'My Results';
                    ?>
                </span>
            </a>


            <?php if ($_SESSION['role'] === 'admin'): ?>

                <a href="add_result.php" class="menu-link">
                    <span class="menu-icon">＋</span>
                    <span>Add Result</span>
                </a>

            <?php endif; ?>


            <!-- FEE MANAGEMENT -->

            <a
                href="<?php
                    echo ($_SESSION['role'] === 'admin')
                        ? 'manage_fees.php'
                        : 'student_fees.php';
                ?>"
                class="menu-link"
            >
                <span class="menu-icon">💰</span>

                <span>
                    <?php
                    echo ($_SESSION['role'] === 'admin')
                        ? 'Fee Management'
                        : 'My Fees';
                    ?>
                </span>
            </a>


            <?php if ($_SESSION['role'] === 'admin'): ?>

                <a href="add_fee.php" class="menu-link">
                    <span class="menu-icon">＋</span>
                    <span>Add Fee</span>
                </a>

                <a href="payment_history.php" class="menu-link">
                    <span class="menu-icon">📋</span>
                    <span>Payment History</span>
                </a>

                <a href="fee_report.php" class="menu-link">
                    <span class="menu-icon">📊</span>
                    <span>Fee Report</span>
                </a>

            <?php endif; ?>


            <!-- REGISTRATION -->

            <a href="../register/register.php" class="menu-link">
                <span class="menu-icon">＋</span>
                <span>Registration</span>
            </a>


            <!-- ADMIN MANAGEMENT -->

            <?php if ($_SESSION['role'] === 'admin'): ?>

                <div class="menu-title">ADMIN MANAGEMENT</div>

                <a href="create_admin.php" class="menu-link">
                    <span class="menu-icon">♙</span>
                    <span>Create Admin</span>
                </a>

                <a href="manage_admin.php" class="menu-link">
                    <span class="menu-icon">☷</span>
                    <span>Manage Admin</span>
                </a>

            <?php endif; ?><!-- MY PROFILE MENU -->

<a href="my_profile.php" class="menu-link">
    <span class="menu-icon">👤</span>
    <span>My Profile</span>
</a>
<a href="change_password.php" class="menu-link">
    <span class="menu-icon">🔒</span>
    <span>Change Password</span>
</a>




            <!-- PREFERENCES -->

            <div class="menu-title">PREFERENCES</div>

            <a href="#" class="menu-link">
                <span class="menu-icon">⚙</span>
                <span>Settings</span>
            </a>

        </nav>


        <!-- SIDEBAR BOTTOM -->

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


    <!-- MAIN AREA -->

    <div class="main-area">


        <!-- TOP NAVBAR -->

        <header class="topbar">

            <div class="topbar-left">

                <button
                    class="menu-toggle"
                    id="menuToggle"
                    type="button"
                    aria-label="Toggle sidebar"
                >
                    ☰
                </button>

                <div>

                    <h2>Dashboard</h2>

                    <p>Welcome to your workspace</p>

                </div>

            </div>


            <div class="topbar-right">

                <!-- Notification -->

                <button
                    type="button"
                    class="notification-btn"
                    aria-label="Notifications"
                    title="Notifications"
                >
                    🔔
                </button>


                <!-- PROFILE PHOTO -->

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
                        alt="Profile Photo"
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


                <!-- USER NAME -->

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
                        echo ($_SESSION['role'] === 'admin')
                            ? 'Admin Account'
                            : 'Student Account';
                        ?>

                    </span>

                </div>

            </div>

        </header>


        <!-- MAIN CONTENT AREA -->

        <main class="content-area">

            <?php if ($_SESSION['role'] === 'admin'): ?>

                <?php include 'admin_dashboard.php'; ?>

            <?php else: ?>

                <?php include 'dashboard.php'; ?>

            <?php endif; ?>

        </main>


        <!-- FOOTER -->

        <footer class="footer">

            <p>
                &copy; <?php echo date('Y'); ?>
                Dynamic. All rights reserved.
            </p>

        </footer>

    </div>

</div>


<!-- SIDEBAR JAVASCRIPT -->

<script>

const menuToggle = document.getElementById('menuToggle');

const sidebar = document.getElementById('sidebar');

menuToggle.addEventListener('click', function () {

    sidebar.classList.toggle('show');

});

</script>

</body>
</html>
