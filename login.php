
<?php
session_start();

require_once '../../config/db.php';

$error = "";

if (isset($_POST['login'])) {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {

        $error = "Please enter email and password.";

    } else {

        $sql = "SELECT * FROM registration WHERE email = ?";
        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user) {

            if (password_verify($password, $user['password'])) {

                session_regenerate_id(true);

                $_SESSION['student_id'] = $user['studentid'];
                $_SESSION['student_email'] = $user['email'];

                header("Location: ../dashboard/admin_index.php");
                exit;

            } else {

                $error = "Incorrect password!";

            }

        } else {

            $error = "Email not found!";

        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Student Login</title>

    <link rel="stylesheet" href="css/login.css">

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap"
          rel="stylesheet">
</head>

<body>

<div class="login-container">

    <div class="login-card">

        <div class="login-icon">👤</div>

        <h2>Welcome Back!</h2>

        <p class="subtitle">
            Login to your student account
        </p>

        <?php if ($error !== "") { ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php } ?>

        <form action="" method="POST">

            <div class="input-group">
                <label for="email">Email Address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?php echo htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <div class="input-group">
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                >
            </div>

            <div class="form-options">
                <label>
                    <input type="checkbox" name="remember">
                    Remember me
                </label>

                <a href="#">Forgot Password?</a>
            </div>

            <button type="submit" name="login">
                Login Now
            </button>

            <p class="register-link">
                Don't have an account?
                <a href="../registration/register.php">
                    Register Now
                </a>
            </p>

        </form>

    </div>

</div>

</body>
</html>
