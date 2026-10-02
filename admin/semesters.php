<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Manage Semesters';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Handle POST actions (Add, Edit, Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $name = trim($_POST['semester_name'] ?? '');
        $code = strtoupper(trim($_POST['semester_code'] ?? ''));
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($code)) {
            set_flash('error', 'Semester name and code are required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO semesters (semester_name, semester_code, status) VALUES (?, ?, ?)");
                $stmt->execute([$name, $code, $status]);
                set_flash('success', "Semester '{$name}' created successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Duplicate semester code or database error: ' . $e->getMessage());
            }
        }
        header("Location: semesters.php");
        exit;
    }

    if ($postAction === 'edit') {
        $semId = (int)($_POST['semester_id'] ?? 0);
        $name = trim($_POST['semester_name'] ?? '');
        $code = strtoupper(trim($_POST['semester_code'] ?? ''));
        $status = $_POST['status'] ?? 'active';

        if ($semId > 0 && !empty($name) && !empty($code)) {
            try {
                $stmt = $pdo->prepare("UPDATE semesters SET semester_name = ?, semester_code = ?, status = ? WHERE semester_id = ?");
                $stmt->execute([$name, $code, $status, $semId]);
                set_flash('success', "Semester updated successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Error updating semester: ' . $e->getMessage());
            }
        }
        header("Location: semesters.php");
        exit;
    }
}

// Handle GET actions (Delete, Toggle)
if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM semesters WHERE semester_id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Semester deleted successfully.");
    } catch (PDOException $e) {
        set_flash('error', "Cannot delete semester because it is linked to active batches, subjects, or exams.");
    }
    header("Location: semesters.php");
    exit;
}

if ($action === 'toggle' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE semesters SET status = IF(status='active', 'inactive', 'active') WHERE semester_id = ?");
    $stmt->execute([$id]);
    set_flash('success', "Semester status toggled.");
    header("Location: semesters.php");
    exit;
}

$semesters = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM batches b WHERE b.semester_id = s.semester_id) as batch_count,
           (SELECT COUNT(*) FROM subjects sub WHERE sub.semester_id = s.semester_id) as subject_count
    FROM semesters s 
    ORDER BY s.semester_id ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Manage Academic Semesters</h3>
        <p class="text-muted small mb-0">Configure semesters for curriculum, student allocation, and batch groupings.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSemesterModal">
        <i class="fa-solid fa-plus me-1"></i> Add Semester
    </button>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i> All Semesters (<?= count($semesters) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">#</th>
                        <th>Semester Name</th>
                        <th>Code</th>
                        <th>Linked Batches</th>
                        <th>Linked Subjects</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($semesters)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No semesters defined yet. Click "Add Semester" to create one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($semesters as $idx => $sem): ?>
                            <tr>
                                <td><span class="badge bg-light text-muted border"><?= $idx + 1 ?></span></td>
                                <td class="fw-bold text-dark"><?= e($sem['semester_name']) ?></td>
                                <td><code><?= e($sem['semester_code']) ?></code></td>
                                <td><span class="badge bg-secondary"><?= $sem['batch_count'] ?> Batches</span></td>
                                <td><span class="badge bg-info text-dark"><?= $sem['subject_count'] ?> Subjects</span></td>
                                <td><?= status_badge($sem['status']) ?></td>
                                <td class="text-end">
                                    <a href="semesters.php?action=toggle&id=<?= $sem['semester_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Toggle Active/Inactive">
                                        <i class="fa-solid fa-power-off"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $sem['semester_id'] ?>" title="Edit Semester">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="semesters.php?action=delete&id=<?= $sem['semester_id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete <?= e($sem['semester_name']) ?>? All associated batches and subjects will be affected." title="Delete Semester">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                    <!-- Edit Modal -->
                                    <div class="modal fade text-start" id="editModal<?= $sem['semester_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="semesters.php">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="semester_id" value="<?= $sem['semester_id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Semester</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Semester Name</label>
                                                            <input type="text" name="semester_name" class="form-control" value="<?= e($sem['semester_name']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Semester Code</label>
                                                            <input type="text" name="semester_code" class="form-control" value="<?= e($sem['semester_code']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Status</label>
                                                            <select name="status" class="form-select">
                                                                <option value="active" <?= $sem['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                <option value="inactive" <?= $sem['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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

<!-- Add Semester Modal -->
<div class="modal fade" id="addSemesterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="semesters.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add New Semester</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Semester Name</label>
                        <input type="text" name="semester_name" class="form-control" placeholder="e.g. Semester 4" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Semester Code</label>
                        <input type="text" name="semester_code" class="form-control" placeholder="e.g. SEM-4" required>
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
                    <button type="submit" class="btn btn-primary">Create Semester</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
