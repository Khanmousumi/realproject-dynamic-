
<?php

// ========================================
// 1. START SESSION
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. CHECK LOGIN
// ========================================

if (
    !isset($_SESSION['student_id']) ||
    !isset($_SESSION['role'])
) {
    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 3. DATABASE CONNECTION
// ========================================

require_once "../../config/db.php";


// ========================================
// 4. HELPER FUNCTION
// ========================================

function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}


// ========================================
// 5. CHECK USER FROM DATABASE
// ========================================

$student_id = $_SESSION['student_id'];

$stmt = $conn->prepare("
    SELECT studentid, password, role
    FROM registration
    WHERE studentid = ?
    LIMIT 1
");

$stmt->bind_param("s", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user || $user['role'] !== $_SESSION['role']) {
    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 6. CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];


// ========================================
// 7. VARIABLES
// ========================================

$success = "";
$error = "";


// ========================================
// 8. HANDLE FORM SUBMISSION
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $posted_token = $_POST['csrf_token'] ?? '';

    // CSRF CHECK
    if (
        !is_string($posted_token) ||
        !hash_equals($_SESSION['csrf_token'], $posted_token)
    ) {
        $error = "Invalid request. Please refresh the page.";

    // REQUIRED FIELD CHECK
    } elseif (
        $current_password === '' ||
        $new_password === '' ||
        $confirm_password === ''
    ) {
        $error = "All fields are required.";

    // CURRENT PASSWORD CHECK
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";

    // NEW PASSWORD LENGTH
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters.";

    // PASSWORD MATCH CHECK
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirm password do not match.";

    // SAME PASSWORD CHECK
    } elseif (password_verify($new_password, $user['password'])) {
        $error = "New password must be different from current password.";

    } else {

        // HASH NEW PASSWORD
        $hashed_password = password_hash(
            $new_password,
            PASSWORD_DEFAULT
        );

        // UPDATE PASSWORD
        $update = $conn->prepare("
            UPDATE registration
            SET password = ?
            WHERE studentid = ?
        ");

        $update->bind_param(
            "ss",
            $hashed_password,
            $student_id
        );

        if ($update->execute() && $update->affected_rows === 1) {

            // REGENERATE SESSION ID
            session_regenerate_id(true);

            // NEW CSRF TOKEN
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $success = "Password changed successfully!";

        } else {
            $error = "Password could not be changed. Please try again.";
        }

        $update->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Change Password</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            padding: 30px 15px;
        }

        .password-container {
            max-width: 550px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        h2 {
            color: #1e40af;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #64748b;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

    <div class="password-container">

        <h2>Change Password</h2>

        <p class="subtitle">
            পাসওয়ার্ড পরিবর্তন করুন
        </p>

        <?php if ($success !== ""): ?>

            <div class="message success">
                <?= clean($success); ?>
            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="message error">
                <?= clean($error); ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= clean($csrf_token); ?>"
            >

            <div class="form-group">

                <label>Current Password / বর্তমান পাসওয়ার্ড</label>

                <input
                    type="password"
                    name="current_password"
                    required
                    autocomplete="current-password"
                >

            </div>


            <div class="form-group">

                <label>New Password / নতুন পাসওয়ার্ড</label>

                <input
                    type="password"
                    name="new_password"
                    minlength="8"
                    required
                    autocomplete="new-password"
                >

            </div>


            <div class="form-group">

                <label>Confirm New Password / নতুন পাসওয়ার্ড নিশ্চিত করুন</label>

                <input
                    type="password"
                    name="confirm_password"
                    minlength="8"
                    required
                    autocomplete="new-password"
                >

            </div>


            <button type="submit">
                Update Password / পাসওয়ার্ড আপডেট
            </button>

        </form>


        <a href="admin_index.php" class="back-link">
            ← Back to Dashboard / ড্যাশবোর্ডে ফিরে যান
        </a>

    </div>

</body>

</html>
