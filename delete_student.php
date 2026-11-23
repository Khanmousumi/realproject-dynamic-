
<?php

// ========================================
// 1. START SESSION
// ========================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


// ========================================
// 2. CHECK LOGIN
// ========================================

if (!isset($_SESSION['student_id'])) {
    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 3. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 4. VERIFY CURRENT USER FROM DATABASE
// ========================================

$currentId = $_SESSION['student_id'];

$currentStmt = $conn->prepare(
    "SELECT role, is_main_admin
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$currentStmt->bind_param("s", $currentId);
$currentStmt->execute();

$currentUser = $currentStmt->get_result()->fetch_assoc();

$currentStmt->close();

// 2. Verify Admin Permission from Database

$currentStudentId = $_SESSION['student_id'];

$adminCheck = $conn->prepare(
    "SELECT role, is_main_admin
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$adminCheck->bind_param("s", $currentStudentId);
$adminCheck->execute();

$currentUser = $adminCheck->get_result()->fetch_assoc();

$adminCheck->close();

// Check whether account exists and is Admin
if (!$currentUser || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    exit("Access denied! Only Admin can delete students.");
}

// Update Session with current Database information
$_SESSION['role'] = $currentUser['role'];

$_SESSION['is_main_admin'] = (
    (int)$currentUser['is_main_admin'] === 1
) ? 1 : 0;



// Refresh Session

$_SESSION['role'] = $currentUser['role'];

$_SESSION['is_main_admin'] = (
    (int)$currentUser['is_main_admin'] === 1
) ? 1 : 0;


// ========================================
// 6. ALLOW POST REQUEST ONLY
// ========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);
    exit("Invalid request method.");
}


// ========================================
// 7. CHECK CSRF TOKEN
// ========================================

$token = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {

    http_response_code(403);
    exit("Invalid security token. Please try again.");
}


// ========================================
// 8. GET STUDENT ID
// ========================================

$studentId = trim($_POST['studentid'] ?? '');

if ($studentId === '') {
    exit("Student ID is required.");
}


// ========================================
// 9. PREVENT SELF DELETE
// ========================================

if ($studentId === $currentId) {

    http_response_code(403);
    exit("You cannot delete your own account.");
}


// ========================================
// 10. CHECK TARGET ACCOUNT
// ========================================

$check = $conn->prepare(
    "SELECT studentid, role, is_main_admin
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$check->bind_param("s", $studentId);
$check->execute();

$target = $check->get_result()->fetch_assoc();

$check->close();


if (!$target) {

    header("Location: dashboard.php?deleted=0");
    exit;
}


// ========================================
// 11. MAIN ADMIN PROTECTION
// ========================================

if ((int)$target['is_main_admin'] === 1) {

    http_response_code(403);
    exit("Main Admin account cannot be deleted.");
}


// ========================================
// 12. ONLY DELETE STUDENT ACCOUNTS
// ========================================

if ($target['role'] !== 'student') {

    http_response_code(403);
    exit("This account cannot be deleted from Student List.");
}


// ========================================
// 13. DELETE STUDENT
// ========================================

$sql = "DELETE FROM registration
        WHERE studentid = ?
        AND role = 'student'
        AND is_main_admin = 0";

$stmt = $conn->prepare($sql);

$stmt->bind_param("s", $studentId);

$stmt->execute();

$deleted = ($stmt->affected_rows > 0);

$stmt->close();


// ========================================
// 14. REDIRECT WITH MESSAGE
// ========================================

if ($deleted) {

    header("Location: dashboard.php?deleted=1");
    exit;

} else {

    header("Location: dashboard.php?deleted=0");
    exit;
}

?>
