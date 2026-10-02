<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Admin Profile';

$userId = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$user->execute([$userId]);
$admin = $user->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($name) || empty($email)) {
            set_flash('error', 'Name and email are required.');
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE user_id = ?");
                $stmt->execute([$name, $email, $userId]);
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                set_flash('success', 'Profile updated successfully.');
            } catch (PDOException $e) {
                set_flash('error', 'Email already in use or database error.');
            }
        }
        header("Location: profile.php");
        exit;
    }

    if ($postAction === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $admin['password'])) {
            set_flash('error', 'Current password is incorrect.');
        } elseif (strlen($newPass) < 6) {
            set_flash('error', 'New password must be at least 6 characters long.');
        } elseif ($newPass !== $confirmPass) {
            set_flash('error', 'New password and confirmation do not match.');
        } else {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashed, $userId]);
            set_flash('success', 'Password updated successfully.');
        }
        header("Location: profile.php");
        exit;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card text-center p-4">
            <div class="rounded-circle bg-primary bg-opacity-10 text-primary mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                <i class="fa-solid fa-user-shield"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1"><?= e($admin['name']) ?></h4>
            <p class="text-muted small mb-2"><?= e($admin['email']) ?></p>
            <div>
                <span class="badge bg-danger text-uppercase px-3 py-1">Administrator</span>
            </div>
            <div class="mt-4 pt-3 border-top text-start small text-muted">
                <div class="d-flex justify-content-between mb-2">
                    <span>Username:</span>
                    <strong class="text-dark">@<?= e($admin['username']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Account Created:</span>
                    <span class="text-dark"><?= format_date($admin['created_at']) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Access Level:</span>
                    <span class="text-success fw-bold">Full System Control</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header bg-white">
                <span class="fw-bold text-dark"><i class="fa-solid fa-user-pen me-2 text-primary"></i> Edit Profile Information</span>
            </div>
            <div class="card-body">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Display Name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($admin['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= e($admin['email']) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <span class="fw-bold text-dark"><i class="fa-solid fa-key me-2 text-warning"></i> Change Password</span>
            </div>
            <div class="card-body">
                <form method="POST" action="profile.php">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                    </div>
                    <button type="submit" class="btn btn-warning">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
