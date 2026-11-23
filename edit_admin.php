
<?php

// ========================================
// 1. SESSION START
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. ADMIN SESSION CHECK
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
// 4. VERIFY CURRENT ADMIN FROM DATABASE
// ========================================

$currentUserId = $_SESSION['student_id'];

$verifyStmt = $conn->prepare(
    "SELECT role, is_main_admin
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

if (!$verifyStmt) {
    http_response_code(500);
    exit("Database verification failed.");
}

$verifyStmt->bind_param("s", $currentUserId);
$verifyStmt->execute();

$currentUser = $verifyStmt->get_result()->fetch_assoc();
$verifyStmt->close();

if (!$currentUser || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    $conn->close();
    exit("Access denied. Admin permission required.");
}

// Refresh current session information

$_SESSION['role'] = $currentUser['role'];

$_SESSION['is_main_admin'] = (
    (int)$currentUser['is_main_admin'] === 1
) ? 1 : 0;

$isCurrentMainAdmin = (
    (int)$_SESSION['is_main_admin'] === 1
);


// ========================================
// 5. CSRF TOKEN GENERATION
// ========================================

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ========================================
// 6. INITIAL VARIABLES
// ========================================

$errors = [];
$successMessage = '';


// ========================================
// 7. GET ADMIN ID
// ========================================

$adminId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$adminId || $adminId <= 0) {
    http_response_code(400);
    $conn->close();
    exit("Invalid Admin ID.");
}


// ========================================
// 8. FETCH ADMIN INFORMATION
// ========================================

$sql = "SELECT id, fullname, studentid, dob, gender,
               department, semester, email, phone,
               address, profile_image, is_main_admin
        FROM registration
        WHERE id = ?
        AND role = 'admin'
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    $conn->close();
    exit("Database query preparation failed.");
}

$stmt->bind_param("i", $adminId);
$stmt->execute();

$result = $stmt->get_result();
$admin = $result->fetch_assoc();

$stmt->close();


// ========================================
// 9. CHECK ADMIN EXISTS
// ========================================

if (!$admin) {
    http_response_code(404);
    $conn->close();
    exit("Admin account not found.");
}


// ========================================
// 10. MAIN ADMIN PROTECTION
// ========================================

if (
    (int)$admin['is_main_admin'] === 1 &&
    !$isCurrentMainAdmin
) {
    http_response_code(403);
    $conn->close();
    exit("Only Main Admin can edit Main Admin account.");
}


// ========================================
// 11. POST REQUEST PROCESSING
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ====================================
    // CSRF VALIDATION
    // ====================================

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(403);
        $conn->close();
        exit("Invalid CSRF Token! Request denied.");
    }


    // ====================================
    // GET FORM DATA
    // ====================================

    $fullname = trim($_POST['fullname'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');


    // ====================================
    // FORM VALIDATION
    // ====================================

    if ($fullname === '') {
        $errors[] = "Full Name is required.";
    }

    if (
        $email === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $errors[] = "Please enter a valid email address.";
    }

    // Validate Gender

    $allowedGenders = ['', 'Male', 'Female', 'Other'];

    if (!in_array($gender, $allowedGenders, true)) {
        $errors[] = "Invalid gender selection.";
    }

    // Validate Date of Birth

    if ($dob !== '') {

        $dateObject = DateTime::createFromFormat('Y-m-d', $dob);

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $dob
        ) {
            $errors[] = "Invalid date of birth.";
        }
    }


    // ====================================
    // DUPLICATE EMAIL CHECK
    // ====================================

    if (empty($errors)) {

        $checkSql = "SELECT id
                     FROM registration
                     WHERE email = ?
                     AND id != ?
                     LIMIT 1";

        $checkStmt = $conn->prepare($checkSql);

        if (!$checkStmt) {

            $errors[] = "Could not verify email address.";

        } else {

            $checkStmt->bind_param("si", $email, $adminId);
            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $errors[] = "This email address is already used by another account.";
            }

            $checkStmt->close();
        }
    }


    // ====================================
    // 12. PROFILE IMAGE UPLOAD
    // ====================================

    $newProfileImage = $admin['profile_image'];
    $uploadedNewImagePath = null;

    if (
        isset($_FILES['profile_image']) &&
        $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {

            $errors[] = "Profile image upload failed.";

        } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {

            $errors[] = "Image size must be less than 2 MB.";

        } else {

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];

            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);

            if ($fileInfo === false) {

                $errors[] = "Could not verify uploaded image.";

            } else {

                $mimeType = finfo_file(
                    $fileInfo,
                    $_FILES['profile_image']['tmp_name']
                );

                finfo_close($fileInfo);

                $imageInfo = @getimagesize(
                    $_FILES['profile_image']['tmp_name']
                );

                if (
                    !isset($allowedTypes[$mimeType]) ||
                    $imageInfo === false ||
                    $imageInfo['mime'] !== $mimeType
                ) {

                    $errors[] = "Only valid JPG, PNG and WEBP images are allowed.";

                } else {

                    $uploadDirectory = '../../Uploads_Photo/';

                    if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {

                        $errors[] = "Upload folder is missing or not writable.";

                    } else {

                        $extension = $allowedTypes[$mimeType];

                        $newFileName = 'admin_' . $adminId . '_' .
                                       bin2hex(random_bytes(16)) . '.' .
                                       $extension;

                        $destination = $uploadDirectory . $newFileName;

                        if (
                            move_uploaded_file(
                                $_FILES['profile_image']['tmp_name'],
                                $destination
                            )
                        ) {

                            $newProfileImage = $newFileName;
                            $uploadedNewImagePath = $destination;

                        } else {

                            $errors[] = "Could not save the profile image.";

                        }
                    }
                }
            }
        }
    }


    // ====================================
    // 13. UPDATE DATABASE
    // ====================================

    if (empty($errors)) {

        $updateSql = "UPDATE registration
                      SET fullname = ?,
                          dob = ?,
                          gender = ?,
                          department = ?,
                          semester = ?,
                          email = ?,
                          phone = ?,
                          address = ?,
                          profile_image = ?
                      WHERE id = ?
                      AND role = 'admin'
                      AND is_main_admin = ?";

        $updateStmt = $conn->prepare($updateSql);

        if (!$updateStmt) {

            $errors[] = "Database query preparation failed.";

        } else {

            // Preserve the original Main Admin status

            $mainAdminStatus = (int)$admin['is_main_admin'];

            $updateStmt->bind_param(
                "sssssssssii",
                $fullname,
                $dob,
                $gender,
                $department,
                $semester,
                $email,
                $phone,
                $address,
                $newProfileImage,
                $adminId,
                $mainAdminStatus
            );


            // ====================================
            // EXECUTE UPDATE
            // ====================================

            if ($updateStmt->execute()) {

                // Delete old profile image after successful update

                if (
                    !empty($newProfileImage) &&
                    $newProfileImage !== $admin['profile_image'] &&
                    !empty($admin['profile_image'])
                ) {

                    $oldImageName = basename($admin['profile_image']);

                    $oldImagePath = '../../Uploads_Photo/' . $oldImageName;

                    if (
                        $oldImageName !== 'default.png' &&
                        is_file($oldImagePath)
                    ) {
                        unlink($oldImagePath);
                    }
                }


                // Refresh Admin Data

                $admin['fullname'] = $fullname;
                $admin['dob'] = $dob;
                $admin['gender'] = $gender;
                $admin['department'] = $department;
                $admin['semester'] = $semester;
                $admin['email'] = $email;
                $admin['phone'] = $phone;
                $admin['address'] = $address;
                $admin['profile_image'] = $newProfileImage;

                $successMessage = "Admin information updated successfully!";


                // Generate a fresh CSRF token after successful update

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            } else {

                $errors[] = "Failed to update admin information.";

            }

            $updateStmt->close();
        }


        // Remove newly uploaded image if Database update failed

        if (
            !empty($errors) &&
            $uploadedNewImagePath !== null &&
            is_file($uploadedNewImagePath)
        ) {
            unlink($uploadedNewImagePath);
        }
    }


    // ====================================
    // 14. PRESERVE FORM VALUES
    // ====================================

    if (!empty($errors)) {

        $admin['fullname'] = $fullname;
        $admin['dob'] = $dob;
        $admin['gender'] = $gender;
        $admin['department'] = $department;
        $admin['semester'] = $semester;
        $admin['email'] = $email;
        $admin['phone'] = $phone;
        $admin['address'] = $address;
    }

}


// ========================================
// 15. PROFILE IMAGE PATH
// ========================================

$defaultImage = "../../Uploads_Photo/default.png";

if (!empty($admin['profile_image'])) {

    $profileImage = "../../Uploads_Photo/" .
                    rawurlencode(basename($admin['profile_image']));

} else {

    $profileImage = $defaultImage;
}


// ========================================
// 16. CLOSE DATABASE CONNECTION
// ========================================

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Edit Admin | Dynamic</title>

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

        .back-btn:hover {
            background: #1e40af;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }

        .profile {
            text-align: center;
            margin-bottom: 25px;
        }

        .profile img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #dbeafe;
            margin-bottom: 12px;
        }

        .profile h3 {
            color: #1e3a8a;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: bold;
            font-size: 14px;
            color: #334155;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #dbe3ef;
            border-radius: 7px;
            font-size: 14px;
            font-family: Arial, sans-serif;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        .readonly {
            background: #f1f5f9;
            color: #64748b;
        }

        .message {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .error ul {
            padding-left: 20px;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .update-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 13px 25px;
            border-radius: 7px;
            font-size: 15px;
            cursor: pointer;
        }

        .update-btn:hover {
            background: #1e40af;
        }

        .cancel-btn {
            text-decoration: none;
            background: #e2e8f0;
            color: #334155;
            padding: 13px 25px;
            border-radius: 7px;
        }

        .note {
            color: #64748b;
            font-size: 12px;
        }

        @media (max-width: 600px) {

            body {
                padding: 15px;
            }

            .card {
                padding: 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
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
            <h1>Edit Admin</h1>
            <p>Update administrator account information.</p>
        </div>

        <a href="manage_admin.php" class="back-btn">
            ← Back to Admin List
        </a>

    </div>


    <div class="card">

        <!-- PROFILE -->

        <div class="profile">

            <img
                src="<?php echo htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8'); ?>"
                alt="Admin Photo"
                onerror="this.onerror=null; this.src='<?php echo htmlspecialchars($defaultImage, ENT_QUOTES, 'UTF-8'); ?>';"
            >

            <h3>
                <?php echo htmlspecialchars($admin['fullname'], ENT_QUOTES, 'UTF-8'); ?>
            </h3>

            <p>
                Admin ID:
                <?php echo htmlspecialchars($admin['studentid'], ENT_QUOTES, 'UTF-8'); ?>
            </p>

        </div>


        <!-- SUCCESS MESSAGE -->

        <?php if ($successMessage !== ''): ?>

            <div class="message success">
                <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>

        <?php endif; ?>


        <!-- ERROR MESSAGE -->

        <?php if (!empty($errors)): ?>

            <div class="message error">

                <ul>

                    <?php foreach ($errors as $error): ?>

                        <li>
                            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </li>

                    <?php endforeach; ?>

                </ul>

            </div>

        <?php endif; ?>


        <!-- EDIT FORM -->

        <form method="POST" enctype="multipart/form-data">

            <!-- CSRF TOKEN -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label>Full Name</label>

                    <input
                        type="text"
                        name="fullname"
                        value="<?php echo htmlspecialchars($admin['fullname'], ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Admin ID (Cannot Change)</label>

                    <input
                        type="text"
                        value="<?php echo htmlspecialchars($admin['studentid'], ENT_QUOTES, 'UTF-8'); ?>"
                        class="readonly"
                        readonly
                    >

                </div>


                <div class="form-group">

                    <label>Email Address</label>

                    <input
                        type="email"
                        name="email"
                        value="<?php echo htmlspecialchars($admin['email'], ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Phone Number</label>

                    <input
                        type="text"
                        name="phone"
                        value="<?php echo htmlspecialchars($admin['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Department</label>

                    <input
                        type="text"
                        name="department"
                        value="<?php echo htmlspecialchars($admin['department'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Semester</label>

                    <input
                        type="text"
                        name="semester"
                        value="<?php echo htmlspecialchars($admin['semester'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Date of Birth</label>

                    <input
                        type="date"
                        name="dob"
                        value="<?php echo htmlspecialchars($admin['dob'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    >

                </div>


                <div class="form-group">

                    <label>Gender</label>

                    <select name="gender">

                        <option value="">Select Gender</option>

                        <option value="Male"
                            <?php echo ($admin['gender'] === 'Male') ? 'selected' : ''; ?>>
                            Male
                        </option>

                        <option value="Female"
                            <?php echo ($admin['gender'] === 'Female') ? 'selected' : ''; ?>>
                            Female
                        </option>

                        <option value="Other"
                            <?php echo ($admin['gender'] === 'Other') ? 'selected' : ''; ?>>
                            Other
                        </option>

                    </select>

                </div>


                <div class="form-group full">

                    <label>Address</label>

                    <textarea name="address"><?php echo htmlspecialchars($admin['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>

                </div>


                <div class="form-group full">

                    <label>Profile Photo</label>

                    <input
                        type="file"
                        name="profile_image"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <p class="note">
                        JPG, PNG or WEBP. Maximum size: 2 MB.
                        Leave empty to keep the current photo.
                    </p>

                </div>

            </div>


            <div class="button-group">

                <button type="submit" class="update-btn">
                    Update Admin
                </button>

                <a href="manage_admin.php" class="cancel-btn">
                    Cancel
                </a>

            </div>

        </form>

    </div>

</div>

</body>
</html>
