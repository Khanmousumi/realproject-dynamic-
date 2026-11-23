
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
// 4. VERIFY CURRENT USER FROM DATABASE
// ========================================

$currentId = $_SESSION['student_id'];

$checkUser = $conn->prepare(
    "SELECT role, is_main_admin
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$checkUser->bind_param("s", $currentId);
$checkUser->execute();

$currentUser = $checkUser->get_result()->fetch_assoc();

$checkUser->close();


// ========================================
// 5. VERIFY ACCOUNT AND ROLE
// ========================================

if (
    !$currentUser ||
    !in_array($currentUser['role'], ['admin', 'student'], true)
) {
    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 6. REFRESH SESSION
// ========================================

$role = $currentUser['role'];

$isAdmin = ($role === 'admin');

$isCurrentMainAdmin = (
    $isAdmin &&
    (int)$currentUser['is_main_admin'] === 1
);

$_SESSION['role'] = $role;

$_SESSION['is_main_admin'] = $isCurrentMainAdmin ? 1 : 0;


// ========================================
// 7. GET TARGET ACCOUNT
// ========================================

$targetId = trim($_GET['id'] ?? '');

if ($targetId === '') {
    exit('Student ID is required.');
}


// ========================================
// 8. STUDENT PERMISSION CHECK
// ========================================

if (!$isAdmin && $targetId !== $currentId) {
    http_response_code(403);
    exit('Access denied. You can edit only your own profile.');
}

$stmt = $conn->prepare(
    "SELECT studentid, fullname, dob, gender, department,
            semester, email, phone, address, profile_image,
            role, is_main_admin
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);


$stmt->bind_param("s", $targetId);
$stmt->execute();

$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Check whether target account exists

if (!$student) {
    http_response_code(404);
    exit('Account not found.');
}

// Main Admin Protection

if (
    (int)$student['is_main_admin'] === 1 &&
    !$isCurrentMainAdmin
) {
    http_response_code(403);
    exit('Only Main Admin can edit Main Admin account.');
}

$errors = [];
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        exit('Invalid request. Please reload the page and try again.');
    }

    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Admin-only fields
    $studentid = $student['studentid'];
    $dob = $student['dob'];
    $gender = $student['gender'];
    $department = $student['department'];
    $semester = $student['semester'];

    if ($isAdmin) {
        $studentid = trim($_POST['studentid'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
    }

    if ($fullname === '' || strlen($fullname) > 150) {
        $errors[] = 'Enter a valid full name (maximum 150 characters).';
    }

    if (
        $email === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 254
    ) {
        $errors[] = 'Enter a valid email address.';
    }

    if (!preg_match('/^[0-9+\s()-]{7,20}$/', $phone)) {
        $errors[] = 'Enter a valid phone number.';
    }

    if ($address === '' || strlen($address) > 500) {
        $errors[] = 'Enter an address (maximum 500 characters).';
    }

    if ($isAdmin) {
        $dateObject = DateTime::createFromFormat('!Y-m-d', $dob);

        if (
            $studentid === '' ||
            strlen($studentid) > 50
        ) {
            $errors[] = 'Enter a valid Student ID.';
        }

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $dob ||
            $dob > date('Y-m-d')
        ) {
            $errors[] = 'Enter a valid date of birth.';
        }

        if (!in_array($gender, ['male', 'female', 'other'], true)) {
            $errors[] = 'Select a valid gender.';
        }

        if (!in_array($department, ['cse', 'eee', 'bba', 'english', 'law'], true)) {
            $errors[] = 'Select a valid department.';
        }

        if (!in_array($semester, ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'], true)) {
            $errors[] = 'Select a valid semester.';
        }
    }

    // Check duplicate Student ID or email, excluding this record
    if (empty($errors)) {
        $check = $conn->prepare(
            "SELECT studentid, email
             FROM registration
             WHERE (studentid = ? OR email = ?)
               AND studentid <> ?"
        );

        $check->bind_param("sss", $studentid, $email, $targetId);
        $check->execute();
        $duplicates = $check->get_result();

        while ($row = $duplicates->fetch_assoc()) {
            if ($row['studentid'] === $studentid) {
                $errors[] = 'This Student ID is already in use.';
            }

            if (strcasecmp($row['email'], $email) === 0) {
                $errors[] = 'This email is already in use.';
            }
        }

        $check->close();
    }

    $profileImage = $student['profile_image'];
    $newImagePath = null;

    // Optional profile photo upload
    if (
        empty($errors) &&
        isset($_FILES['profile_image']) &&
        $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed.';
        } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Image must be 2 MB or less.';
        } else {
            $tmpName = $_FILES['profile_image']['tmp_name'];
            $imageInfo = @getimagesize($tmpName);

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp'
            ];

            $mime = $imageInfo['mime'] ?? '';

            if ($imageInfo === false || !isset($allowedTypes[$mime])) {
                $errors[] = 'Only valid JPG, PNG or WEBP images are allowed.';
            } else {
                $uploadDir = __DIR__ . '/../../Uploads_Photo/';

                if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                    $errors[] = 'Uploads_Photo folder is missing or not writable.';
                } else {
                    $profileImage = bin2hex(random_bytes(16))
                        . '.' . $allowedTypes[$mime];

                    $newImagePath = $uploadDir . $profileImage;

                    if (!move_uploaded_file($tmpName, $newImagePath)) {
                        $errors[] = 'Could not save the uploaded image.';
                        $newImagePath = null;
                        $profileImage = $student['profile_image'];
                    }
                }
            }
        }
    }

    if (empty($errors)) {
        $update = $conn->prepare(
           "UPDATE registration
 SET studentid = ?, fullname = ?, dob = ?, gender = ?,
     department = ?, semester = ?, email = ?, phone = ?,
     address = ?, profile_image = ?
 WHERE studentid = ?
 AND (is_main_admin = 0 OR ? = 1)"
        );
$mainAdminPermission = $isCurrentMainAdmin ? 1 : 0;

$update->bind_param(
    "sssssssssssi",
    $studentid,
    $fullname,
    $dob,
    $gender,
    $department,
    $semester,
    $email,
    $phone,
    $address,
    $profileImage,
    $targetId,
    $mainAdminPermission
);
       

        if ($update->execute()) {
            $update->close();

            // Keep the session ID correct if Admin edits their own Student ID
            if ($targetId === $currentId) {
                $_SESSION['student_id'] = $studentid;
                $_SESSION['student_email'] = $email;
            }

            // Redirect to avoid resubmitting the form
            header(
                "Location: edit_student.php?id=" . urlencode($studentid) . "&saved=1"
            );
            exit;
        } else {
            if ($newImagePath && is_file($newImagePath)) {
                unlink($newImagePath);
            }

            $errors[] = 'Update failed. Please try again.';
            $update->close();
        }
    }

    // Reload the values for displaying the form after validation errors
    $student['studentid'] = $studentid;
    $student['fullname'] = $fullname;
    $student['dob'] = $dob;
    $student['gender'] = $gender;
    $student['department'] = $department;
    $student['semester'] = $semester;
    $student['email'] = $email;
    $student['phone'] = $phone;
    $student['address'] = $address;
    $student['profile_image'] = $profileImage;
}

if (isset($_GET['saved'])) {
    $success = 'Profile updated successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Student</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 25px 15px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .edit-card {
            max-width: 700px;
            margin: 0 auto;
            padding: 25px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,.07);
        }

        h2 { margin-top: 0; color: #1d4ed8; }

        .field { margin-bottom: 16px; }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
        }

        textarea { min-height: 85px; resize: vertical; }

        button {
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
        }

        .message {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
        }

        .error { background: #fee2e2; color: #991b1b; }
        .success { background: #dcfce7; color: #166534; }

        .photo {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 50%;
            margin-bottom: 10px;
        }

        .back { display: inline-block; margin-bottom: 18px; }

        @media (max-width: 480px) {
            .edit-card { padding: 18px; }
        }
    </style>
</head>
<body>
<div class="edit-card">
    <a class="back" href="dashboard.php">← Back to Students</a>

    <h2>Edit Student Profile</h2>
    <p>Logged in as: <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></p>

    <?php if ($success !== ''): ?>
        <div class="message success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <div class="message error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endforeach; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

        <?php if ($isAdmin): ?>
            <div class="field">
                <label>Student ID</label>
                <input name="studentid" maxlength="50" required
                       value="<?= htmlspecialchars($student['studentid'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
        <?php else: ?>
            <div class="field">
                <label>Student ID</label>
                <input value="<?= htmlspecialchars($student['studentid'], ENT_QUOTES, 'UTF-8') ?>" disabled>
            </div>
        <?php endif; ?>

        <div class="field">
            <label>Full Name</label>
            <input name="fullname" maxlength="150" required
                   value="<?= htmlspecialchars($student['fullname'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
            <label>Email</label>
            <input type="email" name="email" maxlength="254" required
                   value="<?= htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
            <label>Phone</label>
            <input name="phone" required
                   value="<?= htmlspecialchars($student['phone'], ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="field">
            <label>Address</label>
            <textarea name="address" maxlength="500" required><?= htmlspecialchars($student['address'], ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <?php if ($isAdmin): ?>
            <div class="field">
                <label>Date of Birth</label>
                <input type="date" name="dob" required
                       value="<?= htmlspecialchars($student['dob'], ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="field">
                <label>Gender</label>
                <select name="gender" required>
                    <?php foreach (['male', 'female', 'other'] as $option): ?>
                        <option value="<?= $option ?>"
                            <?= $student['gender'] === $option ? 'selected' : '' ?>>
                            <?= ucfirst($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Department</label>
                <select name="department" required>
                    <?php foreach (['cse', 'eee', 'bba', 'english', 'law'] as $option): ?>
                        <option value="<?= $option ?>"
                            <?= $student['department'] === $option ? 'selected' : '' ?>>
                            <?= strtoupper($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Semester</label>
                <select name="semester" required>
                    <?php foreach (['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'] as $option): ?>
                        <option value="<?= $option ?>"
                            <?= $student['semester'] === $option ? 'selected' : '' ?>>
                            <?= $option ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="field">
            <label>Profile Photo (optional, JPG/PNG/WEBP, max 2 MB)</label>

            <?php if (!empty($student['profile_image'])): ?>
                <div>
                    <img class="photo"
                         src="../../Uploads_Photo/<?= rawurlencode(basename($student['profile_image'])) ?>"
                         alt="Profile photo">
                </div>
            <?php endif; ?>

            <input type="file" name="profile_image"
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
        </div>

        <button type="submit">Save Changes</button>
    </form>
</div>
</body>
</html>
