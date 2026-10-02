<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['faculty']);
$pageTitle = 'Faculty Dashboard';

$facultyId = $_SESSION['user_detail_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

// Metric counts
$assignedSubjectsCount = $pdo->prepare("SELECT COUNT(DISTINCT subject_id) FROM faculty_subjects WHERE faculty_id = ?");
$assignedSubjectsCount->execute([$facultyId]);
$totalSubjects = $assignedSubjectsCount->fetchColumn();

$questionsCount = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE created_by = ?");
$questionsCount->execute([$userId]);
$totalQuestions = $questionsCount->fetchColumn();

$runningExamsCount = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE faculty_id = ? AND status = 'running'");
$runningExamsCount->execute([$facultyId]);
$runningExams = $runningExamsCount->fetchColumn();

$scheduledExamsCount = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE faculty_id = ? AND status = 'scheduled'");
$scheduledExamsCount->execute([$facultyId]);
$scheduledExams = $scheduledExamsCount->fetchColumn();

// Fetch upcoming & running exams for this faculty
$myExamsStmt = $pdo->prepare("
    SELECT e.*, s.subject_name, s.subject_code, b.batch_name, sem.semester_name, d.division_name,
           (SELECT COUNT(*) FROM students stu WHERE stu.batch_id = e.batch_id AND stu.status = 'active') as eligible_students,
           (SELECT COUNT(*) FROM question_allocations qa WHERE qa.exam_id = e.exam_id) as allocated_count
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    JOIN batches b ON e.batch_id = b.batch_id
    JOIN semesters sem ON e.semester_id = sem.semester_id
    JOIN divisions d ON e.division_id = d.division_id
    WHERE e.faculty_id = ?
    ORDER BY e.exam_date ASC, e.start_time ASC
");
$myExamsStmt->execute([$facultyId]);
$myExams = $myExamsStmt->fetchAll();

// Recent Allocations in this faculty's exams
$recentAllocStmt = $pdo->prepare("
    SELECT qa.*, e.exam_name, s.roll_no, u.name as student_name, q.question_number, q.question_text
    FROM question_allocations qa
    JOIN exams e ON qa.exam_id = e.exam_id
    JOIN students s ON qa.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN questions q ON qa.question_id = q.question_id
    WHERE e.faculty_id = ?
    ORDER BY qa.allocated_at DESC
    LIMIT 6
");
$recentAllocStmt->execute([$facultyId]);
$recentAllocations = $recentAllocStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Faculty Examination Portal</h3>
        <p class="text-muted small mb-0">Welcome, <strong><?= e($currentUser['name']) ?></strong>. Manage practical question banks, examinee batches, and start paperless chit allocation.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="upload_questions.php" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-file-excel me-1"></i> Upload Questions (Excel)
        </a>
        <a href="create_exam.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i> Create Exam Session
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-white border-start border-primary border-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Assigned Subjects</div>
                <h3 class="fw-bold text-dark mb-0"><?= number_format($totalSubjects) ?></h3>
            </div>
            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                <i class="fa-solid fa-book-bookmark"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-white border-start border-purple border-4" style="border-left-color: #8b5cf6 !important;">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Questions Created</div>
                <h3 class="fw-bold text-dark mb-0"><?= number_format($totalQuestions) ?></h3>
            </div>
            <div class="stat-icon bg-purple bg-opacity-10 text-primary" style="color: #8b5cf6 !important;">
                <i class="fa-solid fa-file-circle-question"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-white border-start border-warning border-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Running Practical Exams</div>
                <h3 class="fw-bold text-warning mb-0"><?= number_format($runningExams) ?></h3>
            </div>
            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                <i class="fa-solid fa-bolt"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card bg-white border-start border-info border-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase">Scheduled Sessions</div>
                <h3 class="fw-bold text-dark mb-0"><?= number_format($scheduledExams) ?></h3>
            </div>
            <div class="stat-icon bg-info bg-opacity-10 text-info">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Faculty Exam Sessions -->
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark"><i class="fa-solid fa-laptop-code me-2 text-primary"></i> Practical Examination Sessions</span>
                <a href="create_exam.php" class="btn btn-sm btn-link text-decoration-none">New Exam</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Exam & Subject</th>
                                <th>Batch / Sem</th>
                                <th>Timing</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($myExams)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-4">No examination sessions scheduled yet. Click "Create Exam Session" to begin.</td></tr>
                            <?php else: ?>
                                <?php foreach ($myExams as $ex): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?= e($ex['exam_name']) ?></div>
                                            <small class="text-muted"><?= e($ex['subject_code']) ?> - <?= e($ex['subject_name']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= e($ex['semester_name']) ?></span>
                                            <span class="badge bg-light text-dark border"><?= e($ex['batch_name']) ?></span>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold"><?= format_date($ex['exam_date']) ?></div>
                                            <small class="text-muted"><?= format_time($ex['start_time']) ?> - <?= format_time($ex['end_time']) ?></small>
                                        </td>
                                        <td><?= status_badge($ex['status']) ?></td>
                                        <td class="text-end">
                                            <?php if ($ex['status'] === 'scheduled'): ?>
                                                <a href="start_exam.php?exam_id=<?= $ex['exam_id'] ?>" class="btn btn-sm btn-success">
                                                    <i class="fa-solid fa-play me-1"></i> Start Exam
                                                </a>
                                            <?php endif; ?>
                                            <a href="allocations.php?exam_id=<?= $ex['exam_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Allocations">
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
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold text-dark"><i class="fa-solid fa-ticket me-2 text-success"></i> Live Question Chits</span>
                <a href="allocations.php" class="btn btn-sm btn-link text-decoration-none">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Roll & Examinee</th>
                                <th>Allocated Q#</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentAllocations)): ?>
                                <tr><td colspan="2" class="text-center text-muted py-4">No active allocations yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentAllocations as $alloc): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-primary">Roll #<?= e($alloc['roll_no']) ?></div>
                                            <small class="text-dark"><?= e($alloc['student_name']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary me-1">Q#<?= e($alloc['question_number']) ?></span>
                                            <small class="text-muted d-block text-truncate" style="max-width: 150px;">
                                                <?= e($alloc['question_text']) ?>
                                            </small>
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
