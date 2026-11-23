
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
// 4. INITIAL VALUES
// ========================================

$inputError = [];
$successMessage = '';

$fullname = '';
$studentid = '';
$dob = '';
$gender = '';
$department = '';
$semester = '';
$email = '';
$phone = '';
$address = '';

$password = '';
$confirmPassword = '';

$profileImage = 'default.png';
$uploadedFilePath = '';


// Success Message

if (
    isset($_GET['created']) &&
    $_GET['created'] === '1'
) {
    $successMessage =
        'New Admin account created successfully!';
}


// ========================================
// 5. FORM SUBMISSION
// ========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    // Receive Form Data

    $fullname = trim($_POST['fullname'] ?? '');
    $studentid = trim($_POST['studentid'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $semester = trim($_POST['semester'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';


    // ====================================
    // 6. FORM VALIDATION
    // ====================================


    // Full Name

    if ($fullname === '') {
        $inputError['fullname'] =
            'Full name is required.';
    }


    // Student ID

    if ($studentid === '') {
        $inputError['studentid'] =
            'Student ID is required.';
    }


    // Date of Birth

    if ($dob === '') {
        $inputError['dob'] =
            'Date of birth is required.';
    } else {

        $dateObject = DateTime::createFromFormat(
            'Y-m-d',
            $dob
        );

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $dob
        ) {
            $inputError['dob'] =
                'Please enter a valid date of birth.';
        }
    }


    // Gender

    if (!in_array(
        $gender,
        ['Male', 'Female', 'Other'],
        true
    )) {
        $inputError['gender'] =
            'Please select gender.';
    }


    // Department

    $validDepartments = [
        'administration',
        'cse',
        'eee',
        'bba',
        'english',
        'law'
    ];

    if (!in_array(
        $department,
        $validDepartments,
        true
    )) {
        $inputError['department'] =
            'Please select department.';
    }


    // Semester

    $validSemesters = [
        'N/A',
        '1st',
        '2nd',
        '3rd',
        '4th',
        '5th',
        '6th',
        '7th',
        '8th'
    ];

    if (!in_array(
        $semester,
        $validSemesters,
        true
    )) {
        $inputError['semester'] =
            'Please select semester.';
    }


    // Email

    if (!filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )) {
        $inputError['email'] =
            'Please enter a valid email.';
    }


    // Phone

    if ($phone === '') {
        $inputError['phone'] =
            'Phone number is required.';
    }


    // Address

    if ($address === '') {
        $inputError['address'] =
            'Address is required.';
    }


    // Password

    if (strlen($password) < 9) {
        $inputError['password'] =
            'Password must be at least 9 characters.';
    }


    // Confirm Password

    if ($password !== $confirmPassword) {
        $inputError['confirm_password'] =
            'Passwords do not match.';
    }


    // ====================================
    // 7. DUPLICATE CHECK
    // ====================================

    if (empty($inputError)) {

        $checkStmt = $conn->prepare(
            "SELECT id FROM registration
             WHERE studentid = ? OR email = ?
             LIMIT 1"
        );

        if (!$checkStmt) {

            $inputError['general'] =
                'Could not check existing account.';

        } else {

            $checkStmt->bind_param(
                "ss",
                $studentid,
                $email
            );

            if (!$checkStmt->execute()) {

                $inputError['general'] =
                    'Account verification failed.';

            } else {

                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows > 0) {

                    $inputError['general'] =
                        'Student ID or Email already exists.';
                }
            }

            $checkStmt->close();
        }
    }


    // ====================================
    // 8. PROFILE PHOTO VALIDATION
    // ====================================

    if (
        empty($inputError) &&
        isset($_FILES['profile_image']) &&
        $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK
        ) {

            $inputError['profile_image'] =
                'Photo upload failed.';

        } else {

            // Maximum 2 MB

            $maxSize = 2 * 1024 * 1024;

            if ($_FILES['profile_image']['size'] > $maxSize) {

                $inputError['profile_image'] =
                    'Photo size must be maximum 2 MB.';

            } else {

                $tmpName = $_FILES['profile_image']['tmp_name'];

                $imageInfo = getimagesize($tmpName);


                // Allowed Image Types

                $allowedTypes = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];


                if (
                    $imageInfo === false ||
                    !isset($allowedTypes[$imageInfo['mime']])
                ) {

                    $inputError['profile_image'] =
                        'Only JPG, PNG and WEBP images are allowed.';

                } else {

                    // Upload Folder

                    $uploadDir = __DIR__ . '/../../Uploads_Photo/';

                    // Random File Name

                    $newFileName = bin2hex(random_bytes(16))
                        . '.' . $allowedTypes[$imageInfo['mime']];

                    $uploadedFilePath =
                        $uploadDir . $newFileName;


                    if (!is_dir($uploadDir)) {

                        $inputError['profile_image'] =
                            'Upload folder does not exist.';

                    } elseif (
                        !move_uploaded_file(
                            $tmpName,
                            $uploadedFilePath
                        )
                    ) {

                        $inputError['profile_image'] =
                            'Could not save profile photo.';

                        $uploadedFilePath = '';

                    } else {

                        $profileImage = $newFileName;
                    }
                }
            }
        }
    }


    // ====================================
    // 9. DATABASE INSERT
    // ====================================

    if (empty($inputError)) {


        // Password Hashing

        $hashedPassword = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        // Admin Role

        $role = 'admin';


        // Registration Time

        $regTime = date('Y-m-d H:i:s');


        // Reset Password Fields

        $resetTokenHash = '';
        $resetTokenExpiry = null;


        // SQL Query

        $sql = "INSERT INTO registration
(
    fullname,
    studentid,
    dob,
    gender,
    department,
    semester,
    email,
    phone,
    address,
    profile_image,
    password,
    role,
    reg_time,
    reset_token_hash,
    reset_token_expiry,
    is_main_admin
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)";


        $insertStmt = $conn->prepare($sql);


        if (!$insertStmt) {

            $inputError['general'] =
                'Database query preparation failed.';

        } else {


            // Bind Parameters

            $insertStmt->bind_param(
                "sssssssssssssss",
                $fullname,
                $studentid,
                $dob,
                $gender,
                $department,
                $semester,
                $email,
                $phone,
                $address,
                $profileImage,
                $hashedPassword,
                $role,
                $regTime,
                $resetTokenHash,
                $resetTokenExpiry
            );


            // Execute Query

            if ($insertStmt->execute()) {

                $insertStmt->close();

                // Redirect After Success

                header(
                    "Location: create_admin.php?created=1"
                );

                exit;

            } else {

                $inputError['general'] =
                    'Admin account could not be created.';

                $insertStmt->close();
            }
        }
    }


    // ====================================
    // 10. DELETE PHOTO IF INSERT FAILED
    // ====================================

    if (
        !empty($inputError) &&
        $uploadedFilePath !== '' &&
        file_exists($uploadedFilePath)
    ) {

        unlink($uploadedFilePath);

        $profileImage = 'default.png';
    }

}

?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px 15px;
            font-family: Arial, sans-serif;
            background: #f3f6fc;
        }

        .admin-form {
            max-width: 750px;
            margin: auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        h2 {
            color: #245eea;
            margin-top: 0;
        }

        .form-row {
            display: flex;
            gap: 20px;
        }

        .form-group {
            flex: 1;
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccd5e3;
            border-radius: 7px;
            font-size: 15px;
        }

        textarea {
            resize: vertical;
        }

        button {
            background: #245eea;
            color: white;
            padding: 13px 25px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #1749c5;
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>

<body>

<div class="admin-form">

    <h2>Create New Admin</h2>
    <p>নতুন Admin Account তৈরি করুন</p>
<?php if (!empty($successMessage)): ?>
    <div style="
        background: #d4edda;
        color: #155724;
        padding: 12px;
        border-radius: 7px;
        margin-bottom: 18px;
    ">
        <?php echo htmlspecialchars($successMessage); ?>
    </div>
<?php endif; ?>


<?php if (!empty($inputError)): ?>

    <div style="
        background: #f8d7da;
        color: #721c24;
        padding: 12px;
        border-radius: 7px;
        margin-bottom: 18px;
    ">

        <strong>Please fix the following errors:</strong>

        <ul>
            <?php foreach ($inputError as $error): ?>
                <li>
                    <?php echo htmlspecialchars($error); ?>
                </li>
            <?php endforeach; ?>
        </ul>

    </div>

<?php endif; ?>




    <form method="POST"
          action=""
          enctype="multipart/form-data">

        <div class="form-group">
            <label>Full Name</label>
           <input type="text"
       name="fullname"
       value="<?php echo htmlspecialchars($fullname ?? ''); ?>"
       required>
        </div>

        <div class="form-row">

            <div class="form-group">
                <label>Student ID</label>
               <input type="text"
       name="studentid"
       value="<?php echo htmlspecialchars($studentid ?? ''); ?>"
       required>
            </div>

            <div class="form-group">
                <label>Date of Birth</label>
                <input type="date"
       name="dob"
       value="<?php echo htmlspecialchars($dob ?? ''); ?>"
       required>
            </div>

        </div>

        <div class="form-row">

            <div class="form-group">
                <label>Gender</label>
               
<select name="gender" required>
    <option value="">Select Gender</option>

    <option value="Male"
        <?php echo (($gender ?? '') === 'Male') ? 'selected' : ''; ?>>
        Male
    </option>

    <option value="Female"
        <?php echo (($gender ?? '') === 'Female') ? 'selected' : ''; ?>>
        Female
    </option>

    <option value="Other"
        <?php echo (($gender ?? '') === 'Other') ? 'selected' : ''; ?>>
        Other
    </option>
</select>

            </div>

            <div class="form-group">
                <label>Department</label>
               
<select name="department" required>
    <option value="">Select Department</option>

    <?php
    $departments = [
        'administration' => 'Administration',
        'cse' => 'Computer Science & Engineering',
        'eee' => 'Electrical & Electronic Engineering',
        'bba' => 'Business Administration',
        'english' => 'English',
        'law' => 'Law'
    ];

    foreach ($departments as $value => $label):
    ?>
        <option value="<?php echo $value; ?>"
            <?php echo (($department ?? '') === $value) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($label); ?>
        </option>
    <?php endforeach; ?>
</select>

            </div>

        </div>

        <div class="form-group">
            <label>Semester</label>
          
<select name="semester" required>
    <option value="">Select Semester</option>

    <?php
    $semesters = [
        'N/A', '1st', '2nd', '3rd',
        '4th', '5th', '6th', '7th', '8th'
    ];

    foreach ($semesters as $value):
    ?>
        <option value="<?php echo $value; ?>"
            <?php echo (($semester ?? '') === $value) ? 'selected' : ''; ?>>
            <?php echo $value; ?>
        </option>
    <?php endforeach; ?>
</select>

        </div>

        <div class="form-group">
            <label>Email</label>
           <input type="email"
       name="email"
       value="<?php echo htmlspecialchars($email ?? ''); ?>"
       required>
        </div>

        <div class="form-group">
            <label>Phone</label>
          <input type="tel"
       name="phone"
       value="<?php echo htmlspecialchars($phone ?? ''); ?>"
       required>
        </div>

        <div class="form-group">
            <label>Address</label>
           <textarea name="address" rows="3" required><?php
            echo htmlspecialchars($address ?? '');
            ?></textarea>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required>
        </div>

        <div class="form-group">
            <label>Profile Photo (Optional, Max 2 MB)</label>
            <input type="file"
                   name="profile_image"
                   accept=".jpg,.jpeg,.png,.webp">
        </div>

        <button type="submit" name="create_admin">
            Create Admin
        </button>

    </form>

</div>

</body>
</html>
