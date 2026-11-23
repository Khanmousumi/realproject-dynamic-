
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

// Search
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        fp.id AS payment_id,
        fp.amount,
        fp.payment_date,
        fp.payment_method,
        fp.note,
        fp.received_by,
        f.id AS fee_id,
        f.fee_type,
        f.fee_period,
        f.amount AS total_fee,
        r.studentid,
        r.fullname
    FROM fee_payments fp
    INNER JOIN fees f ON fp.fee_id = f.id
    INNER JOIN registration r ON f.studentid = r.studentid
";

if ($search !== '') {
    $sql .= "
        WHERE r.studentid LIKE ?
        OR r.fullname LIKE ?
        OR f.fee_type LIKE ?
        OR f.fee_period LIKE ?
        OR fp.payment_method LIKE ?
    ";
}

$sql .= " ORDER BY fp.payment_date DESC, fp.id DESC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $search_value = "%{$search}%";
    $stmt->bind_param(
        "sssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );
}

$stmt->execute();
$result = $stmt->get_result();

$total_collected = 0;
$total_transactions = 0;

$payments = [];

while ($row = $result->fetch_assoc()) {
    $payments[] = $row;
    $total_collected += (float)$row['amount'];
    $total_transactions++;
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

    <title>Payment History | Student Management</title>

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

        .header {
            margin-bottom: 25px;
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
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .summary-card h3 {
            margin: 0 0 10px;
            color: #64748b;
            font-size: 15px;
        }

        .summary-card p {
            margin: 0;
            font-size: 25px;
            font-weight: bold;
            color: #1d4ed8;
        }

        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .search-box form {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 15px;
        }

        button, .reset-btn {
            padding: 12px 18px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .reset-btn {
            background: #64748b;
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
            border-collapse: collapse;
            min-width: 1000px;
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

        .amount {
            color: #15803d;
            font-weight: bold;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #64748b;
        }

        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        @media (max-width: 600px) {
            body {
                padding: 12px;
            }

            .summary {
                grid-template-columns: 1fr;
            }

            .search-box form {
                flex-direction: column;
            }

            .search-box button,
            .reset-btn {
                text-align: center;
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
        <h2>Payment History</h2>
        <p>পেমেন্টের সম্পূর্ণ হিসাব / All Payment Transactions</p>
    </div>

    <div class="summary">

        <div class="summary-card">
            <h3>Total Transactions / মোট পেমেন্ট</h3>
            <p><?php echo $total_transactions; ?></p>
        </div>

        <div class="summary-card">
            <h3>Total Collected / মোট জমা</h3>
            <p>৳ <?php echo number_format($total_collected, 2); ?></p>
        </div>

    </div>

    <div class="search-box">

        <form method="GET">

            <input
                type="text"
                name="search"
                placeholder="Search Student ID, Name, Fee Type..."
                value="<?php echo e($search); ?>"
            >

            <button type="submit">Search</button>

            <a href="payment_history.php" class="reset-btn">
                Reset
            </a>

        </form>

    </div>

    <div class="table-container">

        <table>

            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Fee Type</th>
                    <th>Fee Period</th>
                    <th>Paid Amount</th>
                    <th>Payment Date</th>
                    <th>Method</th>
                    <th>Received By</th>
                    <th>Note</th>
                    <th>Receipt</th>
                </tr>
            </thead>

<tbody>

    <?php if (count($payments) > 0): ?>

        <?php foreach ($payments as $row): ?>

            <tr>
                <td><?php echo (int)$row['payment_id']; ?></td>

                <td><?php echo e($row['studentid']); ?></td>

                <td><?php echo e($row['fullname']); ?></td>

                <td><?php echo e($row['fee_type']); ?></td>

                <td><?php echo e($row['fee_period']); ?></td>

                <td class="amount">
                    ৳ <?php echo number_format((float)$row['amount'], 2); ?>
                </td>

                <td>
                    <?php echo e($row['payment_date']); ?>
                </td>

                <td>
                    <?php echo e($row['payment_method']); ?>
                </td>

                <td>
                    <?php echo e($row['received_by']); ?>
                </td>

                <td>
                    <?php echo e($row['note'] ?: '-'); ?>
                </td>

                <!-- Print Receipt Button -->
                <td>
                    <a href="print_fee_receipt.php?id=<?php echo (int)$row['payment_id']; ?>"
                       target="_blank"
                       style="color:#2563eb; font-weight:bold; text-decoration:none;">
                        🖨️ Print Receipt
                    </a>
                </td>

            </tr>

        <?php endforeach; ?>

    <?php else: ?>

        <tr>
            <td colspan="11" class="empty">
                No Payment Records Found.
                <br>
                কোনো পেমেন্টের রেকর্ড পাওয়া যায়নি।
            </td>
        </tr>

    <?php endif; ?>

</tbody>


        </table>

    </div>

</div>

</body>
</html>
