
<?php

// ========================================
// 1. START SESSION
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. CHECK ADMIN LOGIN
// ========================================

if (
    !isset($_SESSION['student_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login/login.php");
    exit();
}


// ========================================
// 3. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 4. CHECK CURRENT ADMIN ROLE
// ========================================

$student_id = $_SESSION['student_id'];

$stmt = $conn->prepare(
    "SELECT role FROM registration WHERE studentid = ? LIMIT 1"
);

$stmt->bind_param("s", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$admin = $result->fetch_assoc();

$stmt->close();

if (!$admin || $admin['role'] !== 'admin') {
    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit();
}


// ========================================
// 5. DASHBOARD STATISTICS
// ========================================

// Total Students
$query = $conn->query(
    "SELECT COUNT(*) AS total
     FROM registration
     WHERE role = 'student'"
);

$total_students = $query->fetch_assoc()['total'];


// Total Admins
$query = $conn->query(
    "SELECT COUNT(*) AS total
     FROM registration
     WHERE role = 'admin'"
);

$total_admins = $query->fetch_assoc()['total'];


// Today's Attendance
$query = $conn->query(
    "SELECT COUNT(*) AS total
     FROM attendance
     WHERE attendance_date = CURDATE()"
);

$todays_attendance = $query->fetch_assoc()['total'];


// Total Results
$query = $conn->query(
    "SELECT COUNT(*) AS total
     FROM results"
);

$total_results = $query->fetch_assoc()['total'];


// Total Fee Collection
$query = $conn->query(
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM fee_payments"
);

$total_collection = $query->fetch_assoc()['total'];


// Total Due
$query = $conn->query(
    "SELECT COALESCE(SUM(amount - paid_amount), 0) AS total
     FROM fees"
);

$total_due = $query->fetch_assoc()['total'];

?>

<!-- ========================================
     6. DASHBOARD DESIGN
======================================== -->

<style>

.dashboard-heading {
    margin-bottom: 25px;
}

.dashboard-heading h2 {
    color: #1e293b;
    font-size: 25px;
    margin-bottom: 7px;
}

.dashboard-heading p {
    color: #64748b;
    font-size: 14px;
}

.statistics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.stat-card {
    background: #ffffff;
    padding: 22px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.06);
    border: 1px solid #e8edf5;
}

.stat-card h4 {
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
    margin-bottom: 14px;
}

.stat-card h2 {
    color: #1e293b;
    font-size: 28px;
    font-weight: 700;
    margin: 0;
}

.stat-card .stat-icon {
    font-size: 25px;
    margin-bottom: 12px;
}

@media (max-width: 900px) {
    .statistics-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 550px) {
    .statistics-grid {
        grid-template-columns: 1fr;
    }
}

</style>


<div class="dashboard-heading">

    <h2>Admin Dashboard</h2>

    <p>Welcome to your Student Management System.</p>

</div>


<div class="statistics-grid">

    <!-- Total Students -->
    <div class="stat-card">

        <div class="stat-icon">🎓</div>

        <h4>Total Students</h4>

        <h2>
            <?= number_format((int)$total_students); ?>
        </h2>

    </div>


    <!-- Total Admins -->
    <div class="stat-card">

        <div class="stat-icon">👨‍💼</div>

        <h4>Total Admins</h4>

        <h2>
            <?= number_format((int)$total_admins); ?>
        </h2>

    </div>


    <!-- Today's Attendance -->
    <div class="stat-card">

        <div class="stat-icon">📅</div>

        <h4>Today's Attendance</h4>

        <h2>
            <?= number_format((int)$todays_attendance); ?>
        </h2>

    </div>


    <!-- Total Results -->
    <div class="stat-card">

        <div class="stat-icon">📊</div>

        <h4>Total Results</h4>

        <h2>
            <?= number_format((int)$total_results); ?>
        </h2>

    </div>


    <!-- Total Fee Collection -->
    <div class="stat-card">

        <div class="stat-icon">💰</div>

        <h4>Total Fee Collection</h4>

        <h2>
            ৳<?= number_format((float)$total_collection, 2); ?>
        </h2>

    </div>


    <!-- Total Due -->
    <div class="stat-card">

        <div class="stat-icon">🧾</div>

        <h4>Total Due</h4>

        <h2>
            ৳<?= number_format((float)$total_due, 2); ?>
        </h2>

    </div>

</div>
