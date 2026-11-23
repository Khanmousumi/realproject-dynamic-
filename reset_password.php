
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../config/db.php';

$message = '';
$error = '';

// Get token from URL or POST
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$token_hash = '';
$user = null;

// Step 1: Validate reset token
if (
    $token === '' ||
    !preg_match('/^[a-f0-9]{64}$/', $token)
) {
    $error = "Invalid reset link. Please request a new one.";

} else {

    $token_hash = hash('sha256', $token);

    $stmt = $conn->prepare(
        "SELECT studentid
         FROM registration
         WHERE reset_token_hash = ?
         AND reset_token_expiry > NOW()
         LIMIT 1"
    );

    $stmt->bind_param("s", $token_hash);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    if (!$user) {
        $error = "Invalid or expired reset link. Please request a new one.";
    }
}

// Step 2: Process new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 9) {

        $error = "Password must contain at least 9 characters.";

    } elseif ($password !== $confirm_password) {

        $error = "Passwords do not match.";

    } else {

        // Hash the new password
        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        // Update password and remove reset token
        $stmt = $conn->prepare(
            "UPDATE registration
             SET password = ?,
                 reset_token_hash = NULL,
                 reset_token_expiry = NULL
             WHERE studentid = ?
             AND reset_token_hash = ?
             AND reset_token_expiry > NOW()"
        );

        $stmt->bind_param(
            "sss",
            $hashed_password,
            $user['studentid'],
            $token_hash
        );

        $stmt->execute();

        if ($stmt->affected_rows === 1) {

            $message = "Your password has been reset successfully. You can now log in.";

            // Prevent reuse of the token
            $user = null;

        } else {

            $error = "Unable to reset your password. Please request a new link.";

            $user = null;
        }

        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reset Password</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f0f4ff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .reset-container {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.10);
        }

        h2 {
            text-align: center;
            color: #1d4ed8;
            margin-bottom: 12px;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            background: #dcfce7;
            color: #166534;
            margin-bottom: 18px;
            text-align: center;
        }

        .error {
            padding: 12px;
            border-radius: 6px;
            background: #fee2e2;
            color: #991b1b;
            margin-bottom: 18px;
            text-align: center;
        }

        .login-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }

        .login-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .reset-container {
                padding: 25px 20px;
            }
        }
    </style>

</head>

<body>

    <div class="reset-container">

        <h2>Reset Password</h2>

        <p class="subtitle">
            Enter your new password below.
        </p>

        <?php if ($message !== ''): ?>

            <div class="message">
                <?php echo htmlspecialchars($message); ?>
            </div>

            <a href="login.php" class="login-link">
                Go to Login
            </a>

        <?php elseif ($user): ?>

            <?php if ($error !== ''): ?>
                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="token"
                    value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
                >

                <label>New Password</label>

                <input
                    type="password"
                    name="password"
                    placeholder="Enter new password"
                    minlength="9"
                    required
                >

                <label>Confirm New Password</label>

                <input
                    type="password"
                    name="confirm_password"
                    placeholder="Confirm new password"
                    minlength="9"
                    required
                >

                <button type="submit">
                    Reset Password
                </button>

            </form>

        <?php else: ?>

            <?php if ($error !== ''): ?>
                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <a href="forgot_password.php" class="login-link">
                Request a New Reset Link
            </a>

        <?php endif; ?>

    </div>

</body>
</html>

