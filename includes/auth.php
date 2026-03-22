<?php
// Ensure constants before starting session
require_once __DIR__ . '/../config/constants.php';
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once "../config/db.php";

// LOGIN FUNCTION
function login($email, $password) {
    global $conn;
    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn -> prepare($sql);
    $stmt -> bind_param("s", $email);
    $stmt -> execute();
    $result = $stmt -> get_result();

    if ($result -> num_rows === 1) {
        $user = $result -> fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role'] = $user['role'];
            return true;
        }
    }
    return false;
}

// REGISTER FUNCTION
function registerRenter($fullname, $email, $password, $phone) {
    global $conn;

    //CHECKING IF THE EMAIL EXIST.
    $check = $conn -> prepare("SELECT * FROM users WHERE email = ?");
    $check -> bind_param("s", $email);
    $check -> execute();
    $result = $check -> get_result();

    if ($result -> num_rows > 0) {
        return "Email already exist!";
    }

    //PASSWORD ENCRYPTION
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (full_name, email, password, phone, role) VALUES (?, ?, ?, ?, 'renter')";

    $stmt = $conn -> prepare($sql);
    $stmt -> bind_param("ssss", $fullname, $email, $hashed, $phone);

    if ($stmt -> execute()) {
        return "Successfully registered!";
    }
    else {
        return "Registration failed!";
    }

}

// CHECK LOGIN
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// LOGOUT
function logout() {
    session_destroy();
    header("Location: ../public/login.php");
    exit;
}
?>