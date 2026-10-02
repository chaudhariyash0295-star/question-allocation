<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['student']);
$pageTitle = 'Student Profile';

$userId = $_SESSION['user_id'];
$studentId = $_SESSION['user_detail_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT s.*, u.name, u.email, u.username, u.password,
           sem.semester_name, d.division_name, b.batch_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN semesters sem ON s.semester_id = sem.semester_id
    JOIN divisions d ON s.division_id = d.division_id
    JOIN batches b ON s.batch_id = b.batch_id
    WHERE s.student_id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!password_verify($currentPass, $student['password'])) {
        set_flash('error', 'Current password is incorrect.');
    } elseif (strlen($newPass) < 6) {
        set_flash('error', 'New password must be at least 6 characters long.');
    } elseif ($newPass !== $confirmPass) {
        set_flash('error', 'New password and confirmation do not match.');
    } else {
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        $upStmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $upStmt->execute([$hashed, $userId]);
        set_flash('success', 'Password updated successfully.');
    }
    header("Location: profile.php");
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card text-center p-4">
            <div class="rounded-circle bg-primary bg-opacity-10 text-primary mx-auto d-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1"><?= e($student['name']) ?></h4>
            <p class="text-muted small mb-2"><?= e($student['email']) ?></p>
            <div class="mb-3">
                <span class="badge bg-primary fs-6 px-3 py-1">Roll No. <?= e($student['roll_no']) ?></span>
            </div>

            <div class="mt-4 pt-3 border-top text-start small text-muted">
                <div class="d-flex justify-content-between mb-2">
                    <span>Enrollment No:</span>
                    <strong class="text-dark"><code><?= e($student['enrollment_no']) ?></code></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Semester:</span>
                    <span class="text-dark"><?= e($student['semester_name']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Class Division:</span>
                    <span class="text-dark">Division <?= e($student['division_name']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Laboratory Batch:</span>
                    <strong class="text-primary"><?= e($student['batch_name']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Account Status:</span>
                    <span class="text-success fw-bold">Active Examinee</span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-white">
                <span class="fw-bold text-dark"><i class="fa-solid fa-key me-2 text-warning"></i> Change Password</span>
            </div>
            <div class="card-body">
                <form method="POST" action="profile.php">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" placeholder="••••••••" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">New Password</label>
                        <input type="password" name="new_password" class="form-control" minlength="6" placeholder="Minimum 6 characters" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" minlength="6" placeholder="Re-type new password" required>
                    </div>
                    <button type="submit" class="btn btn-warning">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
