
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
// 3. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 4. LIVE ADMIN ROLE CHECK
// ========================================

$adminId = $_SESSION['student_id'];

$adminSql = "SELECT role FROM registration WHERE studentid = ? LIMIT 1";

$adminStmt = mysqli_prepare($conn, $adminSql);

mysqli_stmt_bind_param($adminStmt, "s", $adminId);

mysqli_stmt_execute($adminStmt);

$adminResult = mysqli_stmt_get_result($adminStmt);

$admin = mysqli_fetch_assoc($adminResult);

mysqli_stmt_close($adminStmt);

if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    exit("Access Denied! Only Admin can edit notices.");
}


// ========================================
// 5. CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ========================================
// 6. NOTICE ID VALIDATION
// ========================================

$noticeId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        "options" => [
            "min_range" => 1
        ]
    ]
);

if ($noticeId === false) {
    http_response_code(400);
    exit("Invalid Notice ID.");
}


// ========================================
// 7. FETCH NOTICE
// ========================================

$sql = "SELECT id, title, description
        FROM notices
        WHERE id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $noticeId);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$notice = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$notice) {
    mysqli_close($conn);

    http_response_code(404);
    exit("Notice not found.");
}


// ========================================
// 8. INITIAL VALUES
// ========================================

$error = "";

$title = $notice['title'];
$description = $notice['description'];


// ========================================
// 9. UPDATE NOTICE
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF TOKEN CHECK

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {

        $error = "Invalid request! Please refresh the page.";

    } else {

        // GET FORM DATA

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');


        // VALIDATION

        if ($title === '' || $description === '') {

            $error = "Please fill in all fields.";

        } elseif (mb_strlen($title, 'UTF-8') > 255) {

            $error = "Notice title cannot exceed 255 characters.";

        } else {

            // UPDATE QUERY

            $updateSql = "UPDATE notices
                          SET title = ?, description = ?
                          WHERE id = ?";

            $updateStmt = mysqli_prepare($conn, $updateSql);

            mysqli_stmt_bind_param(
                $updateStmt,
                "ssi",
                $title,
                $description,
                $noticeId
            );


            if (mysqli_stmt_execute($updateStmt)) {

                mysqli_stmt_close($updateStmt);
                mysqli_close($conn);

                header("Location: manage_notices.php?updated=1");
                exit;

            } else {

                $error = "Notice update failed. Please try again.";

            }

            mysqli_stmt_close($updateStmt);
        }
    }
}


// ========================================
// 10. SAFE OUTPUT FUNCTION
// ========================================

function safeOutput($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


mysqli_close($conn);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Notice | Student Management System</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        .container {
            max-width: 700px;
            margin: 30px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        h2 {
            color: #1e40af;
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        textarea {
            min-height: 180px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        .update-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }

        .update-btn:hover {
            background: #1d4ed8;
        }

        .back-btn {
            display: inline-block;
            margin-left: 10px;
            padding: 12px 18px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-btn:hover {
            background: #475569;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        @media (max-width: 500px) {

            .container {
                padding: 20px;
            }

            .back-btn {
                margin: 10px 0 0;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Edit Notice / নোটিশ সম্পাদনা</h2>


    <?php if ($error !== ''): ?>

        <div class="error">
            <?php echo safeOutput($error); ?>
        </div>

    <?php endif; ?>


    <form
        method="POST"
        action="edit_notice.php?id=<?php echo urlencode((string)$noticeId); ?>"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo safeOutput($_SESSION['csrf_token']); ?>"
        >


        <label>
            Notice Title / নোটিশের শিরোনাম
        </label>

        <input
            type="text"
            name="title"
            maxlength="255"
            value="<?php echo safeOutput($title); ?>"
            required
        >


        <label>
            Notice Description / নোটিশের বিবরণ
        </label>

        <textarea
            name="description"
            required
        ><?php echo safeOutput($description); ?></textarea>


        <button type="submit" class="update-btn">
            Update Notice / আপডেট করুন
        </button>


        <a href="manage_notices.php" class="back-btn">
            Cancel / ফিরে যান
        </a>

    </form>

</div>

</body>
</html>
