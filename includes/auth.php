<?php
include 'config.php';

function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

function login($email, $password) {
    if ($email === DEFAULT_EMAIL && $password === DEFAULT_PASSWORD) {
        $_SESSION['loggedin'] = true;
        $_SESSION['email'] = $email;
        return true;
    }
    return false;
}

function logout() {
    $_SESSION = array();
    session_destroy();
    header('Location: login');
    exit;
}

// Auto redirect to login if not logged in
if (!isLoggedIn() && basename($_SERVER['PHP_SELF']) !== 'index.php') {
    header('Location: login');
    exit;
}

// Auto logout check
if (isset($_GET['logout'])) {
    logout();
}
?>