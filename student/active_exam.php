<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['student']);
$pageTitle = 'Practical Examination Chit';

$userId = $_SESSION['user_id'];
$studentId = $_SESSION['user_detail_id'] ?? 0;
$examId = (int)($_GET['exam_id'] ?? 0);

// Fetch Student details
$stuStmt = $pdo->prepare("
    SELECT s.*, u.name, u.email, sem.semester_name, d.division_name, b.batch_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN semesters sem ON s.semester_id = sem.semester_id
    JOIN divisions d ON s.division_id = d.division_id
    JOIN batches b ON s.batch_id = b.batch_id
    WHERE s.student_id = ?
");
$stuStmt->execute([$studentId]);
$student = $stuStmt->fetch();

// Guard: student profile must exist
if (!$student) {
    $_SESSION['flash_error'] = 'Student profile not found. Please contact the administrator.';
    header('Location: ../logout.php');
    exit;
}

// Find active exam if no exam_id provided
if (!$examId) {
    $findStmt = $pdo->prepare("
        SELECT exam_id FROM exams 
        WHERE batch_id = ? AND status = 'running' 
        ORDER BY exam_id DESC LIMIT 1
    ");
    $findStmt->execute([(int)$student['batch_id']]);
    $examId = (int)$findStmt->fetchColumn();
}

if (!$examId) {
    set_flash('info', 'There is no practical examination currently active for your batch.');
    header("Location: dashboard.php");
    exit;
}

// Fetch Exam details
$stmt = $pdo->prepare("
    SELECT e.*, s.subject_name, s.subject_code,
           f_u.name as examiner_name
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    JOIN faculty f ON e.faculty_id = f.faculty_id
    JOIN users f_u ON f.user_id = f_u.user_id
    WHERE e.exam_id = ?
");
$stmt->execute([$examId]);
$exam = $stmt->fetch();

// Fetch ALL allocated questions for this student (ordered by slot_number)
$allocStmt = $pdo->prepare("
    SELECT qa.allocation_id, qa.slot_number, qa.allocated_at, qa.status as alloc_status,
           q.question_number, q.question_text
    FROM question_allocations qa
    JOIN questions q ON qa.question_id = q.question_id
    WHERE qa.exam_id = ? AND qa.student_id = ?
    ORDER BY qa.slot_number ASC
");
$allocStmt->execute([$examId, $studentId]);
$allocatedQuestions = $allocStmt->fetchAll();

if (!$exam) {
    set_flash('error', 'Practical examination record not found.');
    header("Location: dashboard.php");
    exit;
}

// Verify batch eligibility
if ((int)$exam['batch_id'] !== (int)$student['batch_id']) {
    set_flash('error', 'You are not enrolled in the batch participating in this examination.');
    header("Location: dashboard.php");
    exit;
}

// Calculate remaining seconds based on server-side started_at and duration_minutes OR end_time
$now = time();
$examEnded = false;
$remainingSeconds = 0;

if ($exam['status'] === 'completed' || $exam['status'] === 'cancelled') {
    $examEnded = true;
} elseif ($exam['status'] === 'running') {
    // If started_at exists, calculate end timestamp
    if (!empty($exam['started_at'])) {
        $startedTimestamp = strtotime($exam['started_at']);
        $endTimestamp = $startedTimestamp + ($exam['duration_minutes'] * 60);
        $remainingSeconds = max(0, $endTimestamp - $now);
        if ($remainingSeconds <= 0) {
            $examEnded = true;
        }
    } else {
        // Fallback to exam_date and end_time
        $endDateTime = strtotime($exam['exam_date'] . ' ' . $exam['end_time']);
        $remainingSeconds = max(0, $endDateTime - $now);
        if ($remainingSeconds <= 0) {
            $examEnded = true;
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Header / Navigation Back -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 no-print">
    <div>
        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-dark btn-sm btn-print">
            <i class="fa-solid fa-print me-1"></i> Print Question Chit
        </button>
    </div>
</div>

<?php if ($examEnded): ?>
    <div class="alert alert-danger shadow-sm d-flex align-items-center mb-4" role="alert">
        <i class="fa-solid fa-clock-rotate-left fs-3 me-3"></i>
        <div>
            <h5 class="alert-heading fw-bold mb-1">Practical Examination Has Concluded</h5>
            <p class="mb-0 small">The allocated duration for this practical examination session has ended. Your allocated question remains saved below for review.</p>
        </div>
    </div>
<?php endif; ?>

<!-- OFFICIAL DIGITAL PRACTICAL QUESTION CHIT -->
<div class="card mb-4 border-2 border-primary shadow-lg overflow-hidden">
    <!-- Chit Header Banner -->
    <div class="p-4 text-white text-center" style="background: linear-gradient(135deg, #0f2744 0%, #1e40af 100%);">
        <div class="badge bg-light text-primary px-3 py-1 text-uppercase fw-bold rounded-pill mb-2">
            Paperless Practical Examination Portal
        </div>
        <h2 class="fw-bold mb-1 text-uppercase tracking-wide"><?= e($exam['exam_name']) ?></h2>
        <h5 class="text-info fw-semibold mb-0"><?= e($exam['subject_code']) ?> &bull; <?= e($exam['subject_name']) ?></h5>
    </div>

    <!-- Student & Exam Metadata Bar -->
    <div class="p-3 bg-light border-bottom">
        <div class="row g-2 align-items-center text-center text-md-start">
            <div class="col-6 col-md-3">
                <small class="text-muted text-uppercase d-block" style="font-size: 0.72rem;">Student Name</small>
                <strong class="text-dark fs-6"><?= e($student['name']) ?></strong>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted text-uppercase d-block" style="font-size: 0.72rem;">Assigned Roll Number</small>
                <strong class="text-primary fs-5">Roll #<?= e($student['roll_no']) ?></strong>
            </div>
            <div class="col-6 col-md-3">
                <small class="text-muted text-uppercase d-block" style="font-size: 0.72rem;">Batch & Division</small>
                <span class="badge bg-secondary"><?= e($student['batch_name']) ?> (Div <?= e($student['division_name']) ?>)</span>
            </div>
            <div class="col-6 col-md-3 text-md-end">
                <small class="text-muted text-uppercase d-block" style="font-size: 0.72rem;">Examiner</small>
                <span class="text-dark small fw-semibold"><?= e($exam['examiner_name']) ?></span>
            </div>
        </div>
    </div>

    <!-- Chit Body with Live Timer & Allocated Question -->
    <div class="card-body p-4 p-md-5">
        <!-- Live Countdown Timer -->
        <div class="text-center mb-5 no-print" id="examActionArea">
            <div class="exam-timer-box shadow">
                <div class="text-start">
                    <div class="text-uppercase small text-light opacity-75" style="font-size: 0.75rem; letter-spacing: 0.05em;">Time Remaining</div>
                    <div class="timer-digits" id="timerDisplay">--:--:--</div>
                </div>
                <div class="border-start border-secondary ps-3 ms-2 text-start">
                    <div class="small text-light opacity-75">Duration: <?= $exam['duration_minutes'] ?> mins</div>
                    <div class="small text-info"><i class="fa-solid fa-server me-1"></i> Server Synced</div>
                </div>
            </div>
            <div id="examCountdownTimer" data-remaining-seconds="<?= $remainingSeconds ?>"></div>
        </div>

        <!-- THE QUESTION CHIT BOX -->
        <?php if (!empty($allocatedQuestions)): ?>
            <?php $qps = count($allocatedQuestions); ?>
            <div class="chit-container text-center">
                <div class="chit-badge">
                    <i class="fa-solid fa-ticket-simple me-1"></i>
                    Your Allocated Question<?= $qps > 1 ? 's' : ' Chit' ?>
                    <?php if ($qps > 1): ?>
                    <span class="badge bg-primary ms-2"><?= $qps ?> Questions</span>
                    <?php endif; ?>
                </div>

                <?php foreach ($allocatedQuestions as $idx => $aq): ?>
                <?php $slotLabel = $qps > 1 ? 'Question ' . $aq['slot_number'] . ' of ' . $qps : 'QUESTION NO.'; ?>

                <div class="my-4 <?= $qps > 1 ? 'border rounded-3 p-3 bg-light' : '' ?>">
                    <span class="badge bg-primary fs-<?= $qps > 1 ? '5' : '4' ?> px-4 py-2 shadow-sm rounded-pill mb-3 d-inline-block">
                        <?= $slotLabel ?> <?= $qps > 1 ? '' : e($aq['question_number']) ?>
                        <?php if ($qps > 1): ?>
                        &nbsp;<span class="badge bg-light text-primary fs-6">#<?= e($aq['question_number']) ?></span>
                        <?php endif; ?>
                    </span>

                    <div class="chit-question-text text-start shadow-sm <?= $qps > 1 ? 'mt-2' : '' ?>">
                        <?= nl2br(e($aq['question_text'])) ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 text-muted small mt-3 pt-2 border-top">
                        <div>
                            <i class="fa-solid fa-fingerprint me-1 text-primary"></i>
                            Chit ID: <code>SQAS-AL-<?= str_pad($aq['allocation_id'], 6, '0', STR_PAD_LEFT) ?></code>
                        </div>
                        <div>
                            <i class="fa-solid fa-calendar-check me-1 text-success"></i>
                            Allocated: <strong><?= format_datetime($aq['allocated_at']) ?></strong>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning text-center p-4">
                <i class="fa-solid fa-hourglass-half fs-1 text-warning mb-3 d-block"></i>
                <h5 class="fw-bold">Question Allocation Pending</h5>
                <p class="mb-0">The practical examiner is preparing questions for your laboratory batch. Please wait or refresh the page.</p>
            </div>
        <?php endif; ?>

        <!-- Examination Instructions -->
        <div class="mt-5 p-4 bg-light rounded border">
            <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-circle-exclamation me-2 text-primary"></i> Examination Regulations & Instructions</h6>
            <ul class="text-muted small mb-0 ps-3">
                <li class="mb-1">You must develop and execute the solution for the question allocated specifically to your roll number.</li>
                <li class="mb-1">Consecutive seat examinees receive randomized distinct practical problem statements.</li>
                <li class="mb-1">Your allocated question is <strong>permanently locked</strong> and will not change upon page refresh, browser restart, or re-login.</li>
                <li class="mb-1">Demonstrate your working code and database output to the practical examiner before the timer reaches <code>00:00:00</code>.</li>
                <li>Maintain laboratory discipline. Unauthorized internet communication or code sharing will result in disqualification.</li>
            </ul>
        </div>
    </div>

    <!-- Chit Footer Receipt (Visible in Print) -->
    <div class="p-4 bg-white border-top print-only">
        <div class="row text-center mt-4">
            <div class="col-4">
                <hr class="w-75 mx-auto">
                <small class="fw-bold">Student Signature (Roll #<?= e($student['roll_no']) ?>)</small>
            </div>
            <div class="col-4">
                <hr class="w-75 mx-auto">
                <small class="fw-bold">Internal Examiner Signature</small>
            </div>
            <div class="col-4">
                <hr class="w-75 mx-auto">
                <small class="fw-bold">External Examiner Signature</small>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
