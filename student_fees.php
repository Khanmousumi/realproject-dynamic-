
<?php
session_start();

require_once '../../config/db.php';

// Student Login Check
if (!isset($_SESSION['student_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login/login.php");
    exit();
}

// Live Student Role Check
$student_id = $_SESSION['student_id'];

$role_sql = "SELECT role FROM registration WHERE studentid = ? LIMIT 1";
$role_stmt = $conn->prepare($role_sql);
$role_stmt->bind_param("s", $student_id);
$role_stmt->execute();

$role_result = $role_stmt->get_result();
$user = $role_result->fetch_assoc();
$role_stmt->close();

if (!$user || $user['role'] !== 'student') {
    header("Location: admin_index.php");
    exit();
}

// Get Student Fee Records
$sql = "SELECT
            id,
            fee_type,
            fee_period,
            amount,
            paid_amount,
            due_date,
            payment_date,
            payment_method,
            note
        FROM fees
        WHERE studentid = ?
        ORDER BY due_date DESC, id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();

$result = $stmt->get_result();

$fees = [];

$total_fee = 0;
$total_paid = 0;
$total_due = 0;

while ($row = $result->fetch_assoc()) {

    $amount = (float)$row['amount'];
    $paid = (float)$row['paid_amount'];

    $due = max(0, $amount - $paid);

    if ($due <= 0) {
        $status = "Paid";
    } elseif ($paid > 0) {
        $status = "Partial";
    } else {
        $status = "Unpaid";
    }

    $row['due_amount'] = $due;
    $row['status'] = $status;

    $fees[] = $row;

    $total_fee += $amount;
    $total_paid += $paid;
    $total_due += $due;
}

$stmt->close();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Fees | Student Management</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .header h2 {
            color: #1e40af;
            margin-bottom: 8px;
        }

        .header p {
            color: #64748b;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin: 25px 0;
        }

        .summary-card {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .summary-card h3 {
            color: #64748b;
            font-size: 14px;
            margin: 0 0 12px;
        }

        .summary-card p {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }

        .total {
            color: #1d4ed8;
        }

        .paid {
            color: #15803d;
        }

        .due {
            color: #dc2626;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        th {
            background: #eff6ff;
            color: #1e40af;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-paid {
            background: #dcfce7;
            color: #15803d;
        }

        .status-partial {
            background: #fef3c7;
            color: #92400e;
        }

        .status-unpaid {
            background: #fee2e2;
            color: #b91c1c;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }

            .summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <a href="admin_index.php" class="back-btn">
        ← Back to Dashboard
    </a>

    <div class="header">
        <h2>My Fees / আমার ফি</h2>
        <p>নিজের ফি ও পেমেন্টের হিসাব / View Your Fee Details</p>
    </div>

    <div class="summary">

        <div class="summary-card">
            <h3>Total Fee / মোট ফি</h3>
            <p class="total">
                ৳ <?php echo number_format($total_fee, 2); ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Paid Amount / জমা</h3>
            <p class="paid">
                ৳ <?php echo number_format($total_paid, 2); ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Due Amount / বকেয়া</h3>
            <p class="due">
                ৳ <?php echo number_format($total_due, 2); ?>
            </p>
        </div>

    </div>

    <div class="table-container">

        <table>

            <thead>
                <tr>
                    <th>Fee Type</th>
                    <th>Fee Period</th>
                    <th>Total Fee</th>
                    <th>Paid Amount</th>
                    <th>Due Amount</th>
                    <th>Due Date</th>
                    <th>Payment Date</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if (count($fees) > 0): ?>

                <?php foreach ($fees as $fee): ?>

                    <?php
                    $status_class = '';

                    if ($fee['status'] === 'Paid') {
                        $status_class = 'status-paid';
                    } elseif ($fee['status'] === 'Partial') {
                        $status_class = 'status-partial';
                    } else {
                        $status_class = 'status-unpaid';
                    }
                    ?>

                    <tr>

                        <td><?php echo e($fee['fee_type']); ?></td>

                        <td><?php echo e($fee['fee_period']); ?></td>

                        <td>
                            ৳ <?php echo number_format((float)$fee['amount'], 2); ?>
                        </td>

                        <td>
                            ৳ <?php echo number_format((float)$fee['paid_amount'], 2); ?>
                        </td>

                        <td>
                            ৳ <?php echo number_format((float)$fee['due_amount'], 2); ?>
                        </td>

                        <td><?php echo e($fee['due_date']); ?></td>

                        <td>
                            <?php echo e($fee['payment_date'] ?: '-'); ?>
                        </td>

                        <td>
                            <?php echo e($fee['payment_method'] ?: '-'); ?>
                        </td>

                        <td>
                            <span class="status <?php echo $status_class; ?>">
                                <?php echo e($fee['status']); ?>
                            </span>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="9" class="empty">
                        No Fee Records Found.
                        <br>
                        আপনার কোনো Fee Record পাওয়া যায়নি।
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
