<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['student']);
$pageTitle = 'Student Profile';

$userId = $_SESSION['user_id'];
$studentId = $_SESSION['user_detail_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT s.*, u.name, u.email, u.username,
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

if (!$student) {
    header('Location: ../logout.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
        <div class="card border border-light-subtle shadow-sm rounded-4 p-4 p-md-5 bg-white text-center">
            <!-- Avatar Icon -->
            <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 86px; height: 86px; background-color: #e8f0fe;">
                <i class="fa-solid fa-user-graduate" style="font-size: 2.2rem; color: #1a73e8;"></i>
            </div>

            <!-- Student Name & Email -->
            <h3 class="fw-bold text-dark mb-1" style="font-size: 1.6rem; letter-spacing: -0.01em;"><?= e($student['name']) ?></h3>
            <p class="text-secondary mb-3" style="font-size: 0.98rem;"><?= e($student['email']) ?></p>

            <!-- Roll No Badge -->
            <div class="mb-4">
                <span class="badge px-3 py-2 text-white" style="background-color: #0d6efd; font-size: 1rem; font-weight: 700; border-radius: 6px; letter-spacing: 0.2px;">Roll No. <?= e($student['roll_no']) ?></span>
            </div>

            <!-- Separator Line -->
            <div class="border-top mb-4" style="border-color: #e5e7eb;"></div>

            <!-- Profile Details List -->
            <div class="text-start" style="font-size: 0.98rem;">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary" style="color: #5f6368 !important;">Enrollment No:</span>
                    <span class="fw-semibold" style="color: #d63384; font-size: 0.98rem;"><?= e($student['enrollment_no']) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary" style="color: #5f6368 !important;">Semester:</span>
                    <span class="text-dark" style="color: #202124 !important;"><?= e($student['semester_name']) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary" style="color: #5f6368 !important;">Class Division:</span>
                    <span class="text-dark" style="color: #202124 !important;">Division <?= e($student['division_name']) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-secondary" style="color: #5f6368 !important;">Laboratory Batch:</span>
                    <strong class="fw-bold" style="color: #0d6efd;"><?= e($student['batch_name']) ?></strong>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-secondary" style="color: #5f6368 !important;">Account Status:</span>
                    <span class="fw-bold" style="color: #198754;">Active Examinee</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
