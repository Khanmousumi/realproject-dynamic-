
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

$currentAdmin = $checkStmt->get_result()->fetch_assoc();

$checkStmt->close();

if (!$currentAdmin || $currentAdmin['role'] !== 'admin') {
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
// 6. FETCH ALL NOTICES
// ========================================

$sql = "SELECT
            id,
            title,
            description,
            created_by,
            created_at,
            updated_at
        FROM notices
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();

$result = $stmt->get_result();

$stmt->close();


// ========================================
// 7. DISPLAY MESSAGES
// ========================================

$message = "";
$messageType = "";

if (isset($_GET['created']) && $_GET['created'] === '1') {
    $message = "Notice created successfully!";
    $messageType = "success";
}

if (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = "Notice updated successfully!";
    $messageType = "success";
}

if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
    $message = "Notice deleted successfully!";
    $messageType = "success";
}


// ========================================
// 8. SAFE OUTPUT
// ========================================

function safeOutput($value) {
    return htmlspecialchars(
        (string)($value ?? ''),
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

    <title>Manage Notices</title>

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
            width: 100%;
        }

        h2 {
            margin: 20px 0;
            font-size: 27px;
            color: #1e293b;
        }

        .top-actions {
            margin-bottom: 20px;
        }

        .create-btn,
        .back-btn {
            display: inline-block;
            padding: 11px 18px;
            border-radius: 7px;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .create-btn {
            background: #2563eb;
        }

        .create-btn:hover {
            background: #1d4ed8;
        }

        .back-btn {
            margin-left: 7px;
            background: #64748b;
        }

        .back-btn:hover {
            background: #475569;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #2563eb;
            color: white;
            white-space: nowrap;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .description-cell {
            max-width: 300px;
            overflow-wrap: anywhere;
        }

        .edit-btn,
        .delete-btn {
            display: inline-block;
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
        }

        .edit-btn {
            background: #16a34a;
        }

        .edit-btn:hover {
            background: #15803d;
        }

        .delete-btn {
            background: #dc2626;
        }

        .delete-btn:hover {
            background: #b91c1c;
        }

        .delete-form {
            display: inline-block;
            margin-left: 5px;
        }

        .message {
            margin: 15px 0;
            padding: 12px 15px;
            border-radius: 7px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .empty-message {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }

        @media (max-width: 600px) {

            body {
                padding: 12px;
            }

            h2 {
                font-size: 23px;
            }

            .create-btn,
            .back-btn {
                margin: 5px 0;
            }

        }

    </style>

</head>


<body>

<div class="notice-container">

    <h2>Manage Notices / নোটিশ পরিচালনা</h2>


    <div class="top-actions">

        <a href="create_notice.php" class="create-btn">
            + Create New Notice
        </a>

        <a href="admin_index.php" class="back-btn">
            Back to Dashboard
        </a>

    </div>


    <?php if ($message !== ''): ?>

        <div class="message <?php echo safeOutput($messageType); ?>">
            <?php echo safeOutput($message); ?>
        </div>

    <?php endif; ?>


    <div class="table-wrapper">

        <table>

            <thead>

                <tr>
                    <th>Notice ID</th>
                    <th>Notice Title</th>
                    <th>Description</th>
                    <th>Created By</th>
                    <th>Published Date</th>
                    <th>Action</th>
                </tr>

            </thead>


            <tbody>

                <?php if ($result && $result->num_rows > 0): ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?php echo safeOutput($row['id']); ?>
                            </td>

                            <td>
                                <?php echo safeOutput($row['title']); ?>
                            </td>

                            <td class="description-cell">
                                <?php echo safeOutput($row['description']); ?>
                            </td>

                            <td>
                                <?php echo safeOutput($row['created_by']); ?>
                            </td>

                            <td>
                                <?php echo safeOutput($row['created_at']); ?>
                            </td>

                            <td>

                                <a
                                    href="edit_notice.php?id=<?php echo urlencode($row['id']); ?>"
                                    class="edit-btn"
                                >
                                    Edit
                                </a>


                                <form
                                    action="delete_notice.php"
                                    method="POST"
                                    class="delete-form"
                                    onsubmit="return confirm('Are you sure you want to delete this notice?');"
                                >

                                    <input
                                        type="hidden"
                                        name="id"
                                        value="<?php echo safeOutput($row['id']); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo safeOutput($_SESSION['csrf_token']); ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="delete-btn"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty-message">
                            No notices found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>
