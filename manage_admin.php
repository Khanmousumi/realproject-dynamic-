
<?php

// ========================================
// 1. SESSION START
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. ADMIN PERMISSION CHECK
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
// 4. VERIFY CURRENT ADMIN
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

$isCurrentMainAdmin = (
    (int)$currentAdmin['is_main_admin'] === 1
);


// ========================================
// 5. SEARCH
// ========================================

$search = trim($_GET['search'] ?? '');


// ========================================
// 6. FETCH ADMIN DATA
// ========================================

if ($search !== '') {

    $sql = "SELECT id, fullname, studentid, email,
                   department, phone, reg_time, is_main_admin
            FROM registration
            WHERE role = 'admin'
            AND (
                fullname LIKE ?
                OR studentid LIKE ?
                OR email LIKE ?
            )
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);

    $searchValue = "%{$search}%";

    $stmt->bind_param(
        "sss",
        $searchValue,
        $searchValue,
        $searchValue
    );

} else {

    $sql = "SELECT id, fullname, studentid, email,
                   department, phone, reg_time, is_main_admin
            FROM registration
            WHERE role = 'admin'
            ORDER BY id DESC";

    $stmt = $conn->prepare($sql);
}

$stmt->execute();

$result = $stmt->get_result();

$totalAdmins = $result->num_rows;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Admin | Dynamic</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f7fc;
            color: #263445;
            padding: 30px;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        .header h1 {
            color: #1e3a8a;
            font-size: 27px;
            margin-bottom: 7px;
        }

        .header p {
            color: #64748b;
        }

        .back-btn {
            text-decoration: none;
            background: #2563eb;
            color: white;
            padding: 11px 18px;
            border-radius: 7px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            margin-bottom: 22px;
        }

        .stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stats h2 {
            color: #2563eb;
            font-size: 30px;
            margin-top: 8px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-form input {
            flex: 1;
            min-width: 0;
            padding: 12px;
            border: 1px solid #dbe3ef;
            border-radius: 7px;
            font-size: 15px;
        }

        .search-form button {
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 7px;
            cursor: pointer;
        }

        .search-form a {
            text-decoration: none;
            padding: 12px 15px;
            background: #e2e8f0;
            color: #334155;
            border-radius: 7px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #edf0f5;
        }

        th {
            background: #eff6ff;
            color: #1e40af;
            font-size: 13px;
        }

        td {
            font-size: 14px;
        }

        .admin-badge {
            background: #dcfce7;
            color: #15803d;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .main-admin-badge {
            background: #fef3c7;
            color: #92400e;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .view-btn,
        .edit-btn,
        .delete-btn {
            display: inline-block;
            text-decoration: none;
            color: white;
            padding: 8px 13px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: bold;
            margin: 2px;
        }

        .view-btn {
            background: #2563eb;
        }

        .view-btn:hover {
            background: #1e40af;
        }

        .edit-btn {
            background: #f59e0b;
        }

        .edit-btn:hover {
            background: #d97706;
        }

        .delete-btn {
            background: #dc2626;
        }

        .delete-btn:hover {
            background: #991b1b;
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #64748b;
        }

        @media (max-width: 600px) {

            body {
                padding: 15px;
            }

            .card {
                padding: 17px;
            }

            .header h1 {
                font-size: 23px;
            }

            .search-form {
                flex-wrap: wrap;
            }

            .search-form input {
                flex-basis: 100%;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- Header -->

    <div class="header">

        <div>
            <h1>Manage Admin</h1>
            <p>Manage and view all administrator accounts.</p>
        </div>

        <a href="admin_index.php" class="back-btn">
            ← Back to Dashboard
        </a>

    </div>


    <!-- Total Admins -->

    <div class="card stats">

        <div>
            <p>Total Admin Accounts</p>
            <h2><?php echo $totalAdmins; ?></h2>
        </div>

        <span>♙</span>

    </div>


    <!-- Admin List -->

    <div class="card">

        <h3 style="margin-bottom: 20px;">
            Administrator List
        </h3>


        <!-- Search Form -->

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                placeholder="Search by name, Admin ID or email..."
                value="<?php echo htmlspecialchars($search); ?>"
            >

            <button type="submit">Search</button>

            <a href="manage_admin.php">Reset</a>

        </form>


        <!-- Admin Table -->

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>SL</th>
                        <th>Full Name</th>
                        <th>Admin ID</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>

                </thead>

                <tbody>

                    <?php if ($totalAdmins > 0): ?>

                        <?php $sl = 1; ?>

                        <?php while ($admin = $result->fetch_assoc()): ?>

                            <?php
                                $isTargetMainAdmin =
                                    (int)$admin['is_main_admin'] === 1;

                                $isOwnAccount =
                                    $admin['studentid'] === $currentStudentId;
                            ?>

                            <tr>

                                <td>
                                    <?php echo $sl++; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($admin['fullname']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($admin['studentid']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($admin['email']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($admin['department']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($admin['phone']); ?>
                                </td>

                                <td>

                                    <?php if ($isTargetMainAdmin): ?>

                                        <span class="main-admin-badge">
                                            MAIN ADMIN
                                        </span>

                                    <?php else: ?>

                                        <span class="admin-badge">
                                            ADMIN
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <a
                                        href="admin_details.php?id=<?php echo (int)$admin['id']; ?>"
                                        class="view-btn"
                                    >
                                        View Details
                                    </a>

                                    <?php if (!$isTargetMainAdmin || ($isCurrentMainAdmin && $isOwnAccount)): ?>

                                        <a
                                            href="edit_admin.php?id=<?php echo (int)$admin['id']; ?>"
                                            class="edit-btn"
                                        >
                                            Edit
                                        </a>

                                    <?php endif; ?>

                                    <?php if (!$isTargetMainAdmin && !$isOwnAccount): ?>

                                        <a
                                            href="delete_admin.php?id=<?php echo (int)$admin['id']; ?>"
                                            class="delete-btn"
                                        >
                                            Delete
                                        </a>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="8" class="empty">
                                No Admin Account Found.
                            </td>

                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>

</body>
</html>

<?php

$stmt->close();
$conn->close();

?>
