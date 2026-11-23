
<?php

// ========================================
// 1. SESSION START
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. ADMIN SESSION CHECK
// ========================================

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin' ||
    !isset($_SESSION['student_id'])
) {
    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 3. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 4. VERIFY CURRENT ADMIN FROM DATABASE
// ========================================

$currentStudentId = $_SESSION['student_id'];

$currentStmt = $conn->prepare(
    "SELECT role, is_main_admin
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$currentStmt->bind_param("s", $currentStudentId);
$currentStmt->execute();

$currentAdmin = $currentStmt->get_result()->fetch_assoc();

$currentStmt->close();

if (!$currentAdmin || $currentAdmin['role'] !== 'admin') {
    http_response_code(403);
    exit("Access denied.");
}

// Update Session from Database
$_SESSION['role'] = $currentAdmin['role'];

$_SESSION['is_main_admin'] = (
    (int)$currentAdmin['is_main_admin'] === 1
) ? 1 : 0;

$isCurrentMainAdmin = (
    $_SESSION['is_main_admin'] === 1
);


// ========================================
// 5. CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$successMessage = '';


// ========================================
// 6. GET ADMIN ID
// ========================================

if (
    !isset($_GET['id']) ||
    !filter_var($_GET['id'], FILTER_VALIDATE_INT)
) {
    exit("Invalid Admin ID.");
}

$adminId = (int)$_GET['id'];


// ========================================
// 7. FETCH TARGET ADMIN
// ========================================

$stmt = $conn->prepare(
    "SELECT id, fullname, studentid, profile_image, is_main_admin
     FROM registration
     WHERE id = ?
     AND role = 'admin'
     LIMIT 1"
);

$stmt->bind_param("i", $adminId);
$stmt->execute();

$admin = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$admin) {
    exit("Admin account not found.");
}


// ========================================
// 8. MAIN ADMIN PROTECTION
// ========================================

// Main Admin can never be deleted.

if ((int)$admin['is_main_admin'] === 1) {

    http_response_code(403);

    exit("Main Admin account cannot be deleted.");
}


// ========================================
// 9. PREVENT SELF DELETION
// ========================================

if ($admin['studentid'] === $currentStudentId) {

    exit("You cannot delete your own Admin account.");
}


// ========================================
// 10. DELETE ADMIN
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF Token

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token']
        )
    ) {

        $errorMessage = "Invalid request. Please try again.";

    } else {

        // Delete only a normal Admin account.
        // Main Admin is protected by the SQL condition.

        $deleteStmt = $conn->prepare(
            "DELETE FROM registration
             WHERE id = ?
             AND role = 'admin'
             AND is_main_admin = 0"
        );

        $deleteStmt->bind_param("i", $adminId);

        if (
            $deleteStmt->execute() &&
            $deleteStmt->affected_rows === 1
        ) {

            // Delete old profile photo

            $photoName = basename($admin['profile_image'] ?? '');

            if (
                $photoName !== '' &&
                $photoName !== 'default.png'
            ) {

                $photoPath = '../../Uploads_Photo/' . $photoName;

                if (is_file($photoPath)) {
                    unlink($photoPath);
                }
            }

            $successMessage = "Admin account deleted successfully.";

            // Generate a new CSRF token

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        } else {

            $errorMessage = "Admin account could not be deleted.";

        }

        $deleteStmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Delete Admin</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        .container {
            max-width: 500px;
            margin: 70px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        h2 {
            color: #dc2626;
        }

        p {
            color: #475569;
            line-height: 1.7;
        }

        .admin-info {
            margin: 20px 0;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .delete-btn {
            display: inline-block;
            padding: 11px 20px;
            border: none;
            border-radius: 6px;
            background: #dc2626;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .delete-btn:hover {
            background: #991b1b;
        }

        .cancel-btn {
            display: inline-block;
            margin-left: 8px;
            padding: 11px 20px;
            border-radius: 6px;
            background: #64748b;
            color: white;
            text-decoration: none;
        }

        .success {
            color: #15803d;
            font-weight: bold;
        }

        .error {
            color: #dc2626;
            font-weight: bold;
        }

    </style>

</head>

<body>

<div class="container">

    <?php if ($successMessage !== ''): ?>

        <h2>Success!</h2>

        <p class="success">
            <?php echo htmlspecialchars($successMessage); ?>
        </p>

        <a href="manage_admin.php" class="cancel-btn">
            Back to Manage Admin
        </a>

    <?php else: ?>

        <h2>Delete Admin?</h2>

        <p>
            Are you sure you want to delete this Admin account?
            This action cannot be undone.
        </p>

        <div class="admin-info">

            <p>
                <strong>Name:</strong>
                <?php echo htmlspecialchars($admin['fullname']); ?>
            </p>

            <p>
                <strong>Admin ID:</strong>
                <?php echo htmlspecialchars($admin['studentid']); ?>
            </p>

        </div>

        <?php if ($errorMessage !== ''): ?>

            <p class="error">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>

        <?php endif; ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
            >

            <button type="submit" class="delete-btn">
                Yes, Delete Admin
            </button>

            <a href="manage_admin.php" class="cancel-btn">
                Cancel
            </a>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
