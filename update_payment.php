
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

// Fetch Fee Details
$stmt = $conn->prepare("
    SELECT
        f.id,
        f.studentid,
        r.fullname,
        f.fee_type,
        f.fee_period,
        f.amount,
        f.paid_amount
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

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = "";
$message_type = "";

$payment_methods = [
    "Cash",
    "Bank Transfer",
    "Mobile Banking",
    "Card",
    "Other"
];

// Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Check
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die("Invalid request. Please refresh the page.");
    }

    $amount_input = trim($_POST['payment_amount'] ?? '');
    $payment_date = trim($_POST['payment_date'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $note = trim($_POST['note'] ?? '');

    // Validation
    if (
        $amount_input === '' ||
        $payment_date === '' ||
        $payment_method === ''
    ) {

        $message = "Please fill in all required fields.";
        $message_type = "error";

    } elseif (
        !preg_match('/^\d+(\.\d{1,2})?$/', $amount_input) ||
        !is_finite((float)$amount_input) ||
        (float)$amount_input <= 0
    ) {

        $message = "Please enter a valid payment amount.";
        $message_type = "error";

    } elseif (!in_array($payment_method, $payment_methods, true)) {

        $message = "Invalid payment method.";
        $message_type = "error";

    } elseif (
        !DateTime::createFromFormat('!Y-m-d', $payment_date) ||
        DateTime::createFromFormat('!Y-m-d', $payment_date)->format('Y-m-d') !== $payment_date
    ) {

        $message = "Please enter a valid payment date.";
        $message_type = "error";

    } else {

        $payment_amount = (float)$amount_input;

        try {

            // Start Database Transaction
            $conn->begin_transaction();

            // Lock Fee Record
            $lock = $conn->prepare("
                SELECT amount, paid_amount
                FROM fees
                WHERE id = ?
                FOR UPDATE
            ");

            $lock->bind_param("i", $id);
            $lock->execute();

            $locked_result = $lock->get_result();
            $locked_fee = $locked_result->fetch_assoc();

            $lock->close();

            if (!$locked_fee) {

                throw new Exception("Fee record not found.");

            }

            $total_amount = (float)$locked_fee['amount'];
            $already_paid = (float)$locked_fee['paid_amount'];

            $due_amount = round($total_amount - $already_paid, 2);

            // Check Remaining Due
            if ($due_amount <= 0) {

                throw new Exception("This fee is already fully paid.");

            }

            // Payment Cannot Exceed Due
            elseif ($payment_amount > $due_amount) {

                throw new Exception(
                    "Payment cannot exceed remaining due amount."
                );

            } else {

                // Insert Payment History
                $insert = $conn->prepare("
                    INSERT INTO fee_payments
                    (
                        fee_id,
                        amount,
                        payment_date,
                        payment_method,
                        note,
                        received_by
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $insert->bind_param(
                    "idssss",
                    $id,
                    $payment_amount,
                    $payment_date,
                    $payment_method,
                    $note,
                    $admin_id
                );

                if (!$insert->execute()) {
                    throw new Exception("Unable to save payment.");
                }

                $insert->close();

                // Update Total Paid Amount
                $new_paid_amount = round(
                    $already_paid + $payment_amount,
                    2
                );

                $update = $conn->prepare("
                    UPDATE fees
                    SET
                        paid_amount = ?,
                        payment_date = ?,
                        payment_method = ?
                    WHERE id = ?
                ");

                $update->bind_param(
                    "dssi",
                    $new_paid_amount,
                    $payment_date,
                    $payment_method,
                    $id
                );

                if (!$update->execute()) {
                    throw new Exception("Unable to update fee balance.");
                }

                $update->close();

                // Commit Both Changes
                $conn->commit();

                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header("Location: manage_fees.php?payment=success");
                exit();
            }

        } catch (Throwable $error) {

            // Undo Changes if Something Fails
            $conn->rollback();

            $message = $error->getMessage();
            $message_type = "error";
        }
    }
}

// Refresh Fee Details
$stmt = $conn->prepare("
    SELECT
        f.amount,
        f.paid_amount
    FROM fees f
    WHERE f.id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$updated_result = $stmt->get_result();
$updated_fee = $updated_result->fetch_assoc();

$stmt->close();

if ($updated_fee) {
    $fee['amount'] = $updated_fee['amount'];
    $fee['paid_amount'] = $updated_fee['paid_amount'];
}

// Calculate Due
$total_amount = (float)$fee['amount'];
$already_paid = (float)$fee['paid_amount'];

$due_amount = max(0, round($total_amount - $already_paid, 2));

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

<title>Update Payment | Student Management System</title>

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
    max-width: 700px;
    margin: 25px auto;
    padding: 30px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

h2 {
    color: #1e40af;
    margin-top: 0;
}

.subtitle {
    color: #64748b;
    margin-bottom: 25px;
}

.fee-info {
    background: #eff6ff;
    padding: 18px;
    border-radius: 8px;
    margin-bottom: 22px;
    line-height: 1.9;
}

.balance {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 25px;
}

.balance-card {
    padding: 15px 10px;
    text-align: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.balance-card p {
    color: #64748b;
    font-size: 13px;
    margin: 0 0 8px;
}

.balance-card h3 {
    color: #1e40af;
    font-size: 19px;
    margin: 0;
}

.due h3 {
    color: #dc2626;
}

.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    font-weight: bold;
    color: #334155;
    margin-bottom: 8px;
}

input, select, textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
}

input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: #2563eb;
}

textarea {
    resize: vertical;
    min-height: 85px;
}

.btn {
    display: inline-block;
    padding: 12px 20px;
    background: #2563eb;
    color: white;
    border: none;
    border-radius: 7px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
}

.btn:hover {
    background: #1d4ed8;
}

.back-btn {
    background: #64748b;
    margin-left: 8px;
}

.back-btn:hover {
    background: #475569;
}

.message {
    padding: 13px;
    border-radius: 7px;
    margin-bottom: 20px;
    font-weight: bold;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.completed {
    padding: 15px;
    background: #dcfce7;
    color: #166534;
    border-radius: 8px;
    font-weight: bold;
    margin-bottom: 20px;
}

@media(max-width: 600px) {

    body {
        padding: 12px;
    }

    .container {
        padding: 20px;
    }

    .balance {
        grid-template-columns: 1fr;
    }

    .btn {
        display: block;
        width: 100%;
        margin: 7px 0;
        text-align: center;
    }

}

</style>

</head>

<body>

<div class="container">

    <h2>💳 Update Student Payment</h2>

    <p class="subtitle">
        Receive student payment / শিক্ষার্থীর জমা গ্রহণ করুন।
    </p>

    <div class="fee-info">

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

    </div>

    <div class="balance">

        <div class="balance-card">
            <p>Total Fee</p>
            <h3>৳<?php echo number_format($total_amount, 2); ?></h3>
        </div>

        <div class="balance-card">
            <p>Already Paid</p>
            <h3>৳<?php echo number_format($already_paid, 2); ?></h3>
        </div>

        <div class="balance-card due">
            <p>Remaining Due</p>
            <h3>৳<?php echo number_format($due_amount, 2); ?></h3>
        </div>

    </div>

    <?php if ($message !== ''): ?>

        <div class="message <?php echo e($message_type); ?>">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>

    <?php if ($due_amount <= 0): ?>

        <div class="completed">
            <span>✓</span>
            This fee is fully paid / এই ফি সম্পূর্ণ পরিশোধ করা হয়েছে।
        </div>

    <?php else: ?>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo e($_SESSION['csrf_token']); ?>"
            >

            <div class="form-group">

                <label>Payment Amount (৳) / জমার পরিমাণ</label>

                <input
                    type="number"
                    name="payment_amount"
                    min="0.01"
                    max="<?php echo e(number_format($due_amount, 2, '.', '')); ?>"
                    step="0.01"
                    placeholder="Enter payment amount"
                    value="<?php echo e($_POST['payment_amount'] ?? ''); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Payment Date / জমার তারিখ</label>

                <input
                    type="date"
                    name="payment_date"
                    value="<?php echo e($_POST['payment_date'] ?? date('Y-m-d')); ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label>Payment Method / পেমেন্টের মাধ্যম</label>

                <select name="payment_method" required>

                    <option value="">-- Select Payment Method --</option>

                    <?php foreach ($payment_methods as $method): ?>

                        <option
                            value="<?php echo e($method); ?>"
                            <?php echo (
                                ($_POST['payment_method'] ?? '') === $method
                            ) ? 'selected' : ''; ?>
                        >
                            <?php echo e($method); ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>Note (Optional) / অতিরিক্ত তথ্য</label>

                <textarea
                    name="note"
                    placeholder="Write payment details..."
                ><?php echo e($_POST['note'] ?? ''); ?></textarea>

            </div>

            <button type="submit" class="btn">
                💰 Receive Payment
            </button>

            <a href="manage_fees.php" class="btn back-btn">
                ← Back to Manage Fees
            </a>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
