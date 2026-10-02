<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Manage Question Bank';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Filters
$filterSubject = (int)($_GET['subject_id'] ?? 0);
$filterSem = (int)($_GET['semester_id'] ?? 0);
$search = trim($_GET['search'] ?? '');

$subjects = $pdo->query("SELECT s.*, sem.semester_name FROM subjects s JOIN semesters sem ON s.semester_id = sem.semester_id ORDER BY s.subject_name ASC")->fetchAll();
$semesters = $pdo->query("SELECT * FROM semesters ORDER BY semester_id ASC")->fetchAll();
$batches = $pdo->query("SELECT * FROM batches ORDER BY batch_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $batch_id = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
        $q_no = (int)($_POST['question_number'] ?? 0);
        $q_text = trim($_POST['question_text'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (!$subject_id || !$semester_id || empty($q_text) || $q_no <= 0) {
            set_flash('error', 'Please provide subject, semester, question number, and question text.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO questions (subject_id, semester_id, batch_id, question_number, question_text, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$subject_id, $semester_id, $batch_id, $q_no, $q_text, $status, $_SESSION['user_id']]);
                set_flash('success', "Question #{$q_no} added to the question bank.");
            } catch (PDOException $e) {
                set_flash('error', 'Error adding question: ' . $e->getMessage());
            }
        }
        header("Location: questions.php?subject_id=" . $subject_id);
        exit;
    }

    if ($postAction === 'edit') {
        $q_id = (int)($_POST['question_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $batch_id = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;
        $q_no = (int)($_POST['question_number'] ?? 0);
        $q_text = trim($_POST['question_text'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($q_id > 0 && !empty($q_text) && $q_no > 0) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE questions 
                    SET subject_id = ?, semester_id = ?, batch_id = ?, question_number = ?, question_text = ?, status = ?
                    WHERE question_id = ?
                ");
                $stmt->execute([$subject_id, $semester_id, $batch_id, $q_no, $q_text, $status, $q_id]);
                set_flash('success', "Question #{$q_no} updated successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Error updating question: ' . $e->getMessage());
            }
        }
        header("Location: questions.php?subject_id=" . $filterSubject);
        exit;
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM questions WHERE question_id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Question deleted from bank.");
    } catch (PDOException $e) {
        set_flash('error', 'Cannot delete question because it has already been allocated in an examination.');
    }
    header("Location: questions.php?subject_id=" . $filterSubject);
    exit;
}

if ($action === 'toggle' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE questions SET status = IF(status='active', 'inactive', 'active') WHERE question_id = ?");
    $stmt->execute([$id]);
    set_flash('success', "Question status toggled.");
    header("Location: questions.php?subject_id=" . $filterSubject);
    exit;
}

// Build query
$where = ["1=1"];
$params = [];

if ($filterSubject > 0) {
    $where[] = "q.subject_id = ?";
    $params[] = $filterSubject;
}
if ($filterSem > 0) {
    $where[] = "q.semester_id = ?";
    $params[] = $filterSem;
}
if (!empty($search)) {
    $where[] = "q.question_text LIKE ?";
    $params[] = "%{$search}%";
}

$whereClause = implode(" AND ", $where);
$questionsStmt = $pdo->prepare("
    SELECT q.*, s.subject_name, s.subject_code, sem.semester_name, b.batch_name, u.name as creator_name
    FROM questions q
    JOIN subjects s ON q.subject_id = s.subject_id
    JOIN semesters sem ON q.semester_id = sem.semester_id
    LEFT JOIN batches b ON q.batch_id = b.batch_id
    JOIN users u ON q.created_by = u.user_id
    WHERE {$whereClause}
    ORDER BY s.subject_code ASC, q.question_number ASC
");
$questionsStmt->execute($params);
$questions = $questionsStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Central Question Bank</h3>
        <p class="text-muted small mb-0">Practical questions library available for smart chit distribution during examinations.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
            <i class="fa-solid fa-plus me-1"></i> Add Question
        </button>
    </div>
</div>

<!-- Filters Bar -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="questions.php" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Filter by Subject</label>
                <select name="subject_id" class="form-select">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['subject_id'] ?>" <?= $filterSubject == $s['subject_id'] ? 'selected' : '' ?>>
                            <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Semester</label>
                <select name="semester_id" class="form-select">
                    <option value="">All Semesters</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= $sem['semester_id'] ?>" <?= $filterSem == $sem['semester_id'] ? 'selected' : '' ?>>
                            <?= e($sem['semester_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Search Question Text</label>
                <input type="text" name="search" class="form-control" placeholder="Search keywords..." value="<?= e($search) ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                <a href="questions.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Questions List -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-file-circle-question me-2 text-primary"></i> Practical Examination Questions (<?= count($questions) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">Q#</th>
                        <th>Subject & Semester</th>
                        <th>Question Problem Statement</th>
                        <th>Batch Scope</th>
                        <th>Author</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($questions)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No questions found matching criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($questions as $q): ?>
                            <tr>
                                <td><span class="badge bg-primary fs-6 px-2">#<?= $q['question_number'] ?></span></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($q['subject_name']) ?></div>
                                    <small class="text-muted"><code><?= e($q['subject_code']) ?></code> &bull; <?= e($q['semester_name']) ?></small>
                                </td>
                                <td>
                                    <div class="text-dark py-1" style="max-width: 480px; font-size: 0.92rem; line-height: 1.5;">
                                        <?= nl2br(e($q['question_text'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($q['batch_name']): ?>
                                        <span class="badge bg-light text-dark border"><?= e($q['batch_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Subject Wide</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= e($q['creator_name']) ?></small>
                                </td>
                                <td><?= status_badge($q['status']) ?></td>
                                <td class="text-end">
                                    <a href="questions.php?action=toggle&id=<?= $q['question_id'] ?>&subject_id=<?= $filterSubject ?>" class="btn btn-sm btn-outline-secondary" title="Toggle Status">
                                        <i class="fa-solid fa-power-off"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editQModal<?= $q['question_id'] ?>" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="questions.php?action=delete&id=<?= $q['question_id'] ?>&subject_id=<?= $filterSubject ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete Question #<?= $q['question_number'] ?>?" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                    <!-- Edit Question Modal -->
                                    <div class="modal fade text-start" id="editQModal<?= $q['question_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="questions.php">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="question_id" value="<?= $q['question_id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Question #<?= $q['question_number'] ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Subject</label>
                                                                <select name="subject_id" class="form-select" required>
                                                                    <?php foreach ($subjects as $s): ?>
                                                                        <option value="<?= $s['subject_id'] ?>" <?= $q['subject_id'] == $s['subject_id'] ? 'selected' : '' ?>>
                                                                            <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold">Semester</label>
                                                                <select name="semester_id" class="form-select" required>
                                                                    <?php foreach ($semesters as $sem): ?>
                                                                        <option value="<?= $sem['semester_id'] ?>" <?= $q['semester_id'] == $sem['semester_id'] ? 'selected' : '' ?>>
                                                                            <?= e($sem['semester_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label fw-semibold">Question Number</label>
                                                                <input type="number" name="question_number" class="form-control" value="<?= $q['question_number'] ?>" required>
                                                            </div>
                                                            <div class="col-12">
                                                                <label class="form-label fw-semibold">Question Text / Practical Problem Statement</label>
                                                                <textarea name="question_text" class="form-control" rows="4" required><?= e($q['question_text']) ?></textarea>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Batch Scope (Optional)</label>
                                                                <select name="batch_id" class="form-select">
                                                                    <option value="">Subject-Wide (All Batches)</option>
                                                                    <?php foreach ($batches as $b): ?>
                                                                        <option value="<?= $b['batch_id'] ?>" <?= $q['batch_id'] == $b['batch_id'] ? 'selected' : '' ?>>
                                                                            <?= e($b['batch_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Status</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="active" <?= $q['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                    <option value="inactive" <?= $q['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
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

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="questions.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add Question to Question Bank</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['subject_id'] ?>" <?= $filterSubject == $s['subject_id'] ? 'selected' : '' ?>>
                                        <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Semester</label>
                            <select name="semester_id" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= $sem['semester_id'] ?>" <?= $filterSem == $sem['semester_id'] ? 'selected' : '' ?>>
                                        <?= e($sem['semester_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Question Number</label>
                            <input type="number" name="question_number" class="form-control" placeholder="e.g. 1" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Question Text / Practical Problem Statement</label>
                            <textarea name="question_text" class="form-control" rows="4" placeholder="Enter complete problem statement or lab task instructions..." required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Batch Scope (Optional)</label>
                            <select name="batch_id" class="form-select">
                                <option value="">Subject-Wide (All Batches)</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?= $b['batch_id'] ?>"><?= e($b['batch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
