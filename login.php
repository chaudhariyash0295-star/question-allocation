<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect
if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') header("Location: " . BASE_URL . "admin/dashboard.php");
    elseif ($role === 'faculty') header("Location: " . BASE_URL . "faculty/dashboard.php");
    elseif ($role === 'student') header("Location: " . BASE_URL . "student/dashboard.php");
    exit;
}

$error = '';
$selectedRole = $_POST['role'] ?? 'admin';
$loginInput = trim($_POST['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if (empty($loginInput) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        // Query user by username or email
        $stmt = $pdo->prepare("
            SELECT * FROM users 
            WHERE (username = ? OR email = ?) 
            LIMIT 1
        ");
        $stmt->execute([$loginInput, $loginInput]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact the administrator.';
            } elseif (!empty($role) && $user['role'] !== $role) {
                $error = "Selected role does not match this account. This account is registered as " . strtoupper($user['role']) . ".";
            } else {
                // Successful login
                login_user($user, $pdo);

                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: " . BASE_URL . "admin/dashboard.php");
                        break;
                    case 'faculty':
                        header("Location: " . BASE_URL . "faculty/dashboard.php");
                        break;
                    case 'student':
                        header("Location: " . BASE_URL . "student/dashboard.php");
                        break;
                }
                exit;
            }
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Smart Question Allocation System</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .login-card {
            width: 100%;
            max-width: 480px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .login-header {
            background-color: #0f2744;
            color: #ffffff;
            padding: 2rem;
            text-align: center;
        }
        .role-btn-group .btn-check:checked + .btn {
            background-color: #2563eb;
            color: white;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="fs-1 text-info mb-2">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <h4 class="fw-bold mb-1">SMART QUESTION ALLOCATION</h4>
        <p class="text-light text-opacity-75 small mb-0">College Practical Examination Portal</p>
    </div>

    <div class="p-4 p-md-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="fa-solid fa-circle-exclamation me-2 fs-5"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['flash_error'])): ?>
            <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i>
                <div><?= e($_SESSION['flash_error']) ?></div>
            </div>
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <!-- Role Selection -->
            <label class="form-label fw-semibold text-secondary small text-uppercase">Select Role</label>
            <div class="btn-group w-100 mb-4 role-btn-group" role="group">
                <input type="radio" class="btn-check" name="role" id="roleAdmin" value="admin" <?= $selectedRole === 'admin' ? 'checked' : '' ?>>
                <label class="btn btn-outline-primary py-2" for="roleAdmin">
                    <i class="fa-solid fa-user-shield d-block mb-1"></i> Admin
                </label>

                <input type="radio" class="btn-check" name="role" id="roleFaculty" value="faculty" <?= $selectedRole === 'faculty' ? 'checked' : '' ?>>
                <label class="btn btn-outline-primary py-2" for="roleFaculty">
                    <i class="fa-solid fa-chalkboard-user d-block mb-1"></i> Faculty
                </label>

                <input type="radio" class="btn-check" name="role" id="roleStudent" value="student" <?= $selectedRole === 'student' ? 'checked' : '' ?>>
                <label class="btn btn-outline-primary py-2" for="roleStudent">
                    <i class="fa-solid fa-user-graduate d-block mb-1"></i> Student
                </label>
            </div>

            <!-- Username/Email -->
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold small">Username or Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-user"></i></span>
                    <input type="text" class="form-control py-2" id="username" name="username" value="<?= e($loginInput) ?>" placeholder="e.g. admin@example.com" required autofocus>
                </div>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label for="password" class="form-label fw-semibold small">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control py-2" id="password" name="password" placeholder="••••••••" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                        <i class="fa-regular fa-eye"></i>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100 py-2 fs-6 fw-semibold shadow-sm">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Sign In
            </button>
        </form>

        <!-- Quick Demo Fill Buttons for Testing -->
        <div class="mt-4 pt-4 border-top">
            <div class="text-center text-muted small fw-semibold mb-2 text-uppercase" style="letter-spacing: 0.05em;">
                Quick Demo Login Helper
            </div>
            <div class="d-flex justify-content-between gap-2">
                <button type="button" class="btn btn-sm btn-outline-danger flex-fill" onclick="quickFill('admin@example.com', 'Admin@123', 'admin')">
                    Demo Admin
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary flex-fill" onclick="quickFill('faculty@example.com', 'Faculty@123', 'faculty')">
                    Demo Faculty
                </button>
                <button type="button" class="btn btn-sm btn-outline-success flex-fill" onclick="quickFill('student@example.com', 'Student@123', 'student')">
                    Demo Student
                </button>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="<?= BASE_URL ?>" class="text-muted small text-decoration-none">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Homepage
            </a>
        </div>
    </div>
</div>

<script>
function quickFill(user, pass, role) {
    document.getElementById('username').value = user;
    document.getElementById('password').value = pass;
    if (role === 'admin') document.getElementById('roleAdmin').checked = true;
    if (role === 'faculty') document.getElementById('roleFaculty').checked = true;
    if (role === 'student') document.getElementById('roleStudent').checked = true;
}

document.getElementById('togglePasswordBtn').addEventListener('click', function() {
    const pwdInput = document.getElementById('password');
    const icon = this.querySelector('i');
    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        pwdInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
});
</script>

</body>
</html>
