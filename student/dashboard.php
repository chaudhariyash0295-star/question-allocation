<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['student']);
$pageTitle = 'Student Portal';

$userId = $_SESSION['user_id'];
$studentId = $_SESSION['user_detail_id'] ?? 0;

// Fetch full student profile
$stuStmt = $pdo->prepare("
    SELECT s.*, u.name, u.email, u.username,
           sem.semester_name, d.division_name, b.batch_name, b.start_roll_no, b.end_roll_no
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN semesters sem ON s.semester_id = sem.semester_id
    JOIN divisions d ON s.division_id = d.division_id
    JOIN batches b ON s.batch_id = b.batch_id
    WHERE s.student_id = ?
");
$stuStmt->execute([$studentId]);
$student = $stuStmt->fetch();

// Check for Active / Running Exam for this student's batch
$activeExamStmt = $pdo->prepare("
    SELECT e.*, s.subject_name, s.subject_code,
           qa.allocation_id, qa.allocated_at, qa.status as alloc_status,
           q.question_number, q.question_text
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    LEFT JOIN question_allocations qa ON e.exam_id = qa.exam_id AND qa.student_id = ?
    LEFT JOIN questions q ON qa.question_id = q.question_id
    WHERE e.batch_id = ? AND e.status = 'running'
    LIMIT 1
");
$activeExamStmt->execute([$studentId, $student['batch_id']]);
$activeExam = $activeExamStmt->fetch();

// Fetch Upcoming Scheduled Exams
$upcomingStmt = $pdo->prepare("
    SELECT e.*, s.subject_name, s.subject_code
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    WHERE e.batch_id = ? AND e.status = 'scheduled'
    ORDER BY e.exam_date ASC, e.start_time ASC
");
$upcomingStmt->execute([$student['batch_id']]);
$upcomingExams = $upcomingStmt->fetchAll();

// Fetch Completed Exams History for this student
$historyStmt = $pdo->prepare("
    SELECT qa.*, e.exam_name, e.exam_code, e.exam_date, e.start_time, e.end_time,
           s.subject_name, s.subject_code,
           q.question_number, q.question_text
    FROM question_allocations qa
    JOIN exams e ON qa.exam_id = e.exam_id
    JOIN subjects s ON e.subject_id = s.subject_id
    JOIN questions q ON qa.question_id = q.question_id
    WHERE qa.student_id = ? AND e.status = 'completed'
    ORDER BY e.exam_date DESC
");
$historyStmt->execute([$studentId]);
$completedExams = $historyStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<!-- Student Profile Information Banner -->
<div class="card mb-4 bg-white border-primary shadow-sm">
    <div class="card-body p-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-2 text-center text-md-start">
                <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2.2rem;">
                    <i class="fa-solid fa-user-graduate"></i>
                </div>
            </div>
            <div class="col-md-6 text-center text-md-start">
                <h4 class="fw-bold text-dark mb-1"><?= e($student['name']) ?></h4>
                <div class="text-muted small mb-2"><?= e($student['email']) ?> &bull; <code><?= e($student['enrollment_no']) ?></code></div>
                <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-md-start">
                    <span class="badge bg-secondary"><?= e($student['semester_name']) ?></span>
                    <span class="badge bg-light text-dark border">Division <?= e($student['division_name']) ?></span>
                    <span class="badge bg-info text-dark"><?= e($student['batch_name']) ?></span>
                </div>
            </div>
            <div class="col-md-4 text-center text-md-end border-start-md">
                <div class="text-muted small fw-semibold text-uppercase">Assigned Roll Number</div>
                <div class="display-5 fw-extrabold text-primary mb-1">
                    <?= e($student['roll_no']) ?>
                </div>
                <small class="text-muted">Batch Roll Range: <?= $student['start_roll_no'] ?> - <?= $student['end_roll_no'] ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Active Examination Alert / Chit Section -->
<?php if ($activeExam): ?>
    <div class="card mb-4 border-2 border-warning shadow">
        <div class="card-header bg-warning bg-opacity-25 d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark">
                <i class="fa-solid fa-bell fa-shake me-2 text-danger"></i> Practical Examination In Progress!
            </span>
            <span class="badge bg-danger animate-pulse px-3 py-2">LIVE SESSION</span>
        </div>
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 mb-2">
                        <?= e($activeExam['subject_code']) ?> - Practical Examination
                    </div>
                    <h3 class="fw-bold text-dark mb-2"><?= e($activeExam['exam_name']) ?></h3>
                    <p class="text-muted mb-3">
                        <i class="fa-solid fa-clock me-1 text-primary"></i> 
                        Time: <strong><?= format_time($activeExam['start_time']) ?> to <?= format_time($activeExam['end_time']) ?></strong> &bull; 
                        Duration: <strong><?= $activeExam['duration_minutes'] ?> Minutes</strong>
                    </p>
                    
                    <?php if ($activeExam['allocation_id']): ?>
                        <div class="p-3 bg-light rounded border border-success border-opacity-50 mb-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-success">Assigned Chit #<?= e($activeExam['question_number']) ?></span>
                                <span class="fw-semibold text-success">Question Chit Ready</span>
                            </div>
                            <div class="text-dark small text-truncate">
                                <?= e(mb_strimwidth($activeExam['question_text'], 0, 110, '...')) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4 text-center text-lg-end">
                    <a href="active_exam.php?exam_id=<?= $activeExam['exam_id'] ?>" class="btn btn-primary btn-lg px-4 py-3 fw-bold shadow-sm w-100">
                        <i class="fa-solid fa-ticket-simple me-2"></i> View Allocated Question Chit
                    </a>
                    <small class="text-muted d-block mt-2">
                        <i class="fa-solid fa-shield-halved me-1"></i> Question is fixed and cannot be changed.
                    </small>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Upcoming Scheduled Exams -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-calendar-days me-2 text-primary"></i> Upcoming Scheduled Practical Exams</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Examination Title</th>
                        <th>Subject</th>
                        <th>Scheduled Date</th>
                        <th>Lab Timing</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($upcomingExams)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No upcoming examinations scheduled for your batch.</td></tr>
                    <?php else: ?>
                        <?php foreach ($upcomingExams as $up): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= e($up['exam_name']) ?></td>
                                <td>
                                    <code><?= e($up['subject_code']) ?></code> - <?= e($up['subject_name']) ?>
                                </td>
                                <td><?= format_date($up['exam_date']) ?></td>
                                <td><?= format_time($up['start_time']) ?> - <?= format_time($up['end_time']) ?></td>
                                <td><span class="badge bg-info text-dark">Scheduled</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Completed Exams History -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i> Completed Practical Examination History</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Exam & Subject</th>
                        <th>Date</th>
                        <th class="text-center">Assigned Q#</th>
                        <th>Question Problem Statement</th>
                        <th>Allocated At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($completedExams)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No completed practical examinations yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($completedExams as $comp): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($comp['exam_name']) ?></div>
                                    <small class="text-muted"><?= e($comp['subject_code']) ?> &bull; <?= e($comp['subject_name']) ?></small>
                                </td>
                                <td><?= format_date($comp['exam_date']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary fs-6 px-2 py-1">Q #<?= e($comp['question_number']) ?></span>
                                </td>
                                <td>
                                    <div class="small text-dark" style="max-width: 500px;">
                                        <?= nl2br(e($comp['question_text'])) ?>
                                    </div>
                                </td>
                                <td><small class="text-muted"><?= format_datetime($comp['allocated_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
