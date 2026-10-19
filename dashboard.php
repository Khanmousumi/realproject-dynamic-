
<?php
require_once '../../config/db.php';


$search = trim($_GET['search'] ?? '');

if ($search !== '') {
    $sql = "SELECT studentid, fullname, email, department
            FROM registration
            WHERE studentid LIKE ? OR fullname LIKE ?";

    $stmt = mysqli_prepare($conn, $sql);

    $keyword = "%{$search}%";

    mysqli_stmt_bind_param($stmt, "ss", $keyword, $keyword);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
} else {
    $sql = "SELECT studentid, fullname, email, department
            FROM registration";

    $result = mysqli_query($conn, $sql);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students List</title>

    <style>
    .students-section {
        width: 100%;
    }

    .students-section h2 {
        margin-bottom: 20px;
        color: #1e293b;
    }

    .students-table {
        width: 100%;
        border-collapse: collapse;
        background: #ffffff;
        border-radius: 8px;
        overflow: hidden;
    }

    .students-table th,
    .students-table td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }

    .students-table th {
        background: #2563eb;
        color: #ffffff;
    }

    .students-table tr:hover {
        background: #f1f5f9;
    }
    .search-form {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-form input {
    width: 300px;
    max-width: 100%;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
}

.search-form button {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    background: #2563eb;
    color: white;
    cursor: pointer;
}

.search-form button:hover {
    background: #1d4ed8;
}
</style>

</head>
<body>

 <div class="students-section">

 
<form method="GET" action="dashboard.php" class="search-form">
    <input
        type="text"
        name="search"
        placeholder="Search by Student ID or Name"
        value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
    >

    <button type="submit">Search</button>
</form>


    <h2>All Students</h2>

    <table class="students-table">
        <tr>
            <th>Student ID</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Department</th>
        </tr>

        <?php
        if ($result && mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
        ?>

        <tr>
            <td><?php echo htmlspecialchars($row['studentid']); ?></td>
            <td><?php echo htmlspecialchars($row['fullname']); ?></td>
            <td><?php echo htmlspecialchars($row['email']); ?></td>
            <td><?php echo htmlspecialchars($row['department']); ?></td>
        </tr>

        <?php
            }
        } else {
        ?>
            <tr>
                <td colspan="4">No students found</td>
            </tr>
        <?php
        }
        ?>
    </table>

</div>   

     

</body>
</html>
