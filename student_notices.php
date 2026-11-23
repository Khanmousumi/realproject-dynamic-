
<?php

// ========================================
// 1. SESSION START
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. STUDENT LOGIN CHECK
// ========================================

if (
    !isset($_SESSION['student_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'student'
) {
    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 3. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 4. LIVE STUDENT ROLE CHECK
// ========================================

$studentId = $_SESSION['student_id'];

$checkSql = "SELECT role
             FROM registration
             WHERE studentid = ?
             LIMIT 1";

$checkStmt = mysqli_prepare($conn, $checkSql);

mysqli_stmt_bind_param($checkStmt, "s", $studentId);

mysqli_stmt_execute($checkStmt);

$checkResult = mysqli_stmt_get_result($checkStmt);

$student = mysqli_fetch_assoc($checkResult);

mysqli_stmt_close($checkStmt);


if (!$student || $student['role'] !== 'student') {

    mysqli_close($conn);

    http_response_code(403);
    exit("Access Denied! Only Students can view this page.");
}


// ========================================
// 5. FETCH ALL NOTICES
// ========================================

$sql = "SELECT id, title, description, created_at
        FROM notices
        ORDER BY created_at DESC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    mysqli_close($conn);
    exit("Unable to load notices. Please try again.");
}


// ========================================
// 6. SAFE OUTPUT FUNCTION
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

    <title>Student Notice Board</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px 15px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        .container {
            max-width: 900px;
            margin: 20px auto;
        }

        .header {
            background: #2563eb;
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }

        .header h2 {
            margin: 0 0 10px;
        }

        .header p {
            margin: 0;
        }

        .notice-card {
            background: white;
            padding: 22px;
            margin-bottom: 18px;
            border-radius: 8px;
            border-left: 5px solid #2563eb;
            box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        }

        .notice-card h3 {
            color: #1e40af;
            margin-top: 0;
            margin-bottom: 12px;
        }

        .notice-description {
            color: #334155;
            line-height: 1.7;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .notice-date {
            color: #64748b;
            font-size: 13px;
            margin-top: 15px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }

        .empty-message {
            background: white;
            padding: 30px;
            text-align: center;
            border-radius: 8px;
            color: #64748b;
        }

        .back-btn {
            display: inline-block;
            margin-top: 15px;
            padding: 11px 20px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-btn:hover {
            background: #475569;
        }

        @media (max-width: 500px) {

            .notice-card {
                padding: 17px;
            }

            .header {
                padding: 20px;
            }
        }

    </style>

</head>

<body>

<div class="container">


    <!-- PAGE HEADER -->

    <div class="header">

        <h2>Notice Board / নোটিশ বোর্ড</h2>

        <p>
            Important announcements for students.
            শিক্ষার্থীদের জন্য গুরুত্বপূর্ণ বিজ্ঞপ্তি।
        </p>

    </div>


    <!-- NOTICE LIST -->

    <?php if (mysqli_num_rows($result) > 0): ?>

        <?php while ($row = mysqli_fetch_assoc($result)): ?>

            <div class="notice-card">

                <h3>
                    <?php echo safeOutput($row['title']); ?>
                </h3>

                <div class="notice-description">
                    <?php echo safeOutput($row['description']); ?>
                </div>

                <div class="notice-date">

                    Published Date / প্রকাশের তারিখ:

                    <?php
                    echo safeOutput(
                        date(
                            'd M Y, h:i A',
                            strtotime($row['created_at'])
                        )
                    );
                    ?>

                </div>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty-message">

            <h3>No Notices Available</h3>

            <p>বর্তমানে কোনো নোটিশ প্রকাশ করা হয়নি।</p>

        </div>

    <?php endif; ?>


    <a href="admin_index.php" class="back-btn">
        Back to Dashboard / ড্যাশবোর্ডে ফিরে যান
    </a>


</div>

</body>
</html>

<?php

mysqli_free_result($result);
mysqli_close($conn);

?>
