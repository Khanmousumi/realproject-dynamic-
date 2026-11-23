
<?php
session_start();

require_once '../../config/db.php';

// Admin Login Check
if (!isset($_SESSION['student_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login/login.php");
    exit();
}

// Only Admin Access
if ($_SESSION['role'] !== 'admin') {
    header("Location: admin_index.php");
    exit();
}

// Live Admin Role Check
$admin_id = $_SESSION['student_id'];

$admin_check = $conn->prepare(
    "SELECT role FROM registration WHERE studentid = ? LIMIT 1"
);

$admin_check->bind_param("s", $admin_id);
$admin_check->execute();

$admin_result = $admin_check->get_result();
$admin_user = $admin_result->fetch_assoc();

$admin_check->close();

if (!$admin_user || $admin_user['role'] !== 'admin') {
    session_unset();
    session_destroy();

    header("Location: ../login/login.php");
    exit();
}

// Validate Fee ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header("Location: manage_fees.php");
    exit();
}

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch Fee Details
$stmt = $conn->prepare("
    SELECT
        f.id,
        f.studentid,
        r.fullname,
        f.fee_type,
        f.fee_period,
        f.amount,
        f.paid_amount,
        f.due_date
    FROM fees f
    INNER JOIN registration r
        ON f.studentid = r.studentid
    WHERE f.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$fee = $result->fetch_assoc();

$stmt->close();

if (!$fee) {
    header("Location: manage_fees.php");
    exit();
}

$message = "";

// Delete Confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Check
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid request. Please refresh the page.");
    }

    // Delete Fee
    $delete = $conn->prepare(
        "DELETE FROM fees WHERE id = ?"
    );

    $delete->bind_param("i", $id);

    if ($delete->execute()) {

        $delete->close();

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        header("Location: manage_fees.php?deleted=1");
        exit();

    } else {

        $message = "Unable to delete fee. Please try again.";
        $delete->close();
    }
}

// Safe Output
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Delete Fee | Student Management System</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 25px;
    font-family: Arial, sans-serif;
    background: #f1f5f9;
}

.container {
    max-width: 600px;
    margin: 60px auto;
    padding: 30px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

h2 {
    color: #dc2626;
    margin-top: 0;
}

.warning {
    background: #fee2e2;
    color: #991b1b;
    padding: 15px;
    border-radius: 8px;
    margin: 20px 0;
    line-height: 1.6;
}

.fee-details {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 18px;
    margin-bottom: 22px;
    line-height: 2;
}

.btn {
    display: inline-block;
    padding: 12px 20px;
    border: none;
    border-radius: 7px;
    font-size: 15px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
}

.delete-btn {
    background: #dc2626;
    color: white;
}

.delete-btn:hover {
    background: #b91c1c;
}

.cancel-btn {
    background: #64748b;
    color: white;
    margin-left: 8px;
}

.cancel-btn:hover {
    background: #475569;
}

.error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 7px;
    margin-bottom: 18px;
}

@media(max-width: 600px) {

    body {
        padding: 12px;
    }

    .container {
        margin: 25px auto;
        padding: 20px;
    }

    .btn {
        display: block;
        width: 100%;
        text-align: center;
        margin: 8px 0;
    }

}

</style>

</head>

<body>

<div class="container">

    <h2>🗑️ Delete Student Fee</h2>

    <div class="warning">

        <strong>Warning!</strong>

        Are you sure you want to delete this fee record?

        <br>

        এই Fee Record ডিলিট করলে তা আর ফেরত পাওয়া যাবে না।

    </div>

    <?php if ($message !== ''): ?>

        <div class="error">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <div class="fee-details">

        <strong>Student ID:</strong>
        <?php echo e($fee['studentid']); ?>

        <br>

        <strong>Student Name:</strong>
        <?php echo e($fee['fullname']); ?>

        <br>

        <strong>Fee Type:</strong>
        <?php echo e($fee['fee_type']); ?>

        <br>

        <strong>Fee Period:</strong>
        <?php echo e($fee['fee_period']); ?>

        <br>

        <strong>Total Amount:</strong>
        ৳<?php echo number_format((float)$fee['amount'], 2); ?>

        <br>

        <strong>Paid Amount:</strong>
        ৳<?php echo number_format((float)$fee['paid_amount'], 2); ?>

        <br>

        <strong>Due Date:</strong>
        <?php echo e($fee['due_date']); ?>

    </div>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo e($_SESSION['csrf_token']); ?>"
        >

        <button
            type="submit"
            class="btn delete-btn"
            onclick="return confirm('Are you sure you want to permanently delete this fee record?');"
        >
            Yes, Delete Fee
        </button>

        <a href="manage_fees.php" class="btn cancel-btn">
            Cancel
        </a>

    </form>

</div>

</body>

</html>
