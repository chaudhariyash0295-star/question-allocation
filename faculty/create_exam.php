<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['faculty']);
$pageTitle = 'Create Practical Examination';

$facultyId = $_SESSION['user_detail_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

// Fetch faculty assigned subjects
$subjStmt = $pdo->prepare("
    SELECT DISTINCT s.subject_id, s.subject_code, s.subject_name, sem.semester_id, sem.semester_name
    FROM faculty_subjects fs
    JOIN subjects s ON fs.subject_id = s.subject_id
    JOIN semesters sem ON fs.semester_id = sem.semester_id
    WHERE fs.faculty_id = ?
    ORDER BY s.subject_code ASC
");
$subjStmt->execute([$facultyId]);
$subjects = $subjStmt->fetchAll();

if (empty($subjects)) {
    $subjects = $pdo->query("SELECT s.subject_id, s.subject_code, s.subject_name, sem.semester_id, sem.semester_name FROM subjects s JOIN semesters sem ON s.semester_id = sem.semester_id WHERE s.status = 'active' ORDER BY s.subject_code ASC")->fetchAll();
}

$semesters = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY semester_id ASC")->fetchAll();
$divisions = $pdo->query("SELECT * FROM divisions WHERE status = 'active' ORDER BY division_name ASC")->fetchAll();
$batches = $pdo->query("SELECT b.*, sem.semester_name, d.division_name FROM batches b JOIN semesters sem ON b.semester_id = sem.semester_id JOIN divisions d ON b.division_id = d.division_id WHERE b.status = 'active' ORDER BY b.batch_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = trim($_POST['exam_name'] ?? '');
    $exam_code = strtoupper(trim($_POST['exam_code'] ?? ''));
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $semester_id = (int)($_POST['semester_id'] ?? 0);
    $division_id = (int)($_POST['division_id'] ?? 0);
    $batch_id = (int)($_POST['batch_id'] ?? 0);
    $exam_date = $_POST['exam_date'] ?? date('Y-m-d');
    $start_time = $_POST['start_time'] ?? '09:00:00';
    $end_time = $_POST['end_time'] ?? '12:00:00';
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 120);
    $total_marks = (int)($_POST['total_marks'] ?? 50);
    $allocation_rule = $_POST['allocation_rule'] ?? 'random_no_consecutive';
    $questions_per_student = max(1, min(10, (int)($_POST['questions_per_student'] ?? 1)));
    $instructions = trim($_POST['instructions'] ?? '');
    $status = $_POST['status'] ?? 'scheduled';

    if (empty($exam_name) || empty($exam_code) || !$subject_id || !$semester_id || !$division_id || !$batch_id) {
        set_flash('error', 'Please fill in all required fields.');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO exams (exam_name, exam_code, subject_id, semester_id, division_id, batch_id, faculty_id, exam_date, start_time, end_time, duration_minutes, total_marks, allocation_rule, questions_per_student, instructions, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $exam_name, $exam_code, $subject_id, $semester_id, $division_id, $batch_id,
                $facultyId, $exam_date, $start_time, $end_time, $duration_minutes,
                $total_marks, $allocation_rule, $questions_per_student, $instructions, $status
            ]);
            set_flash('success', "Practical examination '{$exam_name}' created successfully. You can start it when the lab session begins.");
            header("Location: start_exam.php");
            exit;
        } catch (PDOException $e) {
            set_flash('error', 'Error scheduling exam (Duplicate exam code or db error): ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Create Practical Exam Session</h3>
        <p class="text-muted small mb-0">Configure laboratory batch, timings, and allocation criteria for the upcoming examination.</p>
    </div>
    <a href="start_exam.php" class="btn btn-outline-primary btn-sm">
        <i class="fa-solid fa-play me-1"></i> Go to Start Exam
    </a>
</div>

<div class="card">
    <div class="card-header bg-white">
        <span class="fw-bold text-dark"><i class="fa-solid fa-calendar-plus me-2 text-primary"></i> Practical Examination Configuration</span>
    </div>
    <div class="card-body">
        <form method="POST" action="create_exam.php">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Exam Title / Name <span class="text-danger">*</span></label>
                    <input type="text" name="exam_name" class="form-control" placeholder="e.g. Web Programming Practical Lab Exam - Batch A" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Exam Code <span class="text-danger">*</span></label>
                    <input type="text" name="exam_code" class="form-control" placeholder="e.g. EXAM-2026-BCA401-B1" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
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
                    <label class="form-label fw-semibold">Semester <span class="text-danger">*</span></label>
                    <select name="semester_id" class="form-select" required>
                        <option value="">Select Semester</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= $sem['semester_id'] ?>"><?= e($sem['semester_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Class Division <span class="text-danger">*</span></label>
                    <select name="division_id" class="form-select" required>
                        <option value="">Select Division</option>
                        <?php foreach ($divisions as $d): ?>
                            <option value="<?= $d['division_id'] ?>">Division <?= e($d['division_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Target Lab Batch <span class="text-danger">*</span></label>
                    <select name="batch_id" class="form-select" required>
                        <option value="">Select Laboratory Batch</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['batch_id'] ?>">
                                <?= e($b['batch_name']) ?> (Roll <?= $b['start_roll_no'] ?> - <?= $b['end_roll_no'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Exam Date <span class="text-danger">*</span></label>
                    <input type="date" name="exam_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Start Time <span class="text-danger">*</span></label>
                    <input type="time" name="start_time" class="form-control" value="09:00" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">End Time <span class="text-danger">*</span></label>
                    <input type="time" name="end_time" class="form-control" value="12:00" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Duration (Minutes) <span class="text-danger">*</span></label>
                    <input type="number" name="duration_minutes" class="form-control" value="120" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Total Marks <span class="text-danger">*</span></label>
                    <input type="number" name="total_marks" class="form-control" value="50" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Smart Allocation Rule <span class="text-danger">*</span></label>
                    <select name="allocation_rule" class="form-select" required>
                        <option value="random_no_consecutive" selected>No Same Question for Consecutive Students</option>
                        <option value="pure_random">Pure Random (Non-Adjacent)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Questions Per Student <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="fa-solid fa-layer-group text-primary"></i></span>
                        <input type="number" name="questions_per_student" class="form-control" value="1" min="1" max="10" required
                               placeholder="e.g. 1 or 2">
                        <span class="input-group-text bg-light text-muted small">per student</span>
                    </div>
                    <div class="form-text text-muted">Enter 1 for single question, 2 for two questions, etc.</div>
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold">Exam Instructions for Examinees</label>
                    <textarea name="instructions" class="form-control" rows="3" placeholder="1. Demonstrate working application code to the examiner before time expires.&#10;2. Do not refresh or close browser until confirmed."></textarea>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold">Initial Status</label>
                    <select name="status" class="form-select">
                        <option value="scheduled" selected>Scheduled (Ready for Allocation)</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>

                <div class="col-12 text-end pt-3 border-top">
                    <a href="dashboard.php" class="btn btn-secondary me-2">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-solid fa-check me-1"></i> Save & Schedule Exam
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
