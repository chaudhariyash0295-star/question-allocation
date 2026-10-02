<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['faculty']);
$pageTitle = 'Upload Questions (Excel / CSV)';

$facultyId = $_SESSION['user_detail_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

// Fetch faculty's assigned subjects
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

// If no direct assignments, fallback to all active subjects for flexibility
if (empty($subjects)) {
    $subjects = $pdo->query("SELECT s.subject_id, s.subject_code, s.subject_name, sem.semester_id, sem.semester_name FROM subjects s JOIN semesters sem ON s.semester_id = sem.semester_id WHERE s.status = 'active' ORDER BY s.subject_code ASC")->fetchAll();
}

$semesters = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY semester_id ASC")->fetchAll();
$divisions = $pdo->query("SELECT * FROM divisions WHERE status = 'active' ORDER BY division_name ASC")->fetchAll();
$batches = $pdo->query("SELECT * FROM batches WHERE status = 'active' ORDER BY batch_name ASC")->fetchAll();

// Handle Sample Template Download
if (isset($_GET['download']) && $_GET['download'] === 'sample') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sample_questions_template.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Question No', 'Question']);
    fputcsv($out, [1, 'Create a PHP script to accept a user registration form and validate inputs.']);
    fputcsv($out, [2, 'Design an HTML5 form with client-side JavaScript validation.']);
    fputcsv($out, [3, 'Write a PHP and MySQL application to perform CRUD operations for Student records.']);
    fputcsv($out, [4, 'Develop a PHP session-based shopping cart system.']);
    fputcsv($out, [5, 'Create an AJAX-powered live search in PHP and MySQL.']);
    fclose($out);
    exit;
}

$previewData = null;
$stats = null;
$selectedSubjectId = (int)($_GET['subject_id'] ?? ($_POST['subject_id'] ?? 0));
$selectedSemesterId = (int)($_POST['semester_id'] ?? 0);
$selectedBatchId = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;

// STEP 5 & 6: File Upload & Validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate_upload') {
    $selectedSubjectId = (int)($_POST['subject_id'] ?? 0);
    $selectedSemesterId = (int)($_POST['semester_id'] ?? 0);
    $selectedBatchId = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;

    if (!$selectedSubjectId || !$selectedSemesterId) {
        set_flash('error', 'Please select both Subject and Semester.');
    } elseif (!isset($_FILES['question_file']) || $_FILES['question_file']['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Please select a valid Excel (.xlsx) or CSV (.csv) file to upload.');
    } else {
        $file = $_FILES['question_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'csv'])) {
            set_flash('error', 'Only .xlsx and .csv files are supported.');
        } else {
            // Save temporary upload
            $uploadDir = __DIR__ . '/../uploads/questions/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $targetPath = $uploadDir . 'upload_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                try {
                    $parsedRows = parse_uploaded_question_file($targetPath, $ext);

                    if (empty($parsedRows)) {
                        set_flash('error', 'No questions found in the uploaded file. Please verify file format.');
                    } else {
                        // Fetch existing questions for this subject to check duplicates against DB
                        $existStmt = $pdo->prepare("SELECT question_number, LOWER(TRIM(question_text)) as clean_text FROM questions WHERE subject_id = ?");
                        $existStmt->execute([$selectedSubjectId]);
                        $existingDB = $existStmt->fetchAll();
                        $existingTexts = array_column($existingDB, 'clean_text');
                        $existingNos = array_column($existingDB, 'question_number');

                        $validatedQuestions = [];
                        $seenTexts = [];
                        $validCount = 0;
                        $duplicateCount = 0;
                        $invalidCount = 0;

                        foreach ($parsedRows as $row) {
                            $qNo = $row['question_number'];
                            $qText = trim($row['question_text']);
                            $cleanText = strtolower($qText);

                            if (empty($qText) || strlen($qText) < 5) {
                                $status = 'invalid';
                                $reason = 'Text too short or empty';
                                $invalidCount++;
                            } elseif (in_array($cleanText, $seenTexts, true) || in_array($cleanText, $existingTexts, true)) {
                                $status = 'duplicate';
                                $reason = 'Duplicate question detected (already exists in bank or file)';
                                $duplicateCount++;
                            } else {
                                $status = 'valid';
                                $reason = 'Ready for import';
                                $validCount++;
                                $seenTexts[] = $cleanText;
                            }

                            $validatedQuestions[] = [
                                'question_number' => $qNo,
                                'question_text'   => $qText,
                                'status'          => $status,
                                'reason'          => $reason
                            ];
                        }

                        // Store in session for the confirm step
                        $_SESSION['pending_import'] = [
                            'subject_id'   => $selectedSubjectId,
                            'semester_id'  => $selectedSemesterId,
                            'batch_id'     => $selectedBatchId,
                            'questions'    => $validatedQuestions,
                            'valid_count'  => $validCount
                        ];

                        $previewData = $validatedQuestions;
                        $stats = [
                            'total'     => count($validatedQuestions),
                            'valid'     => $validCount,
                            'duplicate' => $duplicateCount,
                            'invalid'   => $invalidCount
                        ];
                    }
                } catch (Exception $e) {
                    set_flash('error', 'Error reading uploaded file: ' . $e->getMessage());
                }
            } else {
                set_flash('error', 'Failed to save uploaded file.');
            }
        }
    }
}

// STEP 7: Import Questions into MySQL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_import') {
    if (!isset($_SESSION['pending_import']) || empty($_SESSION['pending_import']['questions'])) {
        set_flash('error', 'No pending questions found for import. Please re-upload the file.');
        header("Location: upload_questions.php");
        exit;
    }

    $import = $_SESSION['pending_import'];
    $subId = $import['subject_id'];
    $semId = $import['semester_id'];
    $batchId = $import['batch_id'];
    $questions = $import['questions'];

    // Get current max question number for this subject
    $maxNoStmt = $pdo->prepare("SELECT COALESCE(MAX(question_number), 0) FROM questions WHERE subject_id = ?");
    $maxNoStmt->execute([$subId]);
    $currentMax = (int)$maxNoStmt->fetchColumn();

    $imported = 0;
    try {
        $pdo->beginTransaction();
        $insStmt = $pdo->prepare("
            INSERT INTO questions (subject_id, semester_id, batch_id, question_number, question_text, status, created_by)
            VALUES (?, ?, ?, ?, ?, 'active', ?)
        ");

        foreach ($questions as $q) {
            if ($q['status'] === 'valid') {
                $currentMax++;
                $qNumber = ($q['question_number'] > 0 && $q['question_number'] > $currentMax) ? $q['question_number'] : $currentMax;
                $insStmt->execute([$subId, $semId, $batchId, $qNumber, $q['question_text'], $userId]);
                $imported++;
            }
        }

        $pdo->commit();
        unset($_SESSION['pending_import']);
        set_flash('success', "Success! {$imported} valid questions have been successfully imported into the Question Bank.");
        header("Location: questions.php?subject_id=" . $subId);
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', 'Database error during import: ' . $e->getMessage());
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Upload Questions via Excel / CSV</h3>
        <p class="text-muted small mb-0">Bulk import practical examination questions with automatic duplicate detection and validation.</p>
    </div>
    <a href="upload_questions.php?download=sample" class="btn btn-outline-success btn-sm">
        <i class="fa-solid fa-download me-1"></i> Download Sample CSV Format
    </a>
</div>

<!-- Upload Wizard Card -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <span class="fw-bold text-dark"><i class="fa-solid fa-file-excel me-2 text-success"></i> Upload & Validation Wizard</span>
    </div>
    <div class="card-body">
        <form method="POST" action="upload_questions.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="validate_upload">
            <div class="row g-3">
                <!-- STEP 1: Select Subject -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Step 1: Select Subject <span class="text-danger">*</span></label>
                    <select name="subject_id" class="form-select" required>
                        <option value="">Choose Course Subject</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['subject_id'] ?>" <?= $selectedSubjectId == $s['subject_id'] ? 'selected' : '' ?>>
                                <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- STEP 2: Select Semester -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Step 2: Select Semester <span class="text-danger">*</span></label>
                    <select name="semester_id" class="form-select" required>
                        <option value="">Choose Semester</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?= $sem['semester_id'] ?>" <?= $selectedSemesterId == $sem['semester_id'] ? 'selected' : '' ?>>
                                <?= e($sem['semester_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- STEP 3: Select Batch/Division -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Step 3: Lab Batch (Optional)</label>
                    <select name="batch_id" class="form-select">
                        <option value="">Subject-Wide (Available for All Batches)</option>
                        <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['batch_id'] ?>" <?= $selectedBatchId == $b['batch_id'] ? 'selected' : '' ?>>
                                <?= e($b['batch_name']) ?> (Roll <?= $b['start_roll_no'] ?>-<?= $b['end_roll_no'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- STEP 4: Upload File -->
                <div class="col-md-12">
                    <label class="form-label fw-semibold">Step 4: Select Excel or CSV File <span class="text-danger">*</span></label>
                    <input type="file" name="question_file" class="form-control py-2" accept=".xlsx, .csv" required>
                    <small class="text-muted">
                        Accepted file types: <strong>.xlsx (Microsoft Excel)</strong> or <strong>.csv (Comma-Separated Values)</strong>. Format: Column 1 = <code>Question No</code>, Column 2 = <code>Question Text</code>.
                    </small>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-solid fa-magnifying-glass me-1"></i> Validate & Preview File
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- STEP 6: Validation Statistics & Preview -->
<?php if ($stats && $previewData): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card stat-card bg-white border-start border-primary border-4">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Total In File</div>
                    <h3 class="fw-bold text-dark mb-0"><?= $stats['total'] ?></h3>
                </div>
                <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fa-solid fa-list-ol"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-white border-start border-success border-4">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Valid Questions</div>
                    <h3 class="fw-bold text-success mb-0"><?= $stats['valid'] ?></h3>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fa-solid fa-check"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-white border-start border-warning border-4">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Duplicate Questions</div>
                    <h3 class="fw-bold text-warning mb-0"><?= $stats['duplicate'] ?></h3>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-clone"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card bg-white border-start border-danger border-4">
                <div>
                    <div class="text-muted small fw-semibold text-uppercase">Invalid / Empty</div>
                    <h3 class="fw-bold text-danger mb-0"><?= $stats['invalid'] ?></h3>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
            </div>
        </div>
    </div>

    <!-- Preview Table & Import Action -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold text-dark"><i class="fa-solid fa-eye me-2 text-primary"></i> Questions Validation Preview</span>
            <?php if ($stats['valid'] > 0): ?>
                <form method="POST" action="upload_questions.php" class="d-inline">
                    <input type="hidden" name="action" value="confirm_import">
                    <button type="submit" class="btn btn-success fw-bold shadow-sm">
                        <i class="fa-solid fa-file-import me-1"></i> Import <?= $stats['valid'] ?> Valid Questions to MySQL
                    </button>
                </form>
            <?php else: ?>
                <span class="text-danger small fw-semibold">No valid questions to import.</span>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 70px;">#</th>
                            <th>Question Statement</th>
                            <th style="width: 140px;">Validation Status</th>
                            <th style="width: 260px;">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previewData as $q): ?>
                            <tr>
                                <td><span class="badge bg-light text-dark border">Q#<?= e($q['question_number']) ?></span></td>
                                <td>
                                    <div class="text-dark py-1" style="font-size: 0.92rem;">
                                        <?= nl2br(e($q['question_text'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($q['status'] === 'valid'): ?>
                                        <span class="badge bg-success"><i class="fa-solid fa-check me-1"></i> Valid</span>
                                    <?php elseif ($q['status'] === 'duplicate'): ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-clone me-1"></i> Duplicate</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger"><i class="fa-solid fa-xmark me-1"></i> Invalid</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= e($q['reason']) ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($stats['valid'] > 0): ?>
            <div class="card-footer bg-white text-end py-3">
                <form method="POST" action="upload_questions.php" class="d-inline">
                    <input type="hidden" name="action" value="confirm_import">
                    <button type="submit" class="btn btn-success btn-lg fw-bold shadow">
                        <i class="fa-solid fa-file-import me-2"></i> Confirm & Import <?= $stats['valid'] ?> Questions
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
