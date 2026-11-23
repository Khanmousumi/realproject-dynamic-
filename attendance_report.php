
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
    exit("Access Denied! Only Admin can view attendance reports.");
}


// ========================================
// 5. SEARCH FILTERS
// ========================================

$searchDate = trim($_GET['date'] ?? '');
$searchStudentId = trim($_GET['studentid'] ?? '');

$error = "";
$records = [];


// ========================================
// 6. DATE VALIDATION
// ========================================

if ($searchDate !== '') {

    $dateObject = DateTime::createFromFormat('!Y-m-d', $searchDate);

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $searchDate
    ) {
        $error = "Invalid date format.";
    }
}


// ========================================
// 7. FETCH ATTENDANCE REPORT
// ========================================

if ($error === '') {

    $sql = "SELECT
                attendance.studentid,
                registration.fullname,
                attendance.attendance_date,
                attendance.status,
                attendance.marked_by
            FROM attendance
            INNER JOIN registration
                ON attendance.studentid = registration.studentid
            WHERE 1 = 1";

    $types = "";
    $params = [];


    // DATE FILTER

    if ($searchDate !== '') {

        $sql .= " AND attendance.attendance_date = ?";

        $types .= "s";
        $params[] = $searchDate;
    }


// STUDENT ID OR NAME FILTER

if ($searchStudentId !== '') {

    $sql .= " AND (
                attendance.studentid LIKE ?
                OR registration.fullname LIKE ?
              )";

    $types .= "ss";

    $searchValue = "%" . $searchStudentId . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
}




    // ORDER BY

    $sql .= " ORDER BY attendance.attendance_date DESC,
                       registration.fullname ASC";


    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {

        if (!empty($params)) {

            mysqli_stmt_bind_param(
                $stmt,
                $types,
                ...$params
            );
        }

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $records[] = $row;
        }

        mysqli_stmt_close($stmt);

    } else {

        $error = "Unable to load attendance report.";
    }
}


// ========================================
// 8. ATTENDANCE SUMMARY
// ========================================

$totalPresent = 0;
$totalAbsent = 0;

foreach ($records as $record) {

    if ($record['status'] === 'Present') {
        $totalPresent++;
    } elseif ($record['status'] === 'Absent') {
        $totalAbsent++;
    }
}

$totalRecords = count($records);


// ========================================
// 9. SAFE OUTPUT FUNCTION
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

    <title>Attendance Report</title>

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
            max-width: 1100px;
            margin: 20px auto;
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        }

        h2 {
            text-align: center;
            color: #1e40af;
            margin-bottom: 25px;
        }

        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 25px;
            align-items: end;
        }

        .form-group {
            flex: 1;
            min-width: 180px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }

        button {
            padding: 11px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        button:hover {
            background: #1d4ed8;
        }

        .reset-btn {
            display: inline-block;
            padding: 11px 18px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .summary-card {
            padding: 18px;
            border-radius: 8px;
            background: #eff6ff;
            text-align: center;
        }

        .summary-card h3 {
            margin: 0 0 10px;
            color: #334155;
            font-size: 15px;
        }

        .summary-card p {
            margin: 0;
            font-size: 25px;
            font-weight: bold;
            color: #1e40af;
        }

        .present {
            color: #15803d !important;
        }

        .absent {
            color: #dc2626 !important;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #eff6ff;
            color: #1e40af;
        }

        .status-present {
            color: #15803d;
            font-weight: bold;
        }

        .status-absent {
            color: #dc2626;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }

        .error {
            padding: 13px;
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {

            .container {
                padding: 17px;
            }

            .summary {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <h2>Attendance Report / উপস্থিতির রিপোর্ট</h2>


    <!-- SEARCH FORM -->

    <form method="GET" class="search-form">

        <div class="form-group">

            <label>
                Attendance Date / তারিখ
            </label>

            <input
                type="date"
                name="date"
                value="<?php echo safeOutput($searchDate); ?>"
            >

        </div>


        <div class="form-group">

            <label>
                Student ID / শিক্ষার্থী আইডি
            </label>

            <input
                type="text"
                name="studentid"
                placeholder="Enter Student ID"
                value="<?php echo safeOutput($searchStudentId); ?>"
            >

        </div>


        <button type="submit">
            Search Report
        </button>


        <a href="attendance_report.php" class="reset-btn">
            Reset
        </a>

    </form>


    <!-- ERROR MESSAGE -->

    <?php if ($error !== ''): ?>

        <div class="error">
            <?php echo safeOutput($error); ?>
        </div>

    <?php else: ?>


        <!-- ATTENDANCE SUMMARY -->

        <div class="summary">

            <div class="summary-card">

                <h3>Total Records</h3>

                <p>
                    <?php echo $totalRecords; ?>
                </p>

            </div>


            <div class="summary-card">

                <h3>Present</h3>

                <p class="present">
                    <?php echo $totalPresent; ?>
                </p>

            </div>


            <div class="summary-card">

                <h3>Absent</h3>

                <p class="absent">
                    <?php echo $totalAbsent; ?>
                </p>

            </div>

        </div>


        <!-- REPORT TABLE -->

        <?php if (!empty($records)): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Attendance Date</th>
                            <th>Status</th>
                            <th>Marked By</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($records as $row): ?>

                            <tr>

                                <td>
                                    <?php echo safeOutput($row['studentid']); ?>
                                </td>

                                <td>
                                    <?php echo safeOutput($row['fullname']); ?>
                                </td>

                                <td>
                                    <?php echo safeOutput($row['attendance_date']); ?>
                                </td>

                                <td>

                                    <?php if ($row['status'] === 'Present'): ?>

                                        <span class="status-present">
                                            Present
                                        </span>

                                    <?php else: ?>

                                        <span class="status-absent">
                                            Absent
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>
                                    <?php echo safeOutput($row['marked_by']); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">
                No attendance records found.
                <br>
                কোনো Attendance Record পাওয়া যায়নি।
            </div>

        <?php endif; ?>

    <?php endif; ?>

</div>

</body>
</html>
