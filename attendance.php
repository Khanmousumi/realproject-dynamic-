
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
    exit("Access Denied! Only Admin can manage attendance.");
}


// ========================================
// 5. CSRF TOKEN
// ========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ========================================
// 6. SELECT ATTENDANCE DATE
// ========================================

$selectedDate = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedDate = $_POST['attendance_date'] ?? date('Y-m-d');
}


// Validate date format

$dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);

if (
    !$dateObject ||
    $dateObject->format('Y-m-d') !== $selectedDate
) {
    mysqli_close($conn);
    exit("Invalid attendance date.");
}


// ========================================
// 7. FETCH ALL STUDENTS
// ========================================

$studentSql = "SELECT studentid, fullname
               FROM registration
               WHERE role = 'student'
               ORDER BY fullname ASC";

$studentResult = mysqli_query($conn, $studentSql);

if (!$studentResult) {
    mysqli_close($conn);
    exit("Unable to load students.");
}

$students = [];

while ($row = mysqli_fetch_assoc($studentResult)) {
    $students[] = $row;
}


// ========================================
// 8. SAVE ATTENDANCE
// ========================================

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF CHECK

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {

        $error = "Invalid request! Please refresh the page.";

    } else {

        $attendanceData = $_POST['attendance'] ?? [];

        if (!is_array($attendanceData)) {

            $error = "Invalid attendance data.";

        } elseif (empty($students)) {

            $error = "No students available.";

        } else {

            // Check that every student has a valid status

            foreach ($students as $student) {

                $id = $student['studentid'];

                $status = $attendanceData[$id] ?? '';

                if (
                    !is_string($status) ||
                    !in_array($status, ['Present', 'Absent'], true)
                ) {
                    $error = "Please select attendance for every student.";
                    break;
                }
            }


            // Save if all statuses are valid

            if ($error === '') {

                mysqli_begin_transaction($conn);

                try {

                    $saveSql = "INSERT INTO attendance
                                (studentid, attendance_date, status, marked_by)
                                VALUES (?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                status = VALUES(status),
                                marked_by = VALUES(marked_by)";

                    $saveStmt = mysqli_prepare($conn, $saveSql);

                    if (!$saveStmt) {
                        throw new Exception("Database query preparation failed.");
                    }

                    foreach ($students as $student) {

                        $id = $student['studentid'];

                        $status = $attendanceData[$id];

                        mysqli_stmt_bind_param(
                            $saveStmt,
                            "ssss",
                            $id,
                            $selectedDate,
                            $status,
                            $adminId
                        );

                        if (!mysqli_stmt_execute($saveStmt)) {
                            throw new Exception("Attendance save failed.");
                        }
                    }

                    mysqli_stmt_close($saveStmt);

                    mysqli_commit($conn);

                    mysqli_close($conn);

                    header(
                        "Location: attendance.php?date=" .
                        urlencode($selectedDate) .
                        "&saved=1"
                    );

                    exit;

                } catch (Throwable $e) {

                    mysqli_rollback($conn);

                    if (isset($saveStmt) && $saveStmt) {
                        mysqli_stmt_close($saveStmt);
                    }

                    $error = "Attendance could not be saved. Please try again.";
                }
            }
        }
    }
}


// ========================================
// 9. FETCH EXISTING ATTENDANCE
// ========================================

$existingSql = "SELECT studentid, status
                FROM attendance
                WHERE attendance_date = ?";

$existingStmt = mysqli_prepare($conn, $existingSql);

mysqli_stmt_bind_param($existingStmt, "s", $selectedDate);

mysqli_stmt_execute($existingStmt);

$existingResult = mysqli_stmt_get_result($existingStmt);

$existingAttendance = [];

while ($row = mysqli_fetch_assoc($existingResult)) {

    $existingAttendance[$row['studentid']] = $row['status'];
}

mysqli_stmt_close($existingStmt);


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

    <title>Manage Attendance</title>

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

        .date-section {
            margin-bottom: 25px;
        }

        .date-section label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input[type="date"] {
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        .date-btn {
            padding: 10px 16px;
            border: none;
            background: #2563eb;
            color: white;
            border-radius: 6px;
            cursor: pointer;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 550px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }

        th {
            background: #eff6ff;
            color: #1e40af;
        }

        .status-option {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-right: 12px;
            cursor: pointer;
        }

        .save-btn {
            margin-top: 22px;
            padding: 12px 22px;
            background: #16a34a;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }

        .save-btn:hover {
            background: #15803d;
        }

        .success, .error {
            padding: 13px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
        }

        .empty {
            text-align: center;
            padding: 25px;
            color: #64748b;
        }

        @media (max-width: 600px) {

            .container {
                padding: 17px;
            }

            .date-btn {
                margin-top: 10px;
            }
        }

    </style>

</head>

<body>

<div class="container">

    <h2>Attendance Management / উপস্থিতি ব্যবস্থাপনা</h2>


    <!-- SUCCESS MESSAGE -->

    <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>

        <div class="success">
            Attendance saved successfully! / উপস্থিতি সফলভাবে সংরক্ষিত হয়েছে।
        </div>

    <?php endif; ?>


    <!-- ERROR MESSAGE -->

    <?php if ($error !== ''): ?>

        <div class="error">
            <?php echo safeOutput($error); ?>
        </div>

    <?php endif; ?>


    <!-- DATE SELECTION -->

    <form method="GET" class="date-section">

        <label>
            Attendance Date / উপস্থিতির তারিখ
        </label>

        <input
            type="date"
            name="date"
            value="<?php echo safeOutput($selectedDate); ?>"
            required
        >

        <button type="submit" class="date-btn">
            Show Students
        </button>

    </form>


    <!-- ATTENDANCE FORM -->

    <?php if (!empty($students)): ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo safeOutput($_SESSION['csrf_token']); ?>"
            >

            <input
                type="hidden"
                name="attendance_date"
                value="<?php echo safeOutput($selectedDate); ?>"
            >


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Attendance Status</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($students as $student): ?>

                            <?php

                            $id = $student['studentid'];

                            $currentStatus = $existingAttendance[$id] ?? '';

                            ?>

                            <tr>

                                <td>
                                    <?php echo safeOutput($id); ?>
                                </td>

                                <td>
                                    <?php echo safeOutput($student['fullname']); ?>
                                </td>

                                <td>

                                    <label class="status-option">

                                        <input
                                            type="radio"
                                            name="attendance[<?php echo safeOutput($id); ?>]"
                                            value="Present"
                                            <?php echo ($currentStatus === 'Present') ? 'checked' : ''; ?>
                                            required
                                        >

                                        Present

                                    </label>


                                    <label class="status-option">

                                        <input
                                            type="radio"
                                            name="attendance[<?php echo safeOutput($id); ?>]"
                                            value="Absent"
                                            <?php echo ($currentStatus === 'Absent') ? 'checked' : ''; ?>
                                            required
                                        >

                                        Absent

                                    </label>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <button type="submit" class="save-btn">
                Save Attendance / উপস্থিতি সংরক্ষণ করুন
            </button>

        </form>

    <?php else: ?>

        <div class="empty">
            No students found! / কোনো শিক্ষার্থী পাওয়া যায়নি।
        </div>

    <?php endif; ?>


</div>

</body>
</html>
