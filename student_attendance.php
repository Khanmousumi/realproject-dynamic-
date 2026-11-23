
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

$checkSql = "SELECT role, fullname
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
    exit("Access Denied!");
}


// ========================================
// 5. ATTENDANCE SUMMARY
// ========================================

$summarySql = "SELECT
                COUNT(*) AS total_classes,

                SUM(CASE
                    WHEN status = 'Present' THEN 1
                    ELSE 0
                END) AS total_present,

                SUM(CASE
                    WHEN status = 'Absent' THEN 1
                    ELSE 0
                END) AS total_absent

               FROM attendance
               WHERE studentid = ?";

$summaryStmt = mysqli_prepare($conn, $summarySql);

mysqli_stmt_bind_param($summaryStmt, "s", $studentId);

mysqli_stmt_execute($summaryStmt);

$summaryResult = mysqli_stmt_get_result($summaryStmt);

$summary = mysqli_fetch_assoc($summaryResult);

mysqli_stmt_close($summaryStmt);


// ========================================
// 6. CALCULATE ATTENDANCE PERCENTAGE
// ========================================

$totalClasses = (int)($summary['total_classes'] ?? 0);

$totalPresent = (int)($summary['total_present'] ?? 0);

$totalAbsent = (int)($summary['total_absent'] ?? 0);

$percentage = 0;

if ($totalClasses > 0) {

    $percentage = ($totalPresent / $totalClasses) * 100;
}


// ========================================
// 7. FETCH ATTENDANCE HISTORY
// ========================================

$historySql = "SELECT attendance_date, status
               FROM attendance
               WHERE studentid = ?
               ORDER BY attendance_date DESC";

$historyStmt = mysqli_prepare($conn, $historySql);

mysqli_stmt_bind_param($historyStmt, "s", $studentId);

mysqli_stmt_execute($historyStmt);

$historyResult = mysqli_stmt_get_result($historyStmt);

$attendanceRecords = [];

while ($row = mysqli_fetch_assoc($historyResult)) {

    $attendanceRecords[] = $row;
}

mysqli_stmt_close($historyStmt);


// ========================================
// 8. SAFE OUTPUT FUNCTION
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

    <title>My Attendance</title>

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
            max-width: 1000px;
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

        .summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 22px 15px;
            border-radius: 9px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .card h3 {
            font-size: 14px;
            color: #64748b;
            margin: 0 0 12px;
        }

        .card p {
            font-size: 27px;
            font-weight: bold;
            margin: 0;
            color: #1e40af;
        }

        .present {
            color: #16a34a !important;
        }

        .absent {
            color: #dc2626 !important;
        }

        .percentage {
            color: #7c3aed !important;
        }

        .history {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .history h3 {
            color: #1e40af;
            margin-top: 0;
            margin-bottom: 20px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 450px;
        }

        th, td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #eff6ff;
            color: #1e40af;
        }

        .status-present {
            color: #16a34a;
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

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 20px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .back-btn:hover {
            background: #475569;
        }

        @media (max-width: 750px) {

            .summary {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 400px) {

            .summary {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="container">


    <!-- HEADER -->

    <div class="header">

        <h2>My Attendance / আমার উপস্থিতি</h2>

        <p>
            Welcome, <?php echo safeOutput($student['fullname']); ?>
        </p>

        <p>
            Student ID: <?php echo safeOutput($studentId); ?>
        </p>

    </div>


    <!-- ATTENDANCE SUMMARY -->

    <div class="summary">


        <div class="card">

            <h3>Total Classes</h3>

            <p>
                <?php echo $totalClasses; ?>
            </p>

        </div>


        <div class="card">

            <h3>Present</h3>

            <p class="present">
                <?php echo $totalPresent; ?>
            </p>

        </div>


        <div class="card">

            <h3>Absent</h3>

            <p class="absent">
                <?php echo $totalAbsent; ?>
            </p>

        </div>


        <div class="card">

            <h3>Attendance Percentage</h3>

            <p class="percentage">
                <?php echo number_format($percentage, 2); ?>%
            </p>

        </div>


    </div>


    <!-- ATTENDANCE HISTORY -->

    <div class="history">

        <h3>Attendance History / উপস্থিতির ইতিহাস</h3>


        <?php if (!empty($attendanceRecords)): ?>

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>SL</th>

                            <th>Attendance Date</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($attendanceRecords as $index => $record): ?>

                            <tr>

                                <td>
                                    <?php echo $index + 1; ?>
                                </td>

                                <td>
                                    <?php
                                    echo safeOutput(
                                        date(
                                            'd M Y',
                                            strtotime($record['attendance_date'])
                                        )
                                    );
                                    ?>
                                </td>

                                <td>

                                    <?php if ($record['status'] === 'Present'): ?>

                                        <span class="status-present">
                                            Present
                                        </span>

                                    <?php else: ?>

                                        <span class="status-absent">
                                            Absent
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty">

                No Attendance Records Found.

                <br><br>

                এখনো তোমার কোনো Attendance Record তৈরি হয়নি।

            </div>

        <?php endif; ?>


    </div>


    <a href="admin_index.php" class="back-btn">
        Back to Dashboard
    </a>


</div>

</body>
</html>
