
<?php

// ========================================
// 1. SESSION START
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. ADMIN LOGIN CHECK
// ========================================

if (
    !isset($_SESSION['student_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 3. ONLY POST REQUEST ALLOWED
// ========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit("Method Not Allowed!");
}


// ========================================
// 4. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 5. LIVE ADMIN ROLE CHECK
// ========================================

$adminId = $_SESSION['student_id'];

$adminSql = "SELECT role
             FROM registration
             WHERE studentid = ?
             LIMIT 1";

$adminStmt = mysqli_prepare($conn, $adminSql);

mysqli_stmt_bind_param($adminStmt, "s", $adminId);

mysqli_stmt_execute($adminStmt);

$adminResult = mysqli_stmt_get_result($adminStmt);

$admin = mysqli_fetch_assoc($adminResult);

mysqli_stmt_close($adminStmt);


if (!$admin || $admin['role'] !== 'admin') {
    mysqli_close($conn);

    http_response_code(403);
    exit("Access Denied! Only Admin can delete notices.");
}


// ========================================
// 6. CSRF TOKEN CHECK
// ========================================

$submittedToken = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !is_string($submittedToken) ||
    !hash_equals($_SESSION['csrf_token'], $submittedToken)
) {
    mysqli_close($conn);

    http_response_code(403);
    exit("Invalid request! Please try again.");
}


// ========================================
// 7. NOTICE ID VALIDATION
// ========================================

$noticeId = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        "options" => [
            "min_range" => 1
        ]
    ]
);

if ($noticeId === false) {
    mysqli_close($conn);

    http_response_code(400);
    exit("Invalid Notice ID.");
}


// ========================================
// 8. CHECK NOTICE EXISTS
// ========================================

$checkSql = "SELECT id FROM notices WHERE id = ? LIMIT 1";

$checkStmt = mysqli_prepare($conn, $checkSql);

mysqli_stmt_bind_param($checkStmt, "i", $noticeId);

mysqli_stmt_execute($checkStmt);

$checkResult = mysqli_stmt_get_result($checkStmt);

$notice = mysqli_fetch_assoc($checkResult);

mysqli_stmt_close($checkStmt);


if (!$notice) {
    mysqli_close($conn);

    header("Location: manage_notices.php?error=not_found");
    exit;
}


// ========================================
// 9. DELETE NOTICE
// ========================================

$deleteSql = "DELETE FROM notices WHERE id = ?";

$deleteStmt = mysqli_prepare($conn, $deleteSql);

mysqli_stmt_bind_param($deleteStmt, "i", $noticeId);


if (mysqli_stmt_execute($deleteStmt)) {

    mysqli_stmt_close($deleteStmt);

    mysqli_close($conn);

    header("Location: manage_notices.php?deleted=1");
    exit;

} else {

    mysqli_stmt_close($deleteStmt);

    mysqli_close($conn);

    header("Location: manage_notices.php?error=delete_failed");
    exit;
}

?>
