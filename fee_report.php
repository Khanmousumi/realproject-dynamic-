
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

// Overall Fee Summary
$summary_sql = "
    SELECT
        COUNT(*) AS total_records,
        COALESCE(SUM(amount), 0) AS total_fee,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(amount - paid_amount), 0) AS total_due
    FROM fees
";

$summary_result = $conn->query($summary_sql);
$summary = $summary_result->fetch_assoc();

$total_records = (int)$summary['total_records'];
$total_fee = (float)$summary['total_fee'];
$total_paid = (float)$summary['total_paid'];
$total_due = (float)$summary['total_due'];

// Search
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT
        f.id,
        f.studentid,
        r.fullname,
        f.fee_type,
        f.fee_period,
        f.amount,
        f.paid_amount,
        (f.amount - f.paid_amount) AS due_amount,
        f.due_date
    FROM fees f
    INNER JOIN registration r
        ON f.studentid = r.studentid
";

if ($search !== '') {
    $sql .= "
        WHERE f.studentid LIKE ?
        OR r.fullname LIKE ?
        OR f.fee_type LIKE ?
        OR f.fee_period LIKE ?
    ";
}

$sql .= " ORDER BY f.due_date DESC, f.id DESC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $search_value = "%{$search}%";

    $stmt->bind_param(
        "ssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );
}

$stmt->execute();
$result = $stmt->get_result();

$fee_records = [];

while ($row = $result->fetch_assoc()) {
    $fee_records[] = $row;
}

$stmt->close();

// Status Counts
$paid_count = 0;
$partial_count = 0;
$unpaid_count = 0;

foreach ($fee_records as $row) {
    if ((float)$row['due_amount'] <= 0) {
        $paid_count++;
    } elseif ((float)$row['paid_amount'] > 0) {
        $partial_count++;
    } else {
        $unpaid_count++;
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Fee Report | Student Management</title>

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
            max-width: 1250px;
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
            grid-template-columns: repeat(4, 1fr);
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
            font-size: 22px;
            font-weight: bold;
            margin: 0;
        }

        .blue { color: #1d4ed8; }
        .green { color: #15803d; }
        .red { color: #dc2626; }
        .purple { color: #7c3aed; }

        .status-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .status-box {
            background: white;
            padding: 13px 18px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            font-size: 14px;
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
            min-width: 1000px;
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

        .paid-amount {
            color: #15803d;
            font-weight: bold;
        }

        .due-amount {
            color: #dc2626;
            font-weight: bold;
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

        @media (max-width: 900px) {
            .summary {
                grid-template-columns: repeat(2, 1fr);
            }
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

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .back-btn,
            .search-box {
                display: none;
            }

            .summary-card,
            .table-container {
                box-shadow: none;
                border: 1px solid #ddd;
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
        <h2>Fee Report / ফি রিপোর্ট</h2>
        <p>Overall Fee Collection and Due Report</p>
    </div>

    <!-- Summary Cards -->

    <div class="summary">

        <div class="summary-card">
            <h3>Total Fee Records / মোট রেকর্ড</h3>
            <p class="purple">
                <?php echo $total_records; ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Total Fee / মোট ফি</h3>
            <p class="blue">
                ৳ <?php echo number_format($total_fee, 2); ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Total Collected / মোট আদায়</h3>
            <p class="green">
                ৳ <?php echo number_format($total_paid, 2); ?>
            </p>
        </div>

        <div class="summary-card">
            <h3>Total Due / মোট বকেয়া</h3>
            <p class="red">
                ৳ <?php echo number_format($total_due, 2); ?>
            </p>
        </div>

    </div>

    <!-- Payment Status Summary -->

    <div class="status-summary">

        <div class="status-box">
            <strong>Paid:</strong> <?php echo $paid_count; ?>
        </div>

        <div class="status-box">
            <strong>Partial:</strong> <?php echo $partial_count; ?>
        </div>

        <div class="status-box">
            <strong>Unpaid:</strong> <?php echo $unpaid_count; ?>
        </div>

    </div>

    <!-- Search -->

    <div class="search-box">

        <form method="GET">

            <input
                type="text"
                name="search"
                placeholder="Search Student ID, Name, Fee Type..."
                value="<?php echo e($search); ?>"
            >

            <button type="submit">Search</button>

            <a href="fee_report.php" class="reset-btn">
                Reset
            </a>

            <button type="button" onclick="window.print()">
                Print Report
            </button>

        </form>

    </div>

    <!-- Fee Report Table -->

    <div class="table-container">

        <table>

            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Fee Type</th>
                    <th>Fee Period</th>
                    <th>Total Fee</th>
                    <th>Paid Amount</th>
                    <th>Due Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if (count($fee_records) > 0): ?>

                <?php foreach ($fee_records as $row): ?>

                    <?php
                    if ((float)$row['due_amount'] <= 0) {
                        $status = 'Paid';
                        $status_class = 'status-paid';
                    } elseif ((float)$row['paid_amount'] > 0) {
                        $status = 'Partial';
                        $status_class = 'status-partial';
                    } else {
                        $status = 'Unpaid';
                        $status_class = 'status-unpaid';
                    }
                    ?>

                    <tr>

                        <td><?php echo e($row['studentid']); ?></td>

                        <td><?php echo e($row['fullname']); ?></td>

                        <td><?php echo e($row['fee_type']); ?></td>

                        <td><?php echo e($row['fee_period']); ?></td>

                        <td>
                            ৳ <?php echo number_format((float)$row['amount'], 2); ?>
                        </td>

                        <td class="paid-amount">
                            ৳ <?php echo number_format((float)$row['paid_amount'], 2); ?>
                        </td>

                        <td class="due-amount">
                            ৳ <?php echo number_format((float)$row['due_amount'], 2); ?>
                        </td>

                        <td><?php echo e($row['due_date']); ?></td>

                        <td>
                            <span class="status <?php echo $status_class; ?>">
                                <?php echo e($status); ?>
                            </span>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>
                    <td colspan="9" class="empty">
                        No Fee Records Found.
                        <br>
                        কোনো Fee Record পাওয়া যায়নি।
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
