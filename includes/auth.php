<?php
/**
 * Authentication and Session Management
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'user_id'   => $_SESSION['user_id'] ?? null,
        'name'      => $_SESSION['user_name'] ?? '',
        'email'     => $_SESSION['user_email'] ?? '',
        'username'  => $_SESSION['user_username'] ?? '',
        'role'      => $_SESSION['user_role'] ?? '',
        'detail_id' => $_SESSION['user_detail_id'] ?? null, // student_id or faculty_id
    ];
}

function require_auth($allowed_roles = []) {
    if (!is_logged_in()) {
        $_SESSION['flash_error'] = "Please log in to continue.";
        header("Location: " . BASE_URL . "login.php");
        exit;
    }

    if (!empty($allowed_roles)) {
        if (is_string($allowed_roles)) {
            $allowed_roles = [$allowed_roles];
        }
        $current_role = $_SESSION['user_role'] ?? '';
        if (!in_array($current_role, $allowed_roles, true)) {
            $_SESSION['flash_error'] = "Access denied: Unauthorized role.";
            // Redirect to appropriate dashboard
            switch ($current_role) {
                case 'admin':
                    header("Location: " . BASE_URL . "admin/dashboard.php");
                    break;
                case 'faculty':
                    header("Location: " . BASE_URL . "faculty/dashboard.php");
                    break;
                case 'student':
                    header("Location: " . BASE_URL . "student/dashboard.php");
                    break;
                default:
                    header("Location: " . BASE_URL . "login.php");
                    break;
            }
            exit;
        }
    }
}

function login_user($user, $pdo) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];

    // Retrieve specific entity ID (faculty_id or student_id)
    if ($user['role'] === 'faculty') {
        $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $row = $stmt->fetch();
        $_SESSION['user_detail_id'] = $row ? $row['faculty_id'] : null;
    } elseif ($user['role'] === 'student') {
        $stmt = $pdo->prepare("SELECT student_id, roll_no, batch_id, semester_id, division_id FROM students WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['user_detail_id'] = $row['student_id'];
            $_SESSION['student_roll_no'] = $row['roll_no'];
            $_SESSION['student_batch_id'] = $row['batch_id'];
            $_SESSION['student_semester_id'] = $row['semester_id'];
            $_SESSION['student_division_id'] = $row['division_id'];
        }
    } else {
        $_SESSION['user_detail_id'] = null;
    }
}

function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
