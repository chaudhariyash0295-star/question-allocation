<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Examination Reports & Allocation History';

$filterSem = (int)($_GET['semester_id'] ?? 0);
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterExam = (int)($_GET['exam_id'] ?? 0);
$filterBatch = (int)($_GET['batch_id'] ?? 0);

$semesters = $pdo->query("SELECT * FROM semesters ORDER BY semester_id ASC")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY subject_name ASC")->fetchAll();
$batches = $pdo->query("SELECT * FROM batches ORDER BY batch_name ASC")->fetchAll();
$exams = $pdo->query("SELECT * FROM exams ORDER BY exam_date DESC, exam_name ASC")->fetchAll();

$where = ["1=1"];
$params = [];

if ($filterSem > 0) {
    $where[] = "e.semester_id = ?";
    $params[] = $filterSem;
}
if ($filterSubject > 0) {
    $where[] = "e.subject_id = ?";
    $params[] = $filterSubject;
}
if ($filterExam > 0) {
    $where[] = "e.exam_id = ?";
    $params[] = $filterExam;
}
if ($filterBatch > 0) {
    $where[] = "e.batch_id = ?";
    $params[] = $filterBatch;
}

$whereClause = implode(" AND ", $where);

$reportStmt = $pdo->prepare("
    SELECT qa.*, s.roll_no, s.enrollment_no, u.name as student_name,
           e.exam_name, e.exam_code, e.exam_date, e.start_time, e.end_time,
           sub.subject_code, sub.subject_name,
           sem.semester_name, d.division_name, b.batch_name,
           q.question_number, q.question_text,
           fac_u.name as faculty_name
    FROM question_allocations qa
    JOIN students s ON qa.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN exams e ON qa.exam_id = e.exam_id
    JOIN subjects sub ON e.subject_id = sub.subject_id
    JOIN semesters sem ON e.semester_id = sem.semester_id
    JOIN divisions d ON e.division_id = d.division_id
    JOIN batches b ON e.batch_id = b.batch_id
    JOIN faculty f ON e.faculty_id = f.faculty_id
    JOIN users fac_u ON f.user_id = fac_u.user_id
    JOIN questions q ON qa.question_id = q.question_id
    WHERE {$whereClause}
    ORDER BY e.exam_date DESC, e.exam_id DESC, s.roll_no ASC
");
$reportStmt->execute($params);
$reportRecords = $reportStmt->fetchAll();

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=exam_report_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Exam', 'Date', 'Semester', 'Division', 'Batch', 'Subject', 'Roll No', 'Student Name', 'Question No', 'Question Text', 'Allocated At']);
    foreach ($reportRecords as $r) {
        fputcsv($output, [
            $r['exam_name'],
            $r['exam_date'],
            $r['semester_name'],
            $r['division_name'],
            $r['batch_name'],
            $r['subject_code'] . ' - ' . $r['subject_name'],
            $r['roll_no'],
            $r['student_name'],
            $r['question_number'],
            $r['question_text'],
            $r['allocated_at']
        ]);
    }
    fclose($output);
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Official Examination Reports & History</h3>
        <p class="text-muted small mb-0">Consolidated history of digital question allocations across practical examination sessions.</p>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="reports.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline-success btn-sm">
            <i class="fa-solid fa-file-csv me-1"></i> Export to CSV
        </a>
        <button class="btn btn-outline-dark btn-sm btn-print">
            <i class="fa-solid fa-print me-1"></i> Print Examination Record
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="card mb-4 no-print">
    <div class="card-body">
        <form method="GET" action="reports.php" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Semester</label>
                <select name="semester_id" class="form-select">
                    <option value="">All Semesters</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s['semester_id'] ?>" <?= $filterSem == $s['semester_id'] ? 'selected' : '' ?>><?= e($s['semester_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['subject_id'] ?>" <?= $filterSubject == $sub['subject_id'] ? 'selected' : '' ?>><?= e($sub['subject_code']) ?> - <?= e($sub['subject_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Exam Session</label>
                <select name="exam_id" class="form-select">
                    <option value="">All Exams</option>
                    <?php foreach ($exams as $ex): ?>
                        <option value="<?= $ex['exam_id'] ?>" <?= $filterExam == $ex['exam_id'] ? 'selected' : '' ?>><?= e($ex['exam_name']) ?> (<?= format_date($ex['exam_date']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Filter Report</button>
                <a href="reports.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Print Header (Only visible on print) -->
<div class="print-only text-center mb-4">
    <h2 class="fw-bold mb-1">COLLEGE PRACTICAL EXAMINATION RECORD</h2>
    <h5 class="text-secondary mb-2">Smart Digital Question Allocation - Official Log Sheet</h5>
    <div class="small text-muted">Generated on: <?= date('d M Y, h:i A') ?></div>
    <hr>
</div>

<!-- Report Table -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-file-invoice me-2 text-primary"></i> Practical Examination Audit Records</span>
        <span class="badge bg-light text-dark border"><?= count($reportRecords) ?> Total Record(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">Roll No</th>
                        <th>Student Name & Enrollment</th>
                        <th>Examination & Subject</th>
                        <th>Batch / Div</th>
                        <th style="width: 100px;" class="text-center">Question#</th>
                        <th>Allocated Problem Text</th>
                        <th style="width: 140px;">Allocation Time</th>
                        <th style="width: 110px;" class="print-only">Signature</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportRecords)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No examination allocation records found matching the filter criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportRecords as $r): ?>
                            <tr>
                                <td class="fw-bold text-primary">Roll <?= e($r['roll_no']) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($r['student_name']) ?></div>
                                    <small class="text-muted"><code><?= e($r['enrollment_no']) ?></code></small>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= e($r['exam_name']) ?></div>
                                    <small class="text-muted"><?= e($r['subject_code']) ?> &bull; <?= format_date($r['exam_date']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($r['batch_name']) ?></span>
                                    <small class="text-muted d-block">Div <?= e($r['division_name']) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary px-2 py-1">Q #<?= e($r['question_number']) ?></span>
                                </td>
                                <td>
                                    <small class="text-dark" title="<?= e($r['question_text']) ?>">
                                        <?= e(mb_strimwidth($r['question_text'], 0, 85, '...')) ?>
                                    </small>
                                </td>
                                <td>
                                    <small class="text-muted"><?= format_datetime($r['allocated_at']) ?></small>
                                </td>
                                <td class="print-only">
                                    &nbsp;
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Print Footer Signatures -->
<div class="print-only mt-5 pt-4">
    <div class="row text-center">
        <div class="col-4">
            <hr class="w-75 mx-auto">
            <p class="fw-bold mb-0">Internal Examiner Signature</p>
        </div>
        <div class="col-4">
            <hr class="w-75 mx-auto">
            <p class="fw-bold mb-0">External Examiner Signature</p>
        </div>
        <div class="col-4">
            <hr class="w-75 mx-auto">
            <p class="fw-bold mb-0">Head of Department (HOD)</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
