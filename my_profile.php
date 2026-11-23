
<?php

// ========================================
// 1. START SESSION
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. CHECK LOGIN
// ========================================

if (
    !isset($_SESSION['student_id']) ||
    !isset($_SESSION['role'])
) {
    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 3. DATABASE CONNECTION
// ========================================

require_once '../../config/db.php';


// ========================================
// 4. GET LOGGED-IN USER
// ========================================

$student_id = $_SESSION['student_id'];

$stmt = $conn->prepare(
    "SELECT fullname, studentid, email, phone,
            dob, gender, department, semester,
            address, profile_image, role
     FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$stmt->bind_param("s", $student_id);
$stmt->execute();

$result = $stmt->get_result();
$profile = $result->fetch_assoc();

$stmt->close();


// ========================================
// 5. VERIFY ACCOUNT
// ========================================

if (
    !$profile ||
    !in_array($profile['role'], ['admin', 'student'], true)
) {
    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit;
}


// ========================================
// 6. HELPER FUNCTION
// ========================================

function showValue($value)
{
    if ($value === null || trim((string)$value) === '') {
        return 'Not Provided';
    }

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


// ========================================
// 7. PROFILE PHOTO
// ========================================

$photo = !empty($profile['profile_image'])
    ? '../../Uploads_Photo/' . $profile['profile_image']
    : '../../Uploads_Photo/default.png';

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Profile</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            background: #f1f5f9;
            font-family: Arial, sans-serif;
            color: #1e293b;
        }

        .profile-container {
            max-width: 850px;
            margin: auto;
        }

        .profile-heading {
            margin-bottom: 25px;
        }

        .profile-heading h2 {
            margin-bottom: 8px;
            color: #1e293b;
        }

        .profile-heading p {
            color: #64748b;
            font-size: 14px;
        }

        .profile-card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0,0,0,0.06);
        }

        .profile-header {
            background: #1d4ed8;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            margin-bottom: 12px;
        }

        .profile-header h2 {
            margin: 5px 0;
            font-size: 23px;
        }

        .profile-header p {
            margin: 5px 0;
            font-size: 14px;
        }

        .role-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 6px 15px;
            border-radius: 20px;
            background: white;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: bold;
        }

        .profile-details {
            padding: 25px;
        }

        .profile-details h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #1d4ed8;
            font-size: 18px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .detail-item {
            padding: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            min-width: 0;
        }

        .detail-item label {
            display: block;
            color: #64748b;
            font-size: 12px;
            margin-bottom: 7px;
        }

        .detail-item strong {
            display: block;
            color: #1e293b;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        @media (max-width: 600px) {

            body {
                padding: 15px;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<div class="profile-container">


    <!-- PAGE HEADING -->

    <div class="profile-heading">

        <h2>My Profile</h2>

        <p>আমার অ্যাকাউন্টের ব্যক্তিগত তথ্য</p>

    </div>


    <!-- PROFILE CARD -->

    <div class="profile-card">


        <!-- PROFILE HEADER -->

        <div class="profile-header">

            <img
                src="<?php echo showValue($photo); ?>"
                alt="Profile Photo"
                class="profile-photo"
                onerror="this.onerror=null;this.src='../../Uploads_Photo/default.png';"
            >

            <h2>
                <?php echo showValue($profile['fullname']); ?>
            </h2>

            <p>
                ID:
                <?php echo showValue($profile['studentid']); ?>
            </p>

            <span class="role-badge">

                <?php
                echo ($profile['role'] === 'admin')
                    ? 'ADMIN ACCOUNT'
                    : 'STUDENT ACCOUNT';
                ?>

            </span>

        </div>
        <a href="edit_profile.php"
   style="
       display: inline-block;
       padding: 10px 20px;
       background: #2563eb;
       color: white;
       text-decoration: none;
       border-radius: 6px;
       margin-bottom: 15px;
   ">
    Edit Profile / প্রোফাইল সম্পাদনা
</a>


        <!-- PROFILE DETAILS -->

        <div class="profile-details">

            <h3>Personal Information / ব্যক্তিগত তথ্য</h3>

            <div class="details-grid">


                <div class="detail-item">

                    <label>Email Address / ইমেইল</label>

                    <strong>
                        <?php echo showValue($profile['email']); ?>
                    </strong>

                </div>


                <div class="detail-item">

                    <label>Phone Number / ফোন</label>

                    <strong>
                        <?php echo showValue($profile['phone']); ?>
                    </strong>

                </div>


                <div class="detail-item">

                    <label>Date of Birth / জন্ম তারিখ</label>

                    <strong>
                        <?php echo showValue($profile['dob']); ?>
                    </strong>

                </div>


                <div class="detail-item">

                    <label>Gender / লিঙ্গ</label>

                    <strong>
                        <?php echo showValue($profile['gender']); ?>
                    </strong>

                </div>


                <div class="detail-item">

                    <label>Department / বিভাগ</label>

                    <strong>
                        <?php echo showValue($profile['department']); ?>
                    </strong>

                </div>


                <div class="detail-item">

                    <label>Semester / সেমিস্টার</label>

                    <strong>
                        <?php echo showValue($profile['semester']); ?>
                    </strong>

                </div>


                <div class="detail-item">

                    <label>Student ID / শিক্ষার্থী আইডি</label>

                    <strong>
                        <?php echo showValue($profile['studentid']); ?>
                    </strong>

                </div>


                <div class="detail-item">

                    <label>Account Role / অ্যাকাউন্টের ধরন</label>

                    <strong>
                        <?php echo showValue(ucfirst($profile['role'])); ?>
                    </strong>

                </div>


                <div class="detail-item">

                    <label>Address / ঠিকানা</label>

                    <strong>
                        <?php echo showValue($profile['address']); ?>
                    </strong>

                </div>


            </div>

        </div>

    </div>

</div>

</body>
</html>
