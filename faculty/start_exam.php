<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['faculty']);
$pageTitle = 'Start Practical Examination';

$facultyId = $_SESSION['user_detail_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

// Handle Start Exam Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start_exam') {
    $examId = (int)($_POST['exam_id'] ?? 0);
    if ($examId > 0) {
        $result = allocate_exam_questions($pdo, $examId, $userId);
        if ($result['success']) {
            $qps = $result['qps'] ?? 1;
            set_flash('success', "Exam Started! {$qps} question(s) allocated per student. Total: {$result['allocated_count']} allocations for {$result['total_students']} students.");
            header("Location: allocations.php?exam_id=" . $examId);
            exit;
        } else {
            set_flash('error', $result['message']);
        }
    }
    header("Location: start_exam.php");
    exit;
}

// Handle End Exam Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'end_exam') {
    $examId = (int)($_POST['exam_id'] ?? 0);
    if ($examId > 0) {
        $stmt = $pdo->prepare("UPDATE exams SET status = 'completed', ended_at = NOW() WHERE exam_id = ? AND faculty_id = ?");
        $stmt->execute([$examId, $facultyId]);
        set_flash('success', "Examination has been marked as Completed. Student sessions have ended.");
    }
    header("Location: start_exam.php");
    exit;
}

// Fetch all exams for this faculty
$examsStmt = $pdo->prepare("
    SELECT e.*, s.subject_name, s.subject_code, sem.semester_name, d.division_name, b.batch_name,
           (SELECT COUNT(*) FROM students stu WHERE stu.batch_id = e.batch_id AND stu.status = 'active') as eligible_students,
           (SELECT COUNT(*) FROM questions q WHERE q.subject_id = e.subject_id AND q.status = 'active') as available_questions,
           (SELECT COUNT(*) FROM question_allocations qa WHERE qa.exam_id = e.exam_id) as allocated_count
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    JOIN semesters sem ON e.semester_id = sem.semester_id
    JOIN divisions d ON e.division_id = d.division_id
    JOIN batches b ON e.batch_id = b.batch_id
    WHERE e.faculty_id = ?
    ORDER BY CASE WHEN e.status = 'running' THEN 1 WHEN e.status = 'scheduled' THEN 2 ELSE 3 END, e.exam_date DESC
");
$examsStmt->execute([$facultyId]);
$exams = $examsStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Start Practical Examination & Allocation</h3>
        <p class="text-muted small mb-0">Launch digital question distribution sessions for your laboratory examinees with non-consecutive seat fairness.</p>
    </div>
    <a href="create_exam.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus me-1"></i> Schedule New Exam
    </a>
</div>

<!-- Scheduled & Running Exams Table -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-play me-2 text-success"></i> Practical Sessions Ready for Commencement</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Exam & Subject</th>
                        <th>Target Batch</th>
                        <th>Timing</th>
                        <th>Readiness Check</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">No exams scheduled yet. Click "Schedule New Exam" to begin.</td></tr>
                    <?php else: ?>
                        <?php foreach ($exams as $ex): ?>
                            <tr class="<?= $ex['status'] === 'running' ? 'table-warning bg-opacity-25' : '' ?>">
                                <td>
                                    <div class="fw-bold text-dark"><?= e($ex['exam_name']) ?></div>
                                    <small class="text-muted"><?= e($ex['subject_code']) ?> &bull; <?= e($ex['subject_name']) ?> (<?= e($ex['semester_name']) ?>)</small>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= e($ex['batch_name']) ?></span>
                                    <span class="badge bg-light text-dark border">Div <?= e($ex['division_name']) ?></span>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= format_date($ex['exam_date']) ?></div>
                                    <small class="text-muted"><?= format_time($ex['start_time']) ?> - <?= format_time($ex['end_time']) ?></small>
                                </td>
                                <td>
                                    <div class="small">
                                        <i class="fa-solid fa-user-graduate text-muted me-1"></i> Students: 
                                        <strong><?= $ex['eligible_students'] ?></strong>
                                    </div>
                                    <div class="small">
                                        <i class="fa-solid fa-file-circle-question text-muted me-1"></i> Bank Questions: 
                                        <strong><?= $ex['available_questions'] ?></strong>
                                    </div>
                                    <?php if ($ex['eligible_students'] > $ex['available_questions']): ?>
                                        <span class="badge bg-info text-dark" style="font-size: 0.72rem;">Uniform Frequency Balancing</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= status_badge($ex['status']) ?></td>
                                <td class="text-end">
                                    <?php if ($ex['status'] === 'scheduled' || $ex['status'] === 'draft'): ?>
                                        <button class="btn btn-success btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#confirmStartModal<?= $ex['exam_id'] ?>">
                                            <i class="fa-solid fa-play me-1"></i> Start Exam
                                        </button>
                                    <?php elseif ($ex['status'] === 'running'): ?>
                                        <form method="POST" action="start_exam.php" class="d-inline">
                                            <input type="hidden" name="action" value="end_exam">
                                            <input type="hidden" name="exam_id" value="<?= $ex['exam_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" data-confirm="Are you sure you want to end this examination? Students will no longer see active timers.">
                                                <i class="fa-solid fa-stop me-1"></i> End Exam
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <a href="allocations.php?exam_id=<?= $ex['exam_id'] ?>" class="btn btn-outline-primary btn-sm ms-1" title="View Allocations">
                                        <i class="fa-solid fa-table-list"></i>
                                    </a>

                                    <!-- Confirmation Modal -->
                                    <div class="modal fade text-start" id="confirmStartModal<?= $ex['exam_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="start_exam.php">
                                                    <input type="hidden" name="action" value="start_exam">
                                                    <input type="hidden" name="exam_id" value="<?= $ex['exam_id'] ?>">
                                                    <div class="modal-header bg-light">
                                                        <h5 class="modal-title fw-bold text-success">
                                                            <i class="fa-solid fa-circle-play me-2"></i> Start Examination Confirmation
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-3">Are you sure you want to start this examination? The smart allocation engine will automatically assign questions right now.</p>

                                                        <div class="p-3 bg-light rounded border mb-3">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Exam Name:</span>
                                                                <strong class="text-dark"><?= e($ex['exam_name']) ?></strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Subject:</span>
                                                                <span class="fw-semibold"><?= e($ex['subject_name']) ?></span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Batch:</span>
                                                                <span class="fw-semibold"><?= e($ex['batch_name']) ?> (Div <?= e($ex['division_name']) ?>)</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Total Students:</span>
                                                                <strong class="text-primary"><?= $ex['eligible_students'] ?> Students</strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Total Questions:</span>
                                                                <strong class="text-success"><?= $ex['available_questions'] ?> Questions</strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Questions Per Student:</span>
                                                                <span class="badge bg-primary fs-6"><?= (int)($ex['questions_per_student'] ?? 1) ?> per student</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between">
                                                                <span class="text-muted">Rule:</span>
                                                                <span class="badge bg-info text-dark">No Consecutive Repetition</span>
                                                            </div>
                                                        </div>

                                                        <div class="alert alert-warning small py-2 mb-0">
                                                            <i class="fa-solid fa-shield-halved me-1"></i>
                                                            Once allocated, questions are permanently stored in the database. Student refreshing will not alter assigned questions.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success fw-bold">
                                                            <i class="fa-solid fa-bolt me-1"></i> Confirm & Start Examination
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
