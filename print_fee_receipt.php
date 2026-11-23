
<?php
session_start();

require_once '../../config/db.php';

// Admin Login Check
if (!isset($_SESSION['student_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login/login.php");
    exit();
}

// Live Admin Role Check
$logged_in_id = $_SESSION['student_id'];

$role_sql = "SELECT role FROM registration WHERE studentid = ? LIMIT 1";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("s", $logged_in_id);
$role_stmt->execute();

$role_result = $role_stmt->get_result();
$admin_user = $role_result->fetch_assoc();
$role_stmt->close();

if (!$admin_user || $admin_user['role'] !== 'admin') {
    header("Location: admin_index.php");
    exit();
}

// Get Payment ID
$payment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$payment_id || $payment_id <= 0) {
    die("Invalid Payment ID.");
}

// Fetch Payment and Student Details
$sql = "SELECT
            fp.id AS payment_id,
            fp.amount AS paid_amount,
            fp.payment_date,
            fp.payment_method,
            fp.note,
            fp.received_by,
            f.fee_type,
            f.fee_period,
            f.amount AS total_fee,
            f.paid_amount AS total_paid,
            f.studentid,
            r.fullname,
            r.email,
            r.phone
        FROM fee_payments fp
        INNER JOIN fees f ON fp.fee_id = f.id
        INNER JOIN registration r ON f.studentid = r.studentid
        WHERE fp.id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $payment_id);
$stmt->execute();

$result = $stmt->get_result();
$receipt = $result->fetch_assoc();

$stmt->close();

if (!$receipt) {
    die("Payment Receipt Not Found.");
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Receipt Information
$receipt_number = "FEE-" . str_pad(
    (string)$receipt['payment_id'],
    6,
    "0",
    STR_PAD_LEFT
);

$remaining_due = max(
    0,
    (float)$receipt['total_fee'] - (float)$receipt['total_paid']
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Payment Receipt - <?php echo e($receipt_number); ?></title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .receipt {
            max-width: 750px;
            margin: 20px auto;
            background: white;
            padding: 40px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .receipt-header h1 {
            color: #1e40af;
            margin: 0 0 8px;
        }

        .receipt-header p {
            color: #64748b;
            margin: 5px 0;
        }

        .receipt-title {
            text-align: center;
            margin: 25px 0;
        }

        .receipt-title h2 {
            display: inline-block;
            color: #15803d;
            border: 2px solid #15803d;
            padding: 8px 25px;
            border-radius: 5px;
        }

        .receipt-info {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 25px;
        }

        .receipt-info p {
            margin: 7px 0;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 13px;
            text-align: left;
            font-size: 14px;
        }

        th {
            width: 40%;
            background: #eff6ff;
            color: #1e40af;
        }

        .paid-amount {
            color: #15803d;
            font-weight: bold;
            font-size: 18px;
        }

        .note {
            background: #f8fafc;
            padding: 12px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
        }

        .signature {
            display: flex;
            justify-content: space-between;
            margin-top: 65px;
            gap: 30px;
        }

        .signature div {
            width: 45%;
            text-align: center;
            border-top: 1px solid #64748b;
            padding-top: 10px;
            font-size: 13px;
        }

        .actions {
            max-width: 750px;
            margin: 20px auto;
            display: flex;
            justify-content: center;
            gap: 12px;
        }

        .actions a,
        .actions button {
            display: inline-block;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .actions a {
            background: #64748b;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .receipt {
                margin: 0 auto;
                border: none;
                border-radius: 0;
                padding: 20px;
                max-width: 100%;
            }

            .actions {
                display: none;
            }
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .receipt {
                padding: 18px;
            }

            .receipt-info {
                flex-direction: column;
            }

            .signature {
                gap: 15px;
            }
        }
    </style>
</head>

<body>

<div class="receipt">

    <div class="receipt-header">

        <h1>Student Management System</h1>

        <p>Official Fee Payment Receipt</p>

        <p>ফি জমার অফিসিয়াল রসিদ</p>

    </div>

    <div class="receipt-title">
        <h2>PAYMENT RECEIPT</h2>
    </div>

    <div class="receipt-info">

        <div>
            <p>
                <strong>Receipt No:</strong>
                <?php echo e($receipt_number); ?>
            </p>

            <p>
                <strong>Payment ID:</strong>
                <?php echo (int)$receipt['payment_id']; ?>
            </p>
        </div>

        <div>
            <p>
                <strong>Payment Date:</strong>
                <?php echo e($receipt['payment_date']); ?>
            </p>

            <p>
                <strong>Student ID:</strong>
                <?php echo e($receipt['studentid']); ?>
            </p>
        </div>

    </div>

    <h3>Student Information / শিক্ষার্থীর তথ্য</h3>

    <table>

        <tr>
            <th>Student Name</th>
            <td><?php echo e($receipt['fullname']); ?></td>
        </tr>

        <tr>
            <th>Student ID</th>
            <td><?php echo e($receipt['studentid']); ?></td>
        </tr>

        <tr>
            <th>Email</th>
            <td><?php echo e($receipt['email']); ?></td>
        </tr>

        <tr>
            <th>Phone</th>
            <td><?php echo e($receipt['phone']); ?></td>
        </tr>

    </table>

    <h3>Payment Information / পেমেন্টের তথ্য</h3>

    <table>

        <tr>
            <th>Fee Type</th>
            <td><?php echo e($receipt['fee_type']); ?></td>
        </tr>

        <tr>
            <th>Fee Period</th>
            <td><?php echo e($receipt['fee_period']); ?></td>
        </tr>

        <tr>
            <th>Total Fee</th>
            <td>
                ৳ <?php echo number_format((float)$receipt['total_fee'], 2); ?>
            </td>
        </tr>

        <tr>
            <th>This Payment</th>
            <td class="paid-amount">
                ৳ <?php echo number_format((float)$receipt['paid_amount'], 2); ?>
            </td>
        </tr>

        <tr>
            <th>Total Paid So Far</th>
            <td>
                ৳ <?php echo number_format((float)$receipt['total_paid'], 2); ?>
            </td>
        </tr>

        <tr>
            <th>Remaining Due</th>
            <td>
                ৳ <?php echo number_format($remaining_due, 2); ?>
            </td>
        </tr>

        <tr>
            <th>Payment Method</th>
            <td><?php echo e($receipt['payment_method']); ?></td>
        </tr>

        <tr>
            <th>Received By</th>
            <td><?php echo e($receipt['received_by']); ?></td>
        </tr>

    </table>

    <?php if (!empty($receipt['note'])): ?>

        <div class="note">
            <strong>Note:</strong>
            <?php echo e($receipt['note']); ?>
        </div>

    <?php endif; ?>

    <div class="signature">

        <div>
            Student / শিক্ষার্থী
        </div>

        <div>
            Authorized Signature / অনুমোদিত স্বাক্ষর
        </div>

    </div>

</div>

<div class="actions">

    <a href="payment_history.php">
        Back to Payment History
    </a>

    <button onclick="window.print()">
        Print Receipt
    </button>

</div>

</body>
</html>
