
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
    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 3. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 4. VERIFY CURRENT USER
// ========================================

$currentStudentId = $_SESSION['student_id'];

$checkStmt = $conn->prepare(
    "SELECT role
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

$_SESSION['role'] = $currentUser['role'];


// ========================================
// 5. GET STUDENT ID
// ========================================

$studentId = trim($_GET['id'] ?? '');

if ($studentId === '') {
    http_response_code(400);
    exit("Invalid Student ID.");
}


// ========================================
// 6. FETCH STUDENT DETAILS
// ========================================

if ($_SESSION['role'] === 'admin') {

    $sql = "SELECT *
            FROM registration
            WHERE studentid = ?
            AND role = 'student'
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $studentId);

} else {

    $sql = "SELECT *
            FROM registration
            WHERE studentid = ?
            AND studentid = ?
            AND role = 'student'
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $studentId, $currentStudentId);
}

$stmt->execute();

$student = $stmt->get_result()->fetch_assoc();

$stmt->close();


// ========================================
// 7. CHECK STUDENT EXISTS
// ========================================

if (!$student) {
    http_response_code(404);
    exit("Student not found.");
}

$conn->close();


// ========================================
// 8. HELPER FOR SAFE OUTPUT
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

    <title>Student Details</title>

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

        .details-container {
            max-width: 850px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
        }

        h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #1e293b;
            text-align: center;
        }

        .profile-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-section img {
            width: 130px;
            height: 130px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #dbeafe;
        }

        .profile-section h3 {
            margin-bottom: 5px;
            color: #1d4ed8;
        }

        .profile-section p {
            margin-top: 0;
            color: #64748b;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .detail-item {
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .detail-item strong {
            display: block;
            margin-bottom: 7px;
            color: #2563eb;
            font-size: 14px;
        }

        .detail-item span {
            color: #334155;
            font-size: 15px;
            overflow-wrap: anywhere;
        }

        .back-btn {
            display: inline-block;
            margin-top: 25px;
            padding: 11px 22px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 7px;
        }

        .back-btn:hover {
            background: #1d4ed8;
        }

        .print-btn {
    display: inline-block;
    margin-top: 25px;
    margin-right: 10px;
    padding: 11px 22px;
    background: #16a34a;
    color: white;
    border: none;
    border-radius: 7px;
    font-size: 15px;
    cursor: pointer;
}

.print-btn:hover {
    background: #15803d;
}

        @media (max-width: 600px) {

            body {
                padding: 12px;
            }

            .details-container {
                padding: 20px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

        }

        @media print {

    body {
        background: white;
        padding: 0;
    }

    .details-container {
        box-shadow: none;
        margin: 0;
        max-width: 100%;
    }

    .print-btn,
    .back-btn {
        display: none;
    }

}

    </style>

</head>

<body>

<div class="details-container">

    <h2>Student Details</h2>

    <div class="profile-section">

        <?php

        $imageName = basename($student['profile_image'] ?? '');

        $imagePath = "../../Uploads_Photo/" . $imageName;

        $defaultImage = "../../Uploads_Photo/default.png";

        if (
            $imageName !== '' &&
            is_file(__DIR__ . "/../../Uploads_Photo/" . $imageName)
        ) {
            $profileImage = $imagePath;
        } else {
            $profileImage = $defaultImage;
        }

        ?>

        <img
            src="<?php echo safeOutput($profileImage); ?>"
            alt="Student Profile"
        >

        <h3><?php echo safeOutput($student['fullname']); ?></h3>

        <p><?php echo safeOutput($student['studentid']); ?></p>

    </div>


    <div class="details-grid">

        <div class="detail-item">
            <strong>Student ID</strong>
            <span><?php echo safeOutput($student['studentid']); ?></span>
        </div>

        <div class="detail-item">
            <strong>Full Name</strong>
            <span><?php echo safeOutput($student['fullname']); ?></span>
        </div>

        <div class="detail-item">
            <strong>Date of Birth</strong>
            <span><?php echo safeOutput($student['dob']); ?></span>
        </div>

        <div class="detail-item">
            <strong>Gender</strong>
            <span><?php echo safeOutput($student['gender']); ?></span>
        </div>

        <div class="detail-item">
            <strong>Department</strong>
            <span><?php echo safeOutput($student['department']); ?></span>
        </div>

        <div class="detail-item">
            <strong>Semester</strong>
            <span><?php echo safeOutput($student['semester']); ?></span>
        </div>

        <div class="detail-item">
            <strong>Email</strong>
            <span><?php echo safeOutput($student['email']); ?></span>
        </div>

        <div class="detail-item">
            <strong>Phone</strong>
            <span><?php echo safeOutput($student['phone']); ?></span>
        </div>

        <div class="detail-item">
            <strong>Address</strong>
            <span><?php echo safeOutput($student['address']); ?></span>
        </div>

        <div class="detail-item">
            <strong>Registration Date</strong>
            <span><?php echo safeOutput($student['reg_time']); ?></span>
        </div>

    </div>

    <button onclick="window.print()" class="print-btn">
    <i class="fa fa-print"></i> Print Student Details
</button>


    <a href="dashboard.php" class="back-btn">
        Back to Students List
    </a>

</div>

</body>
</html>
