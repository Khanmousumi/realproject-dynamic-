
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
        f.due_date,
        f.payment_date,
        f.payment_method,
        f.note
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

$sql .= " ORDER BY f.id DESC";

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

// Summary
$summary_query = $conn->query("
    SELECT
        COUNT(*) AS total_fees,
        COALESCE(SUM(amount), 0) AS total_amount,
        COALESCE(SUM(paid_amount), 0) AS total_paid,
        COALESCE(SUM(amount - paid_amount), 0) AS total_due
    FROM fees
");

$summary = $summary_query->fetch_assoc();

$total_fees = (int)$summary['total_fees'];
$total_amount = (float)$summary['total_amount'];
$total_paid = (float)$summary['total_paid'];
$total_due = (float)$summary['total_due'];

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

<title>Manage Fees | Student Management System</title>

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
    max-width: 1400px;
    margin: 15px auto;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

h2 {
    color: #1e40af;
    margin: 0 0 8px;
}

.subtitle {
    color: #64748b;
    margin: 0;
}

.btn {
    display: inline-block;
    background: #2563eb;
    color: white;
    padding: 11px 17px;
    text-decoration: none;
    border-radius: 7px;
    font-weight: bold;
}

.btn:hover {
    background: #1d4ed8;
}

.summary {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

.card p {
    margin: 0 0 10px;
    color: #64748b;
    font-size: 14px;
}

.card h3 {
    margin: 0;
    color: #1e40af;
    font-size: 23px;
}

.search-box {
    background: white;
    padding: 18px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-box input {
    flex: 1;
    min-width: 200px;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font-size: 15px;
}

.search-btn {
    background: #2563eb;
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: bold;
}

.reset-btn {
    background: #64748b;
    color: white;
    padding: 12px 18px;
    border-radius: 7px;
    text-decoration: none;
}

.table-box {
    background: white;
    border-radius: 10px;
    padding: 18px;
    overflow-x: auto;
    box-shadow: 0 3px 12px rgba(0,0,0,0.05);
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

th, td {
    padding: 13px 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
    white-space: nowrap;
    font-size: 14px;
}

th {
    background: #eff6ff;
    color: #1e40af;
}

tr:hover {
    background: #f8fafc;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.paid {
    background: #dcfce7;
    color: #166534;
}

.partial {
    background: #fef3c7;
    color: #92400e;
}

.unpaid {
    background: #fee2e2;
    color: #991b1b;
}

.action-link {
    text-decoration: none;
    font-weight: bold;
    margin-right: 9px;
}

.edit {
    color: #2563eb;
}

.delete {
    color: #dc2626;
}

.empty {
    text-align: center;
    padding: 30px;
    color: #64748b;
}

@media(max-width: 900px) {
    .summary {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 500px) {
    body {
        padding: 12px;
    }

    .summary {
        grid-template-columns: 1fr;
    }

    .header .btn {
        width: 100%;
        text-align: center;
    }
}

</style>

</head>

<body>

<div class="container">

    <div class="header">

        <div>
            <h2>💰 Manage Student Fees</h2>

            <p class="subtitle">
                Manage student fees and payment information /
                শিক্ষার্থীদের ফি ও পেমেন্ট পরিচালনা করুন।
            </p>
        </div>

        <a href="add_fee.php" class="btn">
            ➕ Add New Fee
        </a>

    </div>

    <!-- Summary Cards -->

    <div class="summary">

        <div class="card">
            <p>Total Fee Records / মোট ফি</p>
            <h3><?php echo $total_fees; ?></h3>
        </div>

        <div class="card">
            <p>Total Amount / মোট নির্ধারিত টাকা</p>
            <h3>৳<?php echo number_format($total_amount, 2); ?></h3>
        </div>

        <div class="card">
            <p>Total Paid / মোট জমা</p>
            <h3>৳<?php echo number_format($total_paid, 2); ?></h3>
        </div>

        <div class="card">
            <p>Total Due / মোট বাকি</p>
            <h3>৳<?php echo number_format($total_due, 2); ?></h3>
        </div>

    </div>

    <!-- Search -->

    <form method="GET" class="search-box">

        <input
            type="text"
            name="search"
            placeholder="Search by Student ID, Name or Fee Type..."
            value="<?php echo e($search); ?>"
        >

        <button type="submit" class="search-btn">
            🔍 Search
        </button>

        <a href="manage_fees.php" class="reset-btn">
            Reset
        </a>

    </form>

    <!-- Fee Table -->

    <div class="table-box">

        <table>

            <thead>

                <tr>
                    <th>SL</th>
                    <th>Student ID</th>
                    <th>Student Name</th>
                    <th>Fee Type</th>
                    <th>Period</th>
                    <th>Total Amount</th>
                    <th>Paid Amount</th>
                    <th>Due Amount</th>
                    <th>Due Date</th>
                    <th>Payment Status</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            <?php if ($result->num_rows > 0): ?>

                <?php $sl = 1; ?>

                <?php while ($row = $result->fetch_assoc()): ?>

                    <?php
                        $amount = (float)$row['amount'];
                        $paid = (float)$row['paid_amount'];
                        $due = max(0, $amount - $paid);

                        if ($due <= 0) {
                            $status = "Paid";
                            $status_class = "paid";
                        } elseif ($paid > 0) {
                            $status = "Partial";
                            $status_class = "partial";
                        } else {
                            $status = "Unpaid";
                            $status_class = "unpaid";
                        }
                    ?>

                    <tr>

                        <td><?php echo $sl++; ?></td>

                        <td><?php echo e($row['studentid']); ?></td>

                        <td><?php echo e($row['fullname']); ?></td>

                        <td><?php echo e($row['fee_type']); ?></td>

                        <td><?php echo e($row['fee_period']); ?></td>

                        <td>
                            ৳<?php echo number_format($amount, 2); ?>
                        </td>

                        <td>
                            ৳<?php echo number_format($paid, 2); ?>
                        </td>

                        <td>
                            ৳<?php echo number_format($due, 2); ?>
                        </td>

                        <td><?php echo e($row['due_date']); ?></td>

                        <td>
                            <span class="status <?php echo $status_class; ?>">
                                <?php echo $status; ?>
                            </span>
                        </td>
                        <td>
    <a href="edit_fee.php?id=<?php echo (int)$row['id']; ?>" class="action-link edit">
        ✏️ Edit
    </a>

    <a href="delete_fee.php?id=<?php echo (int)$row['id']; ?>" class="action-link delete">
        🗑️ Delete
    </a>

    <a href="update_payment.php?id=<?php echo (int)$row['id']; ?>"
       class="action-link"
       style="color:#16a34a;">
        💳 Payment
    </a>
</td>


                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="11" class="empty">
                        No Fee Records Found / কোনো ফি রেকর্ড পাওয়া যায়নি।
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

    <br>

    <a href="admin_index.php" class="btn">
        ← Back to Dashboard
    </a>

</div>

</body>
</html>
