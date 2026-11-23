
<?php

// ========================================
// 1. START SESSION
// ========================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ========================================
// 2. CLEAR ALL SESSION DATA
// ========================================

$_SESSION = [];


// ========================================
// 3. DELETE SESSION COOKIE
// ========================================

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}


// ========================================
// 4. DESTROY SESSION
// ========================================

session_destroy();


// ========================================
// 5. REDIRECT TO LOGIN
// ========================================

header("Location: ../login/login.php");
exit;

?>
