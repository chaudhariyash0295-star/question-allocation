<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Manage Exams';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$subjects = $pdo->query("SELECT * FROM subjects WHERE status = 'active' ORDER BY subject_name ASC")->fetchAll();
$semesters = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY semester_id ASC")->fetchAll();
$divisions = $pdo->query("SELECT * FROM divisions WHERE status = 'active' ORDER BY division_name ASC")->fetchAll();
$batches = $pdo->query("SELECT b.*, sem.semester_name, d.division_name FROM batches b JOIN semesters sem ON b.semester_id = sem.semester_id JOIN divisions d ON b.division_id = d.division_id WHERE b.status = 'active' ORDER BY b.batch_name ASC")->fetchAll();
$facultyList = $pdo->query("SELECT f.*, u.name FROM faculty f JOIN users u ON f.user_id = u.user_id WHERE f.status = 'active' ORDER BY u.name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        $exam_name = trim($_POST['exam_name'] ?? '');
        $exam_code = strtoupper(trim($_POST['exam_code'] ?? ''));
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $division_id = (int)($_POST['division_id'] ?? 0);
        $batch_id = (int)($_POST['batch_id'] ?? 0);
        $faculty_id = (int)($_POST['faculty_id'] ?? 0);
        $exam_date = $_POST['exam_date'] ?? date('Y-m-d');
        $start_time = $_POST['start_time'] ?? '10:00:00';
        $end_time = $_POST['end_time'] ?? '12:00:00';
        $duration_minutes = (int)($_POST['duration_minutes'] ?? 120);
        $total_marks = (int)($_POST['total_marks'] ?? 50);
        $allocation_rule = $_POST['allocation_rule'] ?? 'random_no_consecutive';
        $instructions = trim($_POST['instructions'] ?? '');
        $status = $_POST['status'] ?? 'scheduled';

        if (empty($exam_name) || empty($exam_code) || !$subject_id || !$semester_id || !$division_id || !$batch_id || !$faculty_id) {
            set_flash('error', 'Please fill in all required examination parameters.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO exams (exam_name, exam_code, subject_id, semester_id, division_id, batch_id, faculty_id, exam_date, start_time, end_time, duration_minutes, total_marks, allocation_rule, instructions, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $exam_name, $exam_code, $subject_id, $semester_id, $division_id, $batch_id,
                    $faculty_id, $exam_date, $start_time, $end_time, $duration_minutes,
                    $total_marks, $allocation_rule, $instructions, $status
                ]);
                set_flash('success', "Practical examination session '{$exam_name}' scheduled successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Error scheduling exam (Duplicate exam code or db error): ' . $e->getMessage());
            }
        }
        header("Location: exams.php");
        exit;
    }

    if ($postAction === 'start_exam') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        if ($exam_id > 0) {
            $res = allocate_exam_questions($pdo, $exam_id, $_SESSION['user_id']);
            if ($res['success']) {
                set_flash('success', $res['message']);
            } else {
                set_flash('error', $res['message']);
            }
        }
        header("Location: exams.php");
        exit;
    }

    if ($postAction === 'status_update') {
        $exam_id = (int)($_POST['exam_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($exam_id > 0 && in_array($status, ['draft', 'scheduled', 'running', 'completed', 'cancelled'])) {
            $extra = "";
            if ($status === 'completed') {
                $extra = ", ended_at = NOW()";
            }
            $stmt = $pdo->prepare("UPDATE exams SET status = ? {$extra} WHERE exam_id = ?");
            $stmt->execute([$status, $exam_id]);
            set_flash('success', "Exam status updated to " . ucfirst($status) . ".");
        }
        header("Location: exams.php");
        exit;
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM exams WHERE exam_id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Exam record removed.");
    } catch (PDOException $e) {
        set_flash('error', 'Error deleting exam: ' . $e->getMessage());
    }
    header("Location: exams.php");
    exit;
}

// Fetch all exams with meta statistics
$exams = $pdo->query("
    SELECT e.*, s.subject_name, s.subject_code, sem.semester_name, d.division_name, b.batch_name,
           u.name as faculty_name,
           (SELECT COUNT(*) FROM students stu WHERE stu.batch_id = e.batch_id AND stu.status = 'active') as eligible_students,
           (SELECT COUNT(*) FROM questions q WHERE q.subject_id = e.subject_id AND q.status = 'active') as available_questions,
           (SELECT COUNT(*) FROM question_allocations qa WHERE qa.exam_id = e.exam_id) as allocated_count
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    JOIN semesters sem ON e.semester_id = sem.semester_id
    JOIN divisions d ON e.division_id = d.division_id
    JOIN batches b ON e.batch_id = b.batch_id
    JOIN faculty f ON e.faculty_id = f.faculty_id
    JOIN users u ON f.user_id = u.user_id
    ORDER BY e.exam_date DESC, e.start_time DESC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Manage Practical Examinations</h3>
        <p class="text-muted small mb-0">Schedule exam sessions, initiate question allocations, and monitor live timer status.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">
        <i class="fa-solid fa-plus me-1"></i> Schedule Exam
    </button>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-laptop-code me-2 text-primary"></i> Practical Exam Sessions (<?= count($exams) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Exam Details</th>
                        <th>Subject & Semester</th>
                        <th>Target Batch</th>
                        <th>Date & Timing</th>
                        <th>Students / Questions</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No examinations scheduled yet. Click "Schedule Exam" to set one up.</td></tr>
                    <?php else: ?>
                        <?php foreach ($exams as $ex): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($ex['exam_name']) ?></div>
                                    <small class="text-muted"><code><?= e($ex['exam_code']) ?></code> &bull; Coordinator: <?= e($ex['faculty_name']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($ex['subject_name']) ?></div>
                                    <small class="text-muted"><?= e($ex['subject_code']) ?> (<?= e($ex['semester_name']) ?>)</small>
                                </td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= e($ex['batch_name']) ?></span>
                                    <span class="badge bg-light text-dark border">Div <?= e($ex['division_name']) ?></span>
                                </td>
                                <td>
                                    <div class="small fw-semibold"><?= format_date($ex['exam_date']) ?></div>
                                    <small class="text-muted"><?= format_time($ex['start_time']) ?> - <?= format_time($ex['end_time']) ?> (<?= $ex['duration_minutes'] ?>m)</small>
                                </td>
                                <td>
                                    <div class="small">
                                        <i class="fa-solid fa-user-graduate text-muted me-1"></i> Students: <strong><?= $ex['eligible_students'] ?></strong>
                                    </div>
                                    <div class="small">
                                        <i class="fa-solid fa-file-lines text-muted me-1"></i> Bank Qs: <strong><?= $ex['available_questions'] ?></strong>
                                    </div>
                                    <?php if ($ex['allocated_count'] > 0): ?>
                                        <div class="text-success small fw-bold mt-1">
                                            <i class="fa-solid fa-check-double me-1"></i> Allocated: <?= $ex['allocated_count'] ?>/<?= $ex['eligible_students'] ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= status_badge($ex['status']) ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                                        <?php if ($ex['status'] === 'scheduled' || $ex['status'] === 'draft'): ?>
                                            <!-- Start Exam Button -->
                                            <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#startExamModal<?= $ex['exam_id'] ?>" title="Start Exam & Allocate Questions">
                                                <i class="fa-solid fa-play me-1"></i> Start
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($ex['status'] === 'running'): ?>
                                            <form method="POST" action="exams.php" class="d-inline">
                                                <input type="hidden" name="action" value="status_update">
                                                <input type="hidden" name="exam_id" value="<?= $ex['exam_id'] ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" class="btn btn-sm btn-primary" data-confirm="Are you sure you want to end this examination session? Students will no longer see active timers." title="End Examination">
                                                    <i class="fa-solid fa-stop me-1"></i> End Exam
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <a href="allocations.php?exam_id=<?= $ex['exam_id'] ?>" class="btn btn-sm btn-outline-primary" title="View Allocations Table">
                                            <i class="fa-solid fa-list-check"></i>
                                        </a>

                                        <a href="exams.php?action=delete&id=<?= $ex['exam_id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Delete exam <?= e($ex['exam_name']) ?>?" title="Delete Exam">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>

                                    <!-- Start Exam Confirmation Modal -->
                                    <div class="modal fade text-start" id="startExamModal<?= $ex['exam_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="exams.php">
                                                    <input type="hidden" name="action" value="start_exam">
                                                    <input type="hidden" name="exam_id" value="<?= $ex['exam_id'] ?>">
                                                    <div class="modal-header bg-light">
                                                        <h5 class="modal-title fw-bold text-success"><i class="fa-solid fa-circle-play me-2"></i> Start Examination & Allocate</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p class="mb-3">Are you sure you want to start this practical examination? The system will immediately allocate questions to all registered batch students.</p>
                                                        
                                                        <div class="p-3 bg-light rounded border mb-3">
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Exam:</span>
                                                                <strong class="text-dark"><?= e($ex['exam_name']) ?></strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Subject:</span>
                                                                <span class="fw-semibold"><?= e($ex['subject_name']) ?> (<?= e($ex['subject_code']) ?>)</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Target Batch:</span>
                                                                <span class="fw-semibold"><?= e($ex['batch_name']) ?> (Div <?= e($ex['division_name']) ?>)</span>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Total Students:</span>
                                                                <strong class="text-primary"><?= $ex['eligible_students'] ?> Students</strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between mb-1">
                                                                <span class="text-muted">Total Bank Questions:</span>
                                                                <strong class="text-success"><?= $ex['available_questions'] ?> Questions</strong>
                                                            </div>
                                                            <div class="d-flex justify-content-between">
                                                                <span class="text-muted">Rule:</span>
                                                                <span class="badge bg-info text-dark">Non-Consecutive Random</span>
                                                            </div>
                                                        </div>

                                                        <div class="alert alert-warning small py-2 mb-0">
                                                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                                            Once started, question allocations are saved permanently into MySQL. Students cannot change questions.
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-success fw-bold">
                                                            <i class="fa-solid fa-bolt me-1"></i> Confirm & Start Exam
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

<!-- Create Exam Modal -->
<div class="modal fade" id="createExamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="exams.php">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Schedule Practical Examination</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Exam Title / Name</label>
                            <input type="text" name="exam_name" class="form-control" placeholder="e.g. BCA Sem 4 Web Programming Practical Exam" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Exam Code</label>
                            <input type="text" name="exam_code" class="form-control" placeholder="e.g. EXAM-2026-BCA401-A" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['subject_id'] ?>">
                                        <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Faculty In-Charge / Coordinator</label>
                            <select name="faculty_id" class="form-select" required>
                                <option value="">Select Faculty Member</option>
                                <?php foreach ($facultyList as $fac): ?>
                                    <option value="<?= $fac['faculty_id'] ?>"><?= e($fac['name']) ?> (<?= e($fac['faculty_code']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Semester</label>
                            <select name="semester_id" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= $sem['semester_id'] ?>"><?= e($sem['semester_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Division</label>
                            <select name="division_id" class="form-select" required>
                                <option value="">Select Division</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= $d['division_id'] ?>">Division <?= e($d['division_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Batch</label>
                            <select name="batch_id" class="form-select" required>
                                <option value="">Select Lab Batch</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?= $b['batch_id'] ?>">
                                        <?= e($b['batch_name']) ?> (Roll <?= $b['start_roll_no'] ?> - <?= $b['end_roll_no'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Exam Date</label>
                            <input type="date" name="exam_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Start Time</label>
                            <input type="time" name="start_time" class="form-control" value="09:00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">End Time</label>
                            <input type="time" name="end_time" class="form-control" value="12:00" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Duration (Minutes)</label>
                            <input type="number" name="duration_minutes" class="form-control" value="120" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Marks</label>
                            <input type="number" name="total_marks" class="form-control" value="50" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Allocation Engine Rule</label>
                            <select name="allocation_rule" class="form-select" required>
                                <option value="random_no_consecutive" selected>No Same Question for Consecutive Students</option>
                                <option value="pure_random">Pure Random Non-Adjacent</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Instructions for Examinees</label>
                            <textarea name="instructions" class="form-control" rows="3" placeholder="1. Demonstrate working code to the external examiner...&#10;2. Do not close browser..."></textarea>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Initial Status</label>
                            <select name="status" class="form-select">
                                <option value="scheduled" selected>Scheduled</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save & Schedule Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
