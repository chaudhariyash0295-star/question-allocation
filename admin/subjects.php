<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Manage Subjects';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

$semesters = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY semester_id ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $code = strtoupper(trim($_POST['subject_code'] ?? ''));
        $name = trim($_POST['subject_name'] ?? '');
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (empty($code) || empty($name) || !$semester_id) {
            set_flash('error', 'Please fill in subject code, name, and semester.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_code, subject_name, semester_id, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$code, $name, $semester_id, $status]);
                set_flash('success', "Subject '{$code} - {$name}' created successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Subject code already exists or database error: ' . $e->getMessage());
            }
        }
        header("Location: subjects.php");
        exit;
    }

    if ($postAction === 'edit') {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $code = strtoupper(trim($_POST['subject_code'] ?? ''));
        $name = trim($_POST['subject_name'] ?? '');
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if ($subject_id > 0 && !empty($code) && !empty($name) && $semester_id) {
            try {
                $stmt = $pdo->prepare("UPDATE subjects SET subject_code = ?, subject_name = ?, semester_id = ?, status = ? WHERE subject_id = ?");
                $stmt->execute([$code, $name, $semester_id, $status, $subject_id]);
                set_flash('success', "Subject updated successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Error updating subject: ' . $e->getMessage());
            }
        }
        header("Location: subjects.php");
        exit;
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Subject deleted successfully.");
    } catch (PDOException $e) {
        set_flash('error', "Cannot delete subject because exams or question bank entries exist.");
    }
    header("Location: subjects.php");
    exit;
}

if ($action === 'toggle' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE subjects SET status = IF(status='active', 'inactive', 'active') WHERE subject_id = ?");
    $stmt->execute([$id]);
    set_flash('success', "Subject status toggled.");
    header("Location: subjects.php");
    exit;
}

// Fetch all subjects with question count and exams count
$subjects = $pdo->query("
    SELECT s.*, sem.semester_name,
           (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.subject_id) as question_count,
           (SELECT COUNT(*) FROM exams e WHERE e.subject_id = s.subject_id) as exam_count
    FROM subjects s
    JOIN semesters sem ON s.semester_id = sem.semester_id
    ORDER BY sem.semester_id ASC, s.subject_code ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Manage Subjects</h3>
        <p class="text-muted small mb-0">Academic practical course subjects, course codes, and question bank linkage.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
        <i class="fa-solid fa-plus me-1"></i> Add Subject
    </button>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-book-open me-2 text-primary"></i> Practical Subjects (<?= count($subjects) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Subject Code</th>
                        <th>Subject Name</th>
                        <th>Semester</th>
                        <th>Questions Bank</th>
                        <th>Exams Conducted</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No subjects found. Click "Add Subject" to create one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td><code><?= e($sub['subject_code']) ?></code></td>
                                <td class="fw-bold text-dark"><?= e($sub['subject_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= e($sub['semester_name']) ?></span></td>
                                <td>
                                    <a href="questions.php?subject_id=<?= $sub['subject_id'] ?>" class="badge bg-primary text-decoration-none">
                                        <?= $sub['question_count'] ?> Questions <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                                    </a>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= $sub['exam_count'] ?></span></td>
                                <td><?= status_badge($sub['status']) ?></td>
                                <td class="text-end">
                                    <a href="subjects.php?action=toggle&id=<?= $sub['subject_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Toggle Status">
                                        <i class="fa-solid fa-power-off"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSubModal<?= $sub['subject_id'] ?>" title="Edit Subject">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="subjects.php?action=delete&id=<?= $sub['subject_id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete <?= e($sub['subject_name']) ?>?" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                    <!-- Edit Subject Modal -->
                                    <div class="modal fade text-start" id="editSubModal<?= $sub['subject_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="subjects.php">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="subject_id" value="<?= $sub['subject_id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Subject</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Subject Code</label>
                                                            <input type="text" name="subject_code" class="form-control" value="<?= e($sub['subject_code']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Subject Name</label>
                                                            <input type="text" name="subject_name" class="form-control" value="<?= e($sub['subject_name']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Semester</label>
                                                            <select name="semester_id" class="form-select" required>
                                                                <?php foreach ($semesters as $s): ?>
                                                                    <option value="<?= $s['semester_id'] ?>" <?= $sub['semester_id'] == $s['semester_id'] ? 'selected' : '' ?>>
                                                                        <?= e($s['semester_name']) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Status</label>
                                                            <select name="status" class="form-select">
                                                                <option value="active" <?= $sub['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                <option value="inactive" <?= $sub['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                            </select>
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

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="subjects.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add New Subject</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject Code</label>
                        <input type="text" name="subject_code" class="form-control" placeholder="e.g. BCA401" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject Name</label>
                        <input type="text" name="subject_name" class="form-control" placeholder="e.g. Web Programming" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Semester</label>
                        <select name="semester_id" class="form-select" required>
                            <option value="">Select Semester</option>
                            <?php foreach ($semesters as $s): ?>
                                <option value="<?= $s['semester_id'] ?>"><?= e($s['semester_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
