
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
// 4. VERIFY CURRENT USER FROM DATABASE
// ========================================

$currentStudentId = $_SESSION['student_id'];

$checkStmt = $conn->prepare(
    "SELECT role, is_main_admin
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$checkStmt->bind_param("s", $currentStudentId);
$checkStmt->execute();

$currentUser = $checkStmt->get_result()->fetch_assoc();

$checkStmt->close();


if (
    !$currentUser ||
    !in_array($currentUser['role'], ['admin', 'student'], true)
) {

    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit;
}


// Refresh Session from Database

$_SESSION['role'] = $currentUser['role'];

$_SESSION['is_main_admin'] = (
    $currentUser['role'] === 'admin' &&
    (int)$currentUser['is_main_admin'] === 1
) ? 1 : 0;


// ========================================
// 5. CREATE CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ========================================
// 6. SEARCH FUNCTIONALITY
// ========================================

$search = trim($_GET['search'] ?? '');


// ========================================
// 7. ADMIN: SHOW ALL STUDENTS
// ========================================

if ($_SESSION['role'] === 'admin') {

    if ($search !== '') {

        $sql = "SELECT studentid, fullname, email, department
                FROM registration
                WHERE role = 'student'
                AND (studentid LIKE ? OR fullname LIKE ?)
                ORDER BY fullname ASC";

        $stmt = $conn->prepare($sql);

        $keyword = "%{$search}%";

        $stmt->bind_param("ss", $keyword, $keyword);

    } else {

        $sql = "SELECT studentid, fullname, email, department
                FROM registration
                WHERE role = 'student'
                ORDER BY fullname ASC";

        $stmt = $conn->prepare($sql);
    }


// ========================================
// 8. STUDENT: SHOW OWN PROFILE ONLY
// ========================================

} else {

    if ($search !== '') {

        $sql = "SELECT studentid, fullname, email, department
                FROM registration
                WHERE studentid = ?
                AND role = 'student'
                AND (studentid LIKE ? OR fullname LIKE ?)
                ORDER BY fullname ASC";

        $stmt = $conn->prepare($sql);

        $keyword = "%{$search}%";

        $stmt->bind_param(
            "sss",
            $currentStudentId,
            $keyword,
            $keyword
        );

    } else {

        $sql = "SELECT studentid, fullname, email, department
                FROM registration
                WHERE studentid = ?
                AND role = 'student'
                ORDER BY fullname ASC";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("s", $currentStudentId);
    }
}


// Execute Query

$stmt->execute();

$result = $stmt->get_result();

$stmt->close();


// ========================================
// 9. DISPLAY MESSAGES
// ========================================

$message = '';

if (isset($_GET['deleted'])) {

    if ($_GET['deleted'] === '1') {
        $message = 'Student deleted successfully.';
    } else {
        $message = 'Student was not deleted. Please check the Student ID.';
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Students List</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #f4f7fc;
            color: #1e293b;
        }

        .students-section {
            width: 100%;
        }

        .students-section h2 {
            margin: 22px 0;
            font-size: 28px;
            color: #1e293b;
        }

        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-form input {
            width: 375px;
            max-width: 100%;
            padding: 13px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 15px;
            outline: none;
        }

        .search-form input:focus {
            border-color: #2563eb;
        }

        .search-form button {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .search-form button:hover {
            background: #1d4ed8;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .students-table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
            background: white;
        }

        .students-table th,
        .students-table td {
            padding: 15px 18px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .students-table th {
            background: #2563eb;
            color: white;
            font-size: 16px;
            white-space: nowrap;
        }

        .students-table tr:hover td {
            background: #f8fafc;
        }

        .students-table tr:last-child td {
            border-bottom: none;
        }

        .action-cell {
            white-space: nowrap;
        }

        .edit-btn,
        .delete-btn {
            display: inline-block;
            padding: 8px 13px;
            border: none;
            border-radius: 5px;
            color: white;
            text-decoration: none;
            font-size: 14px;
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
            margin: 0 0 0 5px;
        }

        .view-btn {
    display: inline-block;
    padding: 8px 13px;
    border: none;
    border-radius: 5px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
}

.view-btn:hover {
    background: #1d4ed8;
}

        .message {
            margin: 15px 0;
            padding: 12px 15px;
            background: #dcfce7;
            color: #166534;
            border-radius: 7px;
        }

        .empty-message {
            text-align: center !important;
            padding: 25px !important;
            color: #64748b;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .students-section h2 {
                font-size: 23px;
            }

            .search-form input {
                width: 100%;
            }

            .search-form button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

<div class="students-section">

    <form method="GET" action="dashboard.php" class="search-form">
        <input
            type="text"
            name="search"
            placeholder="Search by Student ID or Name"
            value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
        >

        <button type="submit">Search</button>
    </form>

    <h2>All Students</h2>

    <?php if ($message !== ''): ?>
        <div class="message">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="table-wrapper">
        <table class="students-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>

                    <?php while ($row = mysqli_fetch_assoc($result)): ?>

                        <tr>
                            <td>
                                <?php echo htmlspecialchars($row['studentid'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['fullname'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($row['department'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td class="action-cell">

                            <a
    class="view-btn"
    href="student_details.php?id=<?php echo urlencode($row['studentid']); ?>"
>View Details</a>

                                <a
                                    class="edit-btn"
                                    href="edit_student.php?id=<?php echo urlencode($row['studentid']); ?>"
                                >Edit</a>

                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>

                                    <form
                                        class="delete-form"
                                        action="delete_student.php"
                                        method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this student?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="studentid"
                                            value="<?php echo htmlspecialchars($row['studentid'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="delete-btn"
                                        >Delete</button>
                                    </form>

                                <?php endif; ?>

                            </td>
                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="5" class="empty-message">
                            No students found.
                        </td>
                    </tr>

                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
```
