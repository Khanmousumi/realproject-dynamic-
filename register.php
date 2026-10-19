<?php
require_once '../../config/db.php';

date_default_timezone_set('Asia/Dhaka');

$inputError = [];
$success = '';

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
$confirmpassword = '';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {

    $fullname   = trim($_POST['fullname'] ?? '');
    $studentid  = trim($_POST['studentid'] ?? '');
    $dob        = trim($_POST['dob'] ?? '');
    $gender     = trim($_POST['gender'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $semester   = trim($_POST['semester'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirmpassword = $_POST['confirmpassword'] ?? '';

    // 1. Full name
    if ($fullname === '') {
        $inputError['fullname'] = 'Full Name is required';
    } elseif (strlen($fullname) > 150) {
        $inputError['fullname'] = 'Full Name is too long';
    }

    // 2. Student ID
    if ($studentid === '') {
        $inputError['studentid'] = 'Student ID is required';
    } elseif (strlen($studentid) > 50) {
        $inputError['studentid'] = 'Student ID is too long';
    }

    // 3. Date of birth
    $dateObject = DateTime::createFromFormat('!Y-m-d', $dob);
    if ($dob === '') {
        $inputError['dob'] = 'Date of Birth is required';
    } elseif (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $dob ||
        $dob > date('Y-m-d')
    ) {
        $inputError['dob'] = 'Please enter a valid date of birth';
    }

    // 4. Gender
    if (!in_array($gender, ['male', 'female', 'other'], true)) {
        $inputError['gender'] = 'Please select your gender';
    }

    // 5. Department
    $validDepartments = ['cse', 'eee', 'bba', 'english', 'law'];
    if (!in_array($department, $validDepartments, true)) {
        $inputError['department'] = 'Please select a department';
    }

    // 6. Semester
    $validSemesters = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
    if (!in_array($semester, $validSemesters, true)) {
        $inputError['semester'] = 'Please select a semester';
    }

    // 7. Email
    if ($email === '') {
        $inputError['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $inputError['email'] = 'Please enter a valid email';
    } elseif (strlen($email) > 254) {
        $inputError['email'] = 'Email address is too long';
    }

    // 8. Phone
    if ($phone === '') {
        $inputError['phone'] = 'Phone Number is required';
    } elseif (!preg_match('/^[0-9+\s()-]{7,20}$/', $phone)) {
        $inputError['phone'] = 'Please enter a valid phone number';
    }

    // 9. Address
    if ($address === '') {
        $inputError['address'] = 'Address is required';
    } elseif (strlen($address) > 500) {
        $inputError['address'] = 'Address is too long';
    }

    // 10. Password
    if ($password === '') {
        $inputError['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $inputError['password'] = 'Password must be at least 8 characters';
    }

    // 11. Confirm password
    if ($confirmpassword === '') {
        $inputError['confirmpassword'] = 'Confirm Password is required';
    } elseif ($password !== $confirmpassword) {
        $inputError['confirmpassword'] = 'Passwords do not match';
    }

    // Check duplicate student ID, email and phone
    if (empty($inputError)) {
        $check = $conn->prepare(
            "SELECT studentid, email, phone
             FROM registration
             WHERE studentid = ? OR email = ? OR phone = ?"
        );

        if (!$check) {
            $inputError['general'] = 'Database query could not be prepared';
        } else {
            $check->bind_param('sss', $studentid, $email, $phone);

            if (!$check->execute()) {
                $inputError['general'] = 'Could not check existing student information';
            } else {
                $result = $check->get_result();

                while ($row = $result->fetch_assoc()) {
                    if ($row['studentid'] === $studentid) {
                        $inputError['studentid'] = 'This Student ID is already registered';
                    }
                    if (strcasecmp($row['email'], $email) === 0) {
                        $inputError['email'] = 'This Email is already registered';
                    }
                    if ($row['phone'] === $phone) {
                        $inputError['phone'] = 'This Phone Number is already registered';
                    }
                }
            }
            $check->close();
        }
    }

    // Validate and save optional profile photo
    $profileImage = null;
    $savedImagePath = null;

    if (empty($inputError)) {
        if (
            isset($_FILES['profile_image']) &&
            $_FILES['profile_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                $inputError['profile_image'] = 'Image upload failed. Please try again.';
            } elseif ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
                $inputError['profile_image'] = 'Image size must be 2 MB or less.';
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
                    $inputError['profile_image'] = 'Only valid JPG, PNG or WEBP images are allowed.';
                } else {
                    // register.php is in admin/registration/.
                    // This path points to dynamic/Uploads_Photo/.
                    $uploadDir = __DIR__ . '/../../Uploads_Photo/';

                    if (!is_dir($uploadDir)) {
                        $inputError['profile_image'] =
                            'Uploads_Photo folder was not found. Create it inside the dynamic folder.';
                    } elseif (!is_writable($uploadDir)) {
                        $inputError['profile_image'] =
                            'Uploads_Photo folder is not writable.';
                    } else {
                        $profileImage = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
                        $savedImagePath = $uploadDir . $profileImage;

                        if (!move_uploaded_file($tmpName, $savedImagePath)) {
                            $inputError['profile_image'] = 'Could not save the uploaded image.';
                            $savedImagePath = null;
                            $profileImage = null;
                        }
                    }
                }
            }
        }
    }

    // Insert student data
    if (empty($inputError)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $reg_time = date('Y-m-d H:i:s');

        $stmt = $conn->prepare(
            "INSERT INTO registration
            (fullname, studentid, dob, gender, department, semester,
             email, phone, address, password, profile_image, reg_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            $inputError['general'] = 'Registration query could not be prepared';
        } else {
            $stmt->bind_param(
                'ssssssssssss',
                $fullname,
                $studentid,
                $dob,
                $gender,
                $department,
                $semester,
                $email,
                $phone,
                $address,
                $hashedPassword,
                $profileImage,
                $reg_time
            );

            if ($stmt->execute()) {
                $success = 'Student registered successfully!';

                $fullname = '';
                $studentid = '';
                $dob = '';
                $gender = '';
                $department = '';
                $semester = '';
                $email = '';
                $phone = '';
                $address = '';
            } else {
                if ($stmt->errno === 1062) {
                    $inputError['general'] = 'Student ID, Email or Phone already exists';
                } else {
                    $inputError['general'] = 'Registration failed. Please try again';
                }
            }
            $stmt->close();
        }

        // Remove uploaded file if database insert failed
        if ($success === '' && $savedImagePath !== null && is_file($savedImagePath)) {
            unlink($savedImagePath);
        }
    }

    // Never repopulate password fields
    $password = '';
    $confirmpassword = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link rel="stylesheet" href="css/register.css">
</head>
<body>

<div class="student-form">
    <div class="form-header">
        <h1>Student Registration</h1>
        <p>Please fill in the information below to register a student.</p>
    </div>

    <div class="form-body">
        <?php if ($success !== ''): ?>
            <div class="success-message"><?php echo e($success); ?></div>
        <?php endif; ?>

        <?php if (isset($inputError['general'])): ?>
            <div class="error-message"><?php echo e($inputError['general']); ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <h3 class="section-title">Personal Information</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input type="text" id="fullname" name="fullname"
                           placeholder="Enter student's full name" maxlength="150"
                           value="<?php echo e($fullname); ?>" required>
                    <small class="field-error"><?php echo e($inputError['fullname'] ?? ''); ?></small>
                </div>

                <div class="form-group">
                    <label for="studentid">Student ID</label>
                    <input type="text" id="studentid" name="studentid"
                           placeholder="Enter student ID" maxlength="50"
                           value="<?php echo e($studentid); ?>" required>
                    <small class="field-error"><?php echo e($inputError['studentid'] ?? ''); ?></small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob"
                           max="<?php echo date('Y-m-d'); ?>"
                           value="<?php echo e($dob); ?>" required>
                    <small class="field-error"><?php echo e($inputError['dob'] ?? ''); ?></small>
                </div>

                <div class="form-group">
                    <label>Gender</label>
                    <div class="gender-box">
                        <label><input type="radio" name="gender" value="male"
                            <?php echo $gender === 'male' ? 'checked' : ''; ?>> Male</label>
                        <label><input type="radio" name="gender" value="female"
                            <?php echo $gender === 'female' ? 'checked' : ''; ?>> Female</label>
                        <label><input type="radio" name="gender" value="other"
                            <?php echo $gender === 'other' ? 'checked' : ''; ?>> Other</label>
                    </div>
                    <small class="field-error"><?php echo e($inputError['gender'] ?? ''); ?></small>
                </div>
            </div>

            <h3 class="section-title">Academic Information</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department" required>
                        <option value="">Select Department</option>
                        <option value="cse" <?php echo $department === 'cse' ? 'selected' : ''; ?>>Computer Science &amp; Engineering</option>
                        <option value="eee" <?php echo $department === 'eee' ? 'selected' : ''; ?>>Electrical &amp; Electronic Engineering</option>
                        <option value="bba" <?php echo $department === 'bba' ? 'selected' : ''; ?>>Business Administration</option>
                        <option value="english" <?php echo $department === 'english' ? 'selected' : ''; ?>>English</option>
                        <option value="law" <?php echo $department === 'law' ? 'selected' : ''; ?>>Law</option>
                    </select>
                    <small class="field-error"><?php echo e($inputError['department'] ?? ''); ?></small>
                </div>

                <div class="form-group">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" required>
                        <option value="">Select Semester</option>
                        <?php foreach (['1st','2nd','3rd','4th','5th','6th','7th','8th'] as $sem): ?>
                            <option value="<?php echo e($sem); ?>"
                                <?php echo $semester === $sem ? 'selected' : ''; ?>>
                                <?php echo e($sem); ?> Semester
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-error"><?php echo e($inputError['semester'] ?? ''); ?></small>
                </div>
            </div>

            <h3 class="section-title">Contact Information</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email"
                           placeholder="student@example.com" maxlength="254"
                           value="<?php echo e($email); ?>" required>
                    <small class="field-error"><?php echo e($inputError['email'] ?? ''); ?></small>
                </div>

                <div class="form-group">
                    <label for="profile_image">Student Photo (optional, max 2 MB)</label>
                    <input type="file" id="profile_image" name="profile_image"
                           accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <small class="field-error"><?php echo e($inputError['profile_image'] ?? ''); ?></small>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone"
                           placeholder="+880 1XXXXXXXXX" maxlength="20"
                           value="<?php echo e($phone); ?>" required>
                    <small class="field-error"><?php echo e($inputError['phone'] ?? ''); ?></small>
                </div>
            </div>

            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" maxlength="500"
                          placeholder="Enter student's address" required><?php echo e($address); ?></textarea>
                <small class="field-error"><?php echo e($inputError['address'] ?? ''); ?></small>
            </div>

            <h3 class="section-title">Password Information</h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password"
                           placeholder="Minimum 8 characters" minlength="8"
                           autocomplete="new-password" required>
                    <small class="field-error"><?php echo e($inputError['password'] ?? ''); ?></small>
                </div>

                <div class="form-group">
                    <label for="confirmpassword">Confirm Password</label>
                    <input type="password" id="confirmpassword" name="confirmpassword"
                           placeholder="Re-enter password" autocomplete="new-password" required>
                    <small class="field-error"><?php echo e($inputError['confirmpassword'] ?? ''); ?></small>
                </div>
            </div>

            <div class="button-area">
                <button type="reset" class="reset-btn">Reset</button>
                <button type="submit" name="register" class="register-btn">Register Student</button>
            </div>

            <p class="login-link">
                Already have an account?
                <a href="../login/login.php">Login Now</a>
            </p>
        </form>
    </div>
</div>

</body>
</html>
