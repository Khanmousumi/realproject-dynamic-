
<?php

// ========================================
// 1. START SESSION
// ========================================

if (session_status() !== PHP_SESSION_ACTIVE) {
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
    exit;
}


// ========================================
// 3. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 4. VERIFY ADMIN FROM DATABASE
// ========================================

$adminId = $_SESSION['student_id'];

$checkStmt = $conn->prepare(
    "SELECT role
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$checkStmt->bind_param("s", $adminId);
$checkStmt->execute();

$admin = $checkStmt->get_result()->fetch_assoc();

$checkStmt->close();

if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    exit("Access Denied! Admin only.");
}


// ========================================
// 5. CREATE CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ========================================
// 6. INITIALIZE VARIABLES
// ========================================

$error = "";
$success = "";

$title = "";
$description = "";


// ========================================
// 7. CREATE NOTICE
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify CSRF Token

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {

        http_response_code(403);
        exit("Invalid CSRF Token!");

    }


    // Get Form Data

    $title = trim($_POST['title'] ?? '');

    $description = trim($_POST['description'] ?? '');


    // Validate Data

    if ($title === '' || $description === '') {

        $error = "Please fill in all fields.";

    } elseif (mb_strlen($title) > 255) {

        $error = "Notice title cannot exceed 255 characters.";

    } else {

        // Insert Notice into Database

        $sql = "INSERT INTO notices
                (title, description, created_by)
                VALUES (?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sss",
            $title,
            $description,
            $adminId
        );

        if ($stmt->execute()) {

            $stmt->close();

            header("Location: create_notice.php?success=1");
            exit;

        } else {

            $error = "Notice could not be created. Please try again.";

            $stmt->close();

        }

    }

}


// ========================================
// 8. SUCCESS MESSAGE
// ========================================

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = "Notice published successfully!";
}

$conn->close();


// ========================================
// 9. SAFE OUTPUT
// ========================================

function safeOutput($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Notice</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f7fc;
            color: #1e293b;
        }

        .notice-container {
            max-width: 750px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #1e40af;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
            font-family: Arial, sans-serif;
            outline: none;
        }

        input:focus,
        textarea:focus {
            border-color: #2563eb;
        }

        textarea {
            min-height: 180px;
            resize: vertical;
        }

        .publish-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .publish-btn:hover {
            background: #1d4ed8;
        }

        .back-btn {
            display: inline-block;
            margin-left: 10px;
            padding: 12px 20px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 7px;
        }

        .error,
        .success {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 7px;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        @media (max-width: 600px) {

            body {
                padding: 12px;
            }

            .notice-container {
                padding: 20px;
            }

            .publish-btn,
            .back-btn {
                display: block;
                width: 100%;
                margin: 10px 0;
                text-align: center;
            }

        }

    </style>

</head>


<body>

<div class="notice-container">

    <h2>Create New Notice / নতুন নোটিশ তৈরি করুন</h2>


    <?php if ($error !== ''): ?>

        <div class="error">
            <?php echo safeOutput($error); ?>
        </div>

    <?php endif; ?>


    <?php if ($success !== ''): ?>

        <div class="success">
            <?php echo safeOutput($success); ?>
        </div>

    <?php endif; ?>


    <form method="POST" action="create_notice.php">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo safeOutput($_SESSION['csrf_token']); ?>"
        >


        <div class="form-group">

            <label>Notice Title / নোটিশের শিরোনাম</label>

            <input
                type="text"
                name="title"
                maxlength="255"
                placeholder="Enter notice title"
                value="<?php echo safeOutput($title); ?>"
                required
            >

        </div>


        <div class="form-group">

            <label>Notice Description / নোটিশের বিস্তারিত</label>

            <textarea
                name="description"
                placeholder="Write your notice here..."
                required
            ><?php echo safeOutput($description); ?></textarea>

        </div>


        <button type="submit" class="publish-btn">
            Publish Notice
        </button>


        <a href="admin_index.php" class="back-btn">
            Back to Dashboard
        </a>

    </form>

</div>

</body>

</html>
w