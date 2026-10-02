<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Admin Dashboard';

// Metrics Counts
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
$totalFaculty = $pdo->query("SELECT COUNT(*) FROM faculty WHERE status = 'active'")->fetchColumn();
$totalSubjects = $pdo->query("SELECT COUNT(*) FROM subjects WHERE status = 'active'")->fetchColumn();
$totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions WHERE status = 'active'")->fetchColumn();
$activeExams = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'running'")->fetchColumn();
$completedExams = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'completed'")->fetchColumn();

// Recent Exams
$recentExams = $pdo->query("
    SELECT e.*, s.subject_name, s.subject_code, b.batch_name, sem.semester_name, u.name as faculty_name
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    JOIN batches b ON e.batch_id = b.batch_id
    JOIN semesters sem ON e.semester_id = sem.semester_id
    JOIN faculty f ON e.faculty_id = f.faculty_id
    JOIN users u ON f.user_id = u.user_id
    ORDER BY e.created_at DESC
    LIMIT 5
")->fetchAll();

// Recent Allocations
$recentAllocations = $pdo->query("
    SELECT qa.*, e.exam_name, s.roll_no, u.name as student_name, q.question_number, SUBSTRING(q.question_text, 1, 80) as question_snippet
    FROM question_allocations qa
    JOIN exams e ON qa.exam_id = e.exam_id
    JOIN students s ON qa.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN questions q ON qa.question_id = q.question_id
    ORDER BY qa.allocated_at DESC
    LIMIT 8
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Administrative Control Center</h3>
        <p class="text-muted small mb-0">Overview of practical examination infrastructure, questions bank, and allocation sessions.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>admin/exams.php" class="btn btn-outline-primary btn-sm">
            <i class="fa-solid fa-laptop-code me-1"></i> Manage Exams
        </a>
        <a href="<?= BASE_URL ?>admin/questions.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i> Add Questions
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card bg-white border-start border-primary border-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Students</div>
                <h3 class="fw-bold text-dark mb-0"><?= number_format($totalStudents) ?></h3>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card bg-white border-start border-info border-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Faculty</div>
                <h3 class="fw-bold text-dark mb-0"><?= number_format($totalFaculty) ?></h3>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card bg-white border-start border-success border-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Subjects</div>
                <h3 class="fw-bold text-dark mb-0"><?= number_format($totalSubjects) ?></h3>
            </div>
            <div class="stat-icon bg-success bg-opacity-10 text-success">
                <i class="fa-solid fa-book"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card bg-white border-start border-purple border-4" style="border-left-color: #8b5cf6 !important;">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Questions</div>
                <h3 class="fw-bold text-dark mb-0"><?= number_format($totalQuestions) ?></h3>
            </div>
            <div class="stat-icon bg-purple bg-opacity-10 text-primary" style="color: #8b5cf6 !important;">
                <i class="fa-solid fa-file-circle-question"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card bg-white border-start border-warning border-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Running</div>
                <h3 class="fw-bold text-warning mb-0"><?= number_format($activeExams) ?></h3>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="fa-solid fa-bolt"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="card stat-card bg-white border-start border-secondary border-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Completed</div>
                <h3 class="fw-bold text-secondary mb-0"><?= number_format($completedExams) ?></h3>
            </div>
            <div class="stat-icon bg-secondary bg-opacity-10 text-secondary">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Practical Examinations -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fa-solid fa-calendar-check me-2 text-primary"></i> Practical Examination Sessions</span>
                <a href="<?= BASE_URL ?>admin/exams.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Exam / Subject</th>
                                <th>Batch / Sem</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentExams)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No examination sessions created yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentExams as $ex): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= e($ex['exam_name']) ?></div>
                                            <small class="text-muted"><?= e($ex['subject_code']) ?> - <?= e($ex['subject_name']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?= e($ex['semester_name']) ?></span>
                                            <span class="badge bg-light text-dark border"><?= e($ex['batch_name']) ?></span>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= format_date($ex['exam_date']) ?></div>
                                            <small class="text-muted"><?= format_time($ex['start_time']) ?> - <?= format_time($ex['end_time']) ?></small>
                                        </td>
                                        <td><?= status_badge($ex['status']) ?></td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>admin/allocations.php?exam_id=<?= $ex['exam_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Allocations">
                                                <i class="fa-solid fa-list-check"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Question Allocations Feed -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fa-solid fa-ticket me-2 text-success"></i> Recent Question Allocations</span>
                <a href="<?= BASE_URL ?>admin/allocations.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Roll No & Student</th>
                                <th>Allocated Question</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentAllocations)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-4">No questions allocated yet. Start an exam session to generate allocations.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentAllocations as $alloc): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-primary">Roll #<?= e($alloc['roll_no']) ?></div>
                                            <small class="text-dark"><?= e($alloc['student_name']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary me-1">Q#<?= e($alloc['question_number']) ?></span>
                                            <span class="small text-muted" title="<?= e($alloc['question_snippet']) ?>">
                                                <?= e(mb_strimwidth($alloc['question_snippet'], 0, 35, '...')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= date('h:i A', strtotime($alloc['allocated_at'])) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
