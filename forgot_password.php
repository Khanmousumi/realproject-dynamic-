
<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '../../config/db.php';

$message = '';
$error = '';
$reset_link_for_testing = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        // Check whether the email exists
        $stmt = $conn->prepare(
            "SELECT studentid FROM registration WHERE email = ? LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        $stmt->close();

        if ($user) {

            // Generate a secure reset token
            $token = bin2hex(random_bytes(32));

            // Store only the token hash in database
            $token_hash = hash('sha256', $token);

            // Token expires in 30 minutes
            $expiry = date('Y-m-d H:i:s', time() + 1800);

            $stmt = $conn->prepare(
                "UPDATE registration
                 SET reset_token_hash = ?, reset_token_expiry = ?
                 WHERE studentid = ?"
            );

            $stmt->bind_param(
                "sss",
                $token_hash,
                $expiry,
                $user['studentid']
            );

            $stmt->execute();

               if ($stmt->affected_rows === 1) {
              $reset_link_for_testing =
              "http://localhost/dynamic/admin/login/reset_password.php?token="
               . urlencode($token);
               } else {
              $error = "Token database-এ save হয়নি। আবার চেষ্টা করুন।";
              }

             $stmt->close();
          

            // // Temporary local testing link
            // $reset_link = "http://localhost/dynamic/admin/login/reset_password.php?token="
            //             . urlencode($token);

            // // Local testing only: show the link on the page.
            // // Do not use this approach on a public website.
            // $_SESSION['reset_link_for_testing'] = $reset_link;
            // $reset_link_for_testing = $reset_link;
        }

        // Same message whether the email exists or not
        $message = "If this email is registered, a password reset link has been generated.";

    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
        }

        .forgot-box {
            width: 100%;
            max-width: 420px;
            padding: 30px;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        h2 {
            margin-top: 0;
            color: #1e293b;
        }

        p {
            color: #64748b;
            line-height: 1.6;
        }

        label {
            display: block;
            margin: 18px 0 8px;
            color: #334155;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 16px;
        }

        button {
            width: 100%;
            margin-top: 18px;
            padding: 12px;
            border: 0;
            border-radius: 8px;
            color: white;
            background: #2563eb;
            font-size: 16px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .message {
            color: #166534;
            overflow-wrap: anywhere;
        }

        .error {
            color: #b91c1c;
        }

        a {
            color: #2563eb;
        }
    </style>
</head>
<body>

<div class="forgot-box">
    <h2>Forgot Password?</h2>

    <p>Enter your registered email address to request a password reset.</p>

    <?php if ($message !== ''): ?>
        <p class="message">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <p class="error">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="email">Email Address</label>

        <input
            type="email"
            id="email"
            name="email"
            required
            autocomplete="email"
            placeholder="Enter your registered email"
        >

        <button type="submit">Generate Reset Link</button>
    </form>
    
    <?php if ($reset_link_for_testing !== ''): ?>
    <p class="message">
        Local testing link:
        <a href="<?php
            echo htmlspecialchars(
                $reset_link_for_testing,
                ENT_QUOTES,
                'UTF-8'
            );
        ?>">Reset Password</a>
    </p>
<?php endif; ?>

 

    <p><a href="login.php">Back to Login</a></p>
</div>

</body>
</html>
