
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
// 4. VERIFY USER FROM DATABASE
// ========================================

$logged_in_id = $_SESSION['student_id'];

$stmt = $conn->prepare(
    "SELECT role FROM registration
     WHERE studentid = ?
     LIMIT 1"
);

$stmt->bind_param("s", $logged_in_id);
$stmt->execute();

$result = $stmt->get_result();
$current_user = $result->fetch_assoc();

$stmt->close();

if (
    !$current_user ||
    !in_array($current_user['role'], ['admin', 'student'], true)
) {
    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit;
}

$_SESSION['role'] = $current_user['role'];


// ========================================
// 5. GET STUDENT ID
// ========================================

$selected_student_id = '';

if ($_SESSION['role'] === 'admin') {

    $selected_student_id = trim($_GET['studentid'] ?? '');

} else {

    // Student can only see own ID card

    $selected_student_id = $logged_in_id;
}


// ========================================
// 6. GET STUDENT LIST FOR ADMIN
// ========================================

$students = [];

if ($_SESSION['role'] === 'admin') {

    $list_query = $conn->query(
        "SELECT studentid, fullname
         FROM registration
         WHERE role = 'student'
         ORDER BY fullname ASC"
    );

    if ($list_query) {
        while ($row = $list_query->fetch_assoc()) {
            $students[] = $row;
        }
    }
}


// ========================================
// 7. GET SELECTED STUDENT DETAILS
// ========================================

$student = null;

if ($selected_student_id !== '') {

    $stmt = $conn->prepare(
        "SELECT fullname, studentid, profile_image,
                department, semester, email
         FROM registration
         WHERE studentid = ?
         AND role = 'student'
         LIMIT 1"
    );

    $stmt->bind_param("s", $selected_student_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Student ID Card</title>

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

        .page-container {
            max-width: 900px;
            margin: auto;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h2 {
            margin-bottom: 8px;
        }

        .page-heading p {
            color: #64748b;
        }

        .student-select {
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .student-select label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .student-select select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        .id-card {
            max-width: 420px;
            margin: 25px auto;
            background: #ffffff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
            border: 1px solid #dbeafe;
        }

        .card-header {
            background: #1d4ed8;
            color: white;
            padding: 22px 15px;
            text-align: center;
        }

        .card-header h2 {
            margin: 0 0 7px;
            font-size: 24px;
        }

        .card-header p {
            margin: 0;
            font-size: 13px;
        }

        .card-body {
            padding: 25px;
            text-align: center;
        }

        .student-photo {
            width: 115px;
            height: 115px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #dbeafe;
            margin-bottom: 12px;
        }

        .student-name {
            font-size: 21px;
            color: #1e40af;
            margin: 5px 0 8px;
        }

        .student-id {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            padding: 7px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .student-details {
            text-align: left;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 9px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .detail-row strong {
            color: #475569;
        }

        .detail-row span {
            text-align: right;
            overflow-wrap: anywhere;
        }

        .card-footer {
            background: #eff6ff;
            color: #1d4ed8;
            text-align: center;
            padding: 13px;
            font-size: 12px;
            font-weight: bold;
        }

        .print-button {
            display: block;
            margin: 20px auto;
            padding: 12px 28px;
            border: none;
            border-radius: 7px;
            background: #1d4ed8;
            color: white;
            font-size: 15px;
            cursor: pointer;
        }

        .print-button:hover {
            background: #1e40af;
        }

        .empty-message {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            color: #64748b;
        }

        @media print {

            body {
                background: white;
                padding: 0;
            }

            .page-heading,
            .student-select,
            .print-button {
                display: none !important;
            }

            .page-container {
                max-width: 100%;
            }

            .id-card {
                margin: 20px auto;
                box-shadow: none;
                border: 1px solid #cbd5e1;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

        }

    </style>

</head>

<body>

<div class="page-container">

    <div class="page-heading">

        <h2>Student ID Card</h2>

        <p>Student পরিচয়পত্র দেখুন এবং প্রিন্ট করুন।</p>

    </div>


    <!-- ADMIN STUDENT SELECT -->

    <?php if ($_SESSION['role'] === 'admin'): ?>

        <div class="student-select">

            <form method="GET">

                <label>Select Student / শিক্ষার্থী নির্বাচন করুন</label>

                <select
                    name="studentid"
                    onchange="this.form.submit()"
                >

                    <option value="">-- Select Student --</option>

                    <?php foreach ($students as $item): ?>

                        <option
                            value="<?php echo htmlspecialchars(
                                $item['studentid'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                            <?php echo (
                                $selected_student_id === $item['studentid']
                            ) ? 'selected' : ''; ?>
                        >

                            <?php echo htmlspecialchars(
                                $item['fullname'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>

                            (<?php echo htmlspecialchars(
                                $item['studentid'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>)

                        </option>

                    <?php endforeach; ?>

                </select>

            </form>

        </div>

    <?php endif; ?>


    <!-- STUDENT ID CARD -->

    <?php if ($student): ?>

        <div class="id-card">

            <div class="card-header">

                <h2>Dynamic</h2>

                <p>STUDENT IDENTITY CARD</p>

            </div>


            <div class="card-body">

                <?php

                $photo = !empty($student['profile_image'])
                    ? '../../Uploads_Photo/' . $student['profile_image']
                    : '../../Uploads_Photo/default.png';

                ?>

                <img
                    class="student-photo"
                    src="<?php echo htmlspecialchars(
                        $photo,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>"
                    alt="Student Photo"
                    onerror="this.onerror=null;this.src='../../Uploads_Photo/default.png';"
                >


                <h3 class="student-name">

                    <?php echo htmlspecialchars(
                        $student['fullname'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </h3>


                <div class="student-id">

                    ID:
                    <?php echo htmlspecialchars(
                        $student['studentid'],
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>

                </div>


                <div class="student-details">

                    <div class="detail-row">

                        <strong>Department</strong>

                        <span>
                            <?php echo htmlspecialchars(
                                $student['department'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                    </div>


                    <div class="detail-row">

                        <strong>Semester</strong>

                        <span>
                            <?php echo htmlspecialchars(
                                $student['semester'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                    </div>


                    <div class="detail-row">

                        <strong>Email</strong>

                        <span>
                            <?php echo htmlspecialchars(
                                $student['email'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>

                    </div>

                </div>

            </div>


            <div class="card-footer">

                DYNAMIC | STUDENT MANAGEMENT SYSTEM

            </div>

        </div>


        <button
            type="button"
            class="print-button"
            onclick="window.print()"
        >
            🖨 Print ID Card
        </button>


    <?php elseif ($_SESSION['role'] === 'admin' && $selected_student_id === ''): ?>

        <div class="empty-message">
            Please select a student to view the ID Card.
        </div>


    <?php else: ?>

        <div class="empty-message">
            Student information could not be found.
        </div>

    <?php endif; ?>

</div>

</body>
</html>
