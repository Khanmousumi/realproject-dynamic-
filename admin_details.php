
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
    "SELECT role
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


// ========================================
// 5. GET ADMIN ID
// ========================================

$adminId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$adminId || $adminId <= 0) {
    exit("Invalid Admin ID.");
}


// ========================================
// 6. FETCH ADMIN DETAILS
// ========================================

$sql = "SELECT id, fullname, studentid, dob, gender,
               department, semester, email, phone,
               address, profile_image, reg_time,
               is_main_admin
        FROM registration
        WHERE id = ?
        AND role = 'admin'
        LIMIT 1";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $adminId);

$stmt->execute();

$result = $stmt->get_result();

$admin = $result->fetch_assoc();

$stmt->close();


// ========================================
// 7. CHECK ADMIN EXISTS
// ========================================

if (!$admin) {
    $conn->close();
    exit("Admin account not found.");
}


// ========================================
// 8. CHECK MAIN ADMIN STATUS
// ========================================

$isMainAdmin = (
    (int)$admin['is_main_admin'] === 1
);


// ========================================
// 9. PROFILE IMAGE
// ========================================

$defaultImage = "../../Uploads_Photo/default.png";

if (!empty($admin['profile_image'])) {

    $profileImage = "../../Uploads_Photo/" .
                    rawurlencode(basename($admin['profile_image']));

} else {

    $profileImage = $defaultImage;

}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Details | Dynamic</title>

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
            max-width: 850px;
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

        h1 {
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
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .profile {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile img {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #dbeafe;
            margin-bottom: 15px;
        }

        .profile h2 {
            color: #1e3a8a;
            margin-bottom: 12px;
        }

        .admin-badge {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .main-admin-badge {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .detail-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #edf0f5;
        }

        .detail-item.full {
            grid-column: 1 / -1;
        }

        .detail-item small {
            display: block;
            color: #64748b;
            margin-bottom: 8px;
        }

        .detail-item p {
            font-weight: bold;
            color: #263445;
            overflow-wrap: anywhere;
        }

        @media (max-width: 600px) {

            body {
                padding: 15px;
            }

            .card {
                padding: 20px;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .detail-item.full {
                grid-column: auto;
            }

            h1 {
                font-size: 23px;
            }

        }

    </style>

</head>

<body>

<div class="container">

    <!-- HEADER -->

    <div class="header">

        <div>
            <h1>Admin Details</h1>
            <p>Administrator account information</p>
        </div>

        <a href="manage_admin.php" class="back-btn">
            ← Back to Admin List
        </a>

    </div>


    <div class="card">

        <!-- PROFILE -->

        <div class="profile">

            <img
                src="<?php echo htmlspecialchars($profileImage); ?>"
                alt="Admin Profile Photo"
                onerror="this.onerror=null; this.src='<?php echo htmlspecialchars($defaultImage); ?>';"
            >

            <h2>
                <?php echo htmlspecialchars($admin['fullname']); ?>
            </h2>

            <?php if ($isMainAdmin): ?>

                <span class="main-admin-badge">
                    MAIN ADMIN
                </span>

            <?php else: ?>

                <span class="admin-badge">
                    ADMIN
                </span>

            <?php endif; ?>

        </div>


        <!-- ADMIN INFORMATION -->

        <div class="details">

            <div class="detail-item">
                <small>Admin ID</small>
                <p><?php echo htmlspecialchars($admin['studentid']); ?></p>
            </div>

            <div class="detail-item">
                <small>Database ID</small>
                <p><?php echo (int)$admin['id']; ?></p>
            </div>

            <div class="detail-item">
                <small>Email Address</small>
                <p><?php echo htmlspecialchars($admin['email']); ?></p>
            </div>

            <div class="detail-item">
                <small>Phone Number</small>
                <p><?php echo htmlspecialchars($admin['phone'] ?? 'Not provided'); ?></p>
            </div>

            <div class="detail-item">
                <small>Department</small>
                <p><?php echo htmlspecialchars($admin['department'] ?? 'Not provided'); ?></p>
            </div>

            <div class="detail-item">
                <small>Semester</small>
                <p><?php echo htmlspecialchars($admin['semester'] ?? 'Not provided'); ?></p>
            </div>

            <div class="detail-item">
                <small>Date of Birth</small>
                <p><?php echo htmlspecialchars($admin['dob'] ?? 'Not provided'); ?></p>
            </div>

            <div class="detail-item">
                <small>Gender</small>
                <p><?php echo htmlspecialchars($admin['gender'] ?? 'Not provided'); ?></p>
            </div>

            <div class="detail-item full">
                <small>Address</small>
                <p><?php echo htmlspecialchars($admin['address'] ?? 'Not provided'); ?></p>
            </div>

            <div class="detail-item full">
                <small>Registration Date</small>
                <p><?php echo htmlspecialchars($admin['reg_time'] ?? 'Not available'); ?></p>
            </div>

        </div>

    </div>

</div>

</body>
</html>
