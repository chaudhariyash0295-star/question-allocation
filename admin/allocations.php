<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Question Allocations View';

$examId = (int)($_GET['exam_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

// Fetch all exams for dropdown
$exams = $pdo->query("
    SELECT e.exam_id, e.exam_name, e.exam_code, e.exam_date, e.status, s.subject_name, b.batch_name
    FROM exams e
    JOIN subjects s ON e.subject_id = s.subject_id
    JOIN batches b ON e.batch_id = b.batch_id
    ORDER BY e.exam_date DESC, e.exam_id DESC
")->fetchAll();

if (!$examId && !empty($exams)) {
    $examId = $exams[0]['exam_id'];
}

// Fetch selected exam details
$selectedExam = null;
if ($examId > 0) {
    $stmt = $pdo->prepare("
        SELECT e.*, s.subject_name, s.subject_code, sem.semester_name, d.division_name, b.batch_name,
               u.name as faculty_name,
               (SELECT COUNT(*) FROM question_allocations qa WHERE qa.exam_id = e.exam_id) as total_allocated
        FROM exams e
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN semesters sem ON e.semester_id = sem.semester_id
        JOIN divisions d ON e.division_id = d.division_id
        JOIN batches b ON e.batch_id = b.batch_id
        JOIN faculty f ON e.faculty_id = f.faculty_id
        JOIN users u ON f.user_id = u.user_id
        WHERE e.exam_id = ?
    ");
    $stmt->execute([$examId]);
    $selectedExam = $stmt->fetch();
}

// Fetch Allocations for this exam
$allocations = [];
if ($examId > 0) {
    $where = ["qa.exam_id = ?"];
    $params = [$examId];

    if (!empty($search)) {
        $where[] = "(u.name LIKE ? OR s.roll_no LIKE ? OR q.question_number LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term]);
    }

    $whereClause = implode(" AND ", $where);
    $qStmt = $pdo->prepare("
        SELECT qa.*, s.roll_no, s.enrollment_no, u.name as student_name, u.email as student_email,
               q.question_number, q.question_text, b.batch_name,
               admin_u.name as allocator_name
        FROM question_allocations qa
        JOIN students s ON qa.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        JOIN questions q ON qa.question_id = q.question_id
        JOIN batches b ON s.batch_id = b.batch_id
        JOIN users admin_u ON qa.allocated_by = admin_u.user_id
        WHERE {$whereClause}
        ORDER BY s.roll_no ASC
    ");
    $qStmt->execute($params);
    $allocations = $qStmt->fetchAll();
}

// Check consecutive collision analysis
$consecutiveCollisions = 0;
for ($i = 1; $i < count($allocations); $i++) {
    if ($allocations[$i]['question_id'] === $allocations[$i-1]['question_id']) {
        $consecutiveCollisions++;
    }
}

// CSV Export Handler
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $selectedExam) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=allocations_' . $selectedExam['exam_code'] . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Roll No', 'Student Name', 'Enrollment No', 'Batch', 'Question No', 'Question Statement', 'Allocated At', 'Status']);
    foreach ($allocations as $row) {
        fputcsv($output, [
            $row['roll_no'],
            $row['student_name'],
            $row['enrollment_no'],
            $row['batch_name'],
            $row['question_number'],
            $row['question_text'],
            $row['allocated_at'],
            $row['status']
        ]);
    }
    fclose($output);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Practical Exam Question Allocations</h3>
        <p class="text-muted small mb-0">Live record of allocated questions per student roll number, ensuring compliance with examination non-consecutive rules.</p>
    </div>
    <div class="d-flex gap-2 no-print">
        <?php if ($selectedExam): ?>
            <a href="allocations.php?exam_id=<?= $examId ?>&export=csv" class="btn btn-outline-success btn-sm">
                <i class="fa-solid fa-file-csv me-1"></i> Export CSV
            </a>
            <button class="btn btn-outline-dark btn-sm btn-print">
                <i class="fa-solid fa-print me-1"></i> Print Sheet
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Exam Selector and Stats -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="allocations.php" class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label small fw-semibold text-muted">Select Practical Examination Session</label>
                <select name="exam_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($exams as $e): ?>
                        <option value="<?= $e['exam_id'] ?>" <?= $examId == $e['exam_id'] ? 'selected' : '' ?>>
                            <?= e($e['exam_name']) ?> (<?= e($e['subject_name']) ?> - <?= e($e['batch_name']) ?>) [<?= ucfirst($e['status']) ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Search Roll No or Student</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search roll no..." value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">View</button>
                <a href="allocations.php?exam_id=<?= $examId ?>" class="btn btn-outline-secondary" title="Reset Search"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedExam): ?>
    <!-- Exam Banner Summary -->
    <div class="card mb-4 border-primary">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-8">
                    <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 mb-1">
                        Code: <?= e($selectedExam['exam_code']) ?>
                    </div>
                    <h4 class="fw-bold text-dark mb-1"><?= e($selectedExam['exam_name']) ?></h4>
                    <div class="text-muted small">
                        <strong>Subject:</strong> <?= e($selectedExam['subject_name']) ?> (<?= e($selectedExam['subject_code']) ?>) &bull;
                        <strong>Semester:</strong> <?= e($selectedExam['semester_name']) ?> &bull;
                        <strong>Batch:</strong> <?= e($selectedExam['batch_name']) ?> (Div <?= e($selectedExam['division_name']) ?>) &bull;
                        <strong>Faculty:</strong> <?= e($selectedExam['faculty_name']) ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="mb-2">
                        <span class="me-2 text-muted small">Exam Status:</span>
                        <?= status_badge($selectedExam['status']) ?>
                    </div>
                    <div class="small text-muted">
                        Total Allocated: <strong class="text-primary fs-6"><?= count($allocations) ?></strong> students
                    </div>
                    <div class="small">
                        Consecutive Collision: 
                        <?php if ($consecutiveCollisions === 0): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-shield-check me-1"></i> 0 (100% Compliant)</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><?= $consecutiveCollisions ?> Collisions</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Allocations Table -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark"><i class="fa-solid fa-list-ol me-2 text-primary"></i> Practical Examination Seating & Question Distribution</span>
            <span class="badge bg-light text-dark border"><?= count($allocations) ?> Record(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 100px;">Roll No</th>
                            <th>Student Name & Enrollment</th>
                            <th style="width: 120px;" class="text-center">Assigned Q#</th>
                            <th>Allocated Practical Problem Statement</th>
                            <th style="width: 140px;">Allocated At</th>
                            <th style="width: 100px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allocations)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fa-solid fa-dice fs-1 text-muted opacity-50 mb-2 d-block"></i>
                                    <?php if ($selectedExam['status'] === 'scheduled'): ?>
                                        This exam has not been started yet. Questions will be automatically distributed once the exam starts.
                                        <div class="mt-2">
                                            <a href="exams.php" class="btn btn-sm btn-primary">Go to Exams & Start</a>
                                        </div>
                                    <?php else: ?>
                                        No allocations found for this examination.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($allocations as $idx => $alloc): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold fs-6 text-primary">
                                            Roll <?= e($alloc['roll_no']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e($alloc['student_name']) ?></div>
                                        <small class="text-muted"><code><?= e($alloc['enrollment_no']) ?></code></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary fs-6 px-3 py-2 shadow-sm">
                                            Q #<?= e($alloc['question_number']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="p-2 bg-light rounded border text-dark" style="font-size: 0.9rem; line-height: 1.5;">
                                            <?= nl2br(e($alloc['question_text'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold"><?= date('h:i:s A', strtotime($alloc['allocated_at'])) ?></div>
                                        <small class="text-muted"><?= date('d M Y', strtotime($alloc['allocated_at'])) ?></small>
                                    </td>
                                    <td><?= status_badge($alloc['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
