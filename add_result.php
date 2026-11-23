
<?php
session_start();

require_once '../../config/db.php';

// Admin Login Check
if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}

// Live Database Role Check
$adminId = $_SESSION['student_id'];

$adminQuery = $conn->prepare(
    "SELECT role FROM registration WHERE studentid = ?"
);
$adminQuery->bind_param("s", $adminId);
$adminQuery->execute();

$adminResult = $adminQuery->get_result();
$admin = $adminResult->fetch_assoc();
$adminQuery->close();

if (!$admin || $admin['role'] !== 'admin') {
    session_unset();
    session_destroy();
    header("Location: ../login/login.php");
    exit();
}

// Student ID Check Request
if (isset($_GET['check_student'])) {

    header('Content-Type: application/json; charset=utf-8');

    $studentId = trim($_GET['check_student']);

    $checkQuery = $conn->prepare(
        "SELECT fullname FROM registration
         WHERE studentid = ? AND role = 'student'
         LIMIT 1"
    );

    $checkQuery->bind_param("s", $studentId);
    $checkQuery->execute();

    $checkResult = $checkQuery->get_result();
    $student = $checkResult->fetch_assoc();

    $checkQuery->close();

    if ($student) {
        echo json_encode([
            'success' => true,
            'fullname' => $student['fullname']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Student not found!'
        ]);
    }

    exit();
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

// Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } else {

        $studentId = trim($_POST['studentid'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $examType = trim($_POST['exam_type'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $marksInput = $_POST['marks'] ?? '';
        $totalInput = $_POST['total_marks'] ?? '';
        $resultDate = $_POST['result_date'] ?? '';

        // Basic Validation
        if (
            $studentId === '' ||
            $subject === '' ||
            $examType === '' ||
            $semester === '' ||
            $marksInput === '' ||
            $totalInput === '' ||
            $resultDate === ''
        ) {
            $message = "Please fill in all fields.";
            $messageType = "error";

        } elseif (
            !is_numeric($marksInput) ||
            !is_numeric($totalInput) ||
            (float)$totalInput <= 0 ||
            (float)$marksInput < 0 ||
            (float)$marksInput > (float)$totalInput
        ) {
            $message = "Please enter valid marks.";
            $messageType = "error";

        } else {

            $marks = (float)$marksInput;
            $totalMarks = (float)$totalInput;

            // Validate Date
            $dateObject = DateTime::createFromFormat('Y-m-d', $resultDate);

            if (
                !$dateObject ||
                $dateObject->format('Y-m-d') !== $resultDate
            ) {
                $message = "Please enter a valid result date.";
                $messageType = "error";

            } else {

                // Check Student
                $studentQuery = $conn->prepare(
                    "SELECT studentid FROM registration
                     WHERE studentid = ? AND role = 'student'
                     LIMIT 1"
                );

                $studentQuery->bind_param("s", $studentId);
                $studentQuery->execute();

                $studentResult = $studentQuery->get_result();
                $student = $studentResult->fetch_assoc();

                $studentQuery->close();

                if (!$student) {

                    $message = "Student ID not found!";
                    $messageType = "error";

                } else {

                    // Calculate Grade Automatically
                    $percentage = ($marks / $totalMarks) * 100;

                    if ($percentage >= 80) {
                        $grade = "A+";
                    } elseif ($percentage >= 70) {
                        $grade = "A";
                    } elseif ($percentage >= 60) {
                        $grade = "A-";
                    } elseif ($percentage >= 50) {
                        $grade = "B";
                    } elseif ($percentage >= 40) {
                        $grade = "C";
                    } elseif ($percentage >= 33) {
                        $grade = "D";
                    } else {
                        $grade = "F";
                    }

                    // Insert Result
                    $insertQuery = $conn->prepare(
                        "INSERT INTO results
                        (
                            studentid,
                            subject,
                            marks,
                            total_marks,
                            grade,
                            exam_type,
                            semester,
                            result_date,
                            created_by
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    $insertQuery->bind_param(
                        "ssddsssss",
                        $studentId,
                        $subject,
                        $marks,
                        $totalMarks,
                        $grade,
                        $examType,
                        $semester,
                        $resultDate,
                        $adminId
                    );

                    if ($insertQuery->execute()) {

                        $message = "Student result added successfully! Grade: " . $grade;
                        $messageType = "success";

                    } else {

                        $message = "Something went wrong. Please try again.";
                        $messageType = "error";
                    }

                    $insertQuery->close();
                }
            }
        }
    }
}

// Safe Output
function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Student Result</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            padding: 30px 15px;
        }

        .container {
            max-width: 750px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .heading {
            text-align: center;
            margin-bottom: 25px;
        }

        .heading h2 {
            color: #1e40af;
            margin-bottom: 8px;
        }

        .heading p {
            color: #64748b;
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #334155;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
            outline: none;
        }

        input:focus, select:focus {
            border-color: #2563eb;
        }

        .student-name {
            margin-top: 7px;
            font-size: 14px;
            color: #15803d;
            font-weight: bold;
        }

        .student-error {
            color: #dc2626;
        }

        .grade-note {
            background: #eff6ff;
            padding: 13px;
            border-radius: 7px;
            color: #1e40af;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
            text-align: center;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        @media(max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="heading">
        <h2>🎓 Add Student Result</h2>
        <p>শিক্ষার্থীর পরীক্ষার ফলাফল যোগ করুন</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="message <?php echo clean($messageType); ?>">
            <?php echo clean($message); ?>
        </div>
    <?php endif; ?>

    <div class="grade-note">
        <strong>Automatic Grade System:</strong><br>
        A+ = 80% or above | A = 70%–79% | A- = 60%–69%<br>
        B = 50%–59% | C = 40%–49% | D = 33%–39% | F = Below 33%
    </div>

    <form method="POST" action="">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo clean($_SESSION['csrf_token']); ?>"
        >

        <div class="form-grid">

            <div class="form-group full-width">
                <label>Student ID / শিক্ষার্থী আইডি</label>

                <input
                    type="text"
                    name="studentid"
                    id="studentid"
                    placeholder="Enter Student ID"
                    required
                >

                <div id="studentName" class="student-name"></div>
            </div>

            <div class="form-group">
                <label>Subject / বিষয়</label>

                <input
                    type="text"
                    name="subject"
                    placeholder="Enter Subject Name"
                    required
                >
            </div>

            <div class="form-group">
                <label>Exam Type / পরীক্ষার ধরন</label>

                <select name="exam_type" required>
                    <option value="">Select Exam</option>
                    <option value="Class Test">Class Test</option>
                    <option value="Midterm">Midterm</option>
                    <option value="Final Exam">Final Exam</option>
                    <option value="Assignment">Assignment</option>
                </select>
            </div>

            <div class="form-group">
                <label>Semester / সেমিস্টার</label>

                <select name="semester" required>
                    <option value="">Select Semester</option>
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
                    <option value="3rd Semester">3rd Semester</option>
                    <option value="4th Semester">4th Semester</option>
                    <option value="5th Semester">5th Semester</option>
                    <option value="6th Semester">6th Semester</option>
                    <option value="7th Semester">7th Semester</option>
                    <option value="8th Semester">8th Semester</option>
                </select>
            </div>

            <div class="form-group">
                <label>Obtained Marks / প্রাপ্ত নম্বর</label>

                <input
                    type="number"
                    name="marks"
                    min="0"
                    step="0.01"
                    placeholder="Example: 85"
                    required
                >
            </div>

            <div class="form-group">
                <label>Total Marks / পূর্ণ নম্বর</label>

                <input
                    type="number"
                    name="total_marks"
                    min="0.01"
                    step="0.01"
                    placeholder="Example: 100"
                    required
                >
            </div>

            <div class="form-group full-width">
                <label>Result Date / ফলাফলের তারিখ</label>

                <input
                    type="date"
                    name="result_date"
                    required
                >
            </div>

        </div>

        <button type="submit" class="btn">
            Save Student Result
        </button>

    </form>

    <a href="admin_index.php" class="back-link">
        ← Back to Dashboard
    </a>

</div>

<script>
const studentInput = document.getElementById('studentid');
const studentName = document.getElementById('studentName');

let searchTimer;

studentInput.addEventListener('input', function () {

    clearTimeout(searchTimer);

    const studentId = this.value.trim();

    studentName.textContent = '';

    if (studentId === '') {
        return;
    }

    searchTimer = setTimeout(async function () {

        try {

            const response = await fetch(
                'add_result.php?check_student=' + encodeURIComponent(studentId)
            );

            const data = await response.json();

            if (data.success) {

                studentName.className = 'student-name';
                studentName.textContent = 'Student Name: ' + data.fullname;

            } else {

                studentName.className = 'student-name student-error';
                studentName.textContent = 'Student not found!';

            }

        } catch (error) {

            studentName.className = 'student-name student-error';
            studentName.textContent = 'Unable to check Student ID.';

        }

    }, 400);

});
</script>

</body>
</html>
