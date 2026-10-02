<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Manage Divisions';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $name = strtoupper(trim($_POST['division_name'] ?? ''));
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) {
            set_flash('error', 'Division name is required.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO divisions (division_name, status) VALUES (?, ?)");
                $stmt->execute([$name, $status]);
                set_flash('success', "Division '{$name}' created successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Division name already exists or database error: ' . $e->getMessage());
            }
        }
        header("Location: divisions.php");
        exit;
    }

    if ($postAction === 'edit') {
        $divId = (int)($_POST['division_id'] ?? 0);
        $name = strtoupper(trim($_POST['division_name'] ?? ''));
        $status = $_POST['status'] ?? 'active';

        if ($divId > 0 && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE divisions SET division_name = ?, status = ? WHERE division_id = ?");
                $stmt->execute([$name, $status, $divId]);
                set_flash('success', "Division updated successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Error updating division: ' . $e->getMessage());
            }
        }
        header("Location: divisions.php");
        exit;
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM divisions WHERE division_id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Division deleted successfully.");
    } catch (PDOException $e) {
        set_flash('error', "Cannot delete division because batches or students are linked to it.");
    }
    header("Location: divisions.php");
    exit;
}

if ($action === 'toggle' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE divisions SET status = IF(status='active', 'inactive', 'active') WHERE division_id = ?");
    $stmt->execute([$id]);
    set_flash('success', "Division status toggled.");
    header("Location: divisions.php");
    exit;
}

$divisions = $pdo->query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM batches b WHERE b.division_id = d.division_id) as batch_count,
           (SELECT COUNT(*) FROM students s WHERE s.division_id = d.division_id) as student_count
    FROM divisions d 
    ORDER BY d.division_name ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Manage Class Divisions</h3>
        <p class="text-muted small mb-0">Define class sections (e.g. A, B, C) for lab batches and student distribution.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDivisionModal">
        <i class="fa-solid fa-plus me-1"></i> Add Division
    </button>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-sitemap me-2 text-primary"></i> Class Divisions (<?= count($divisions) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 80px;">#</th>
                        <th>Division Name</th>
                        <th>Associated Batches</th>
                        <th>Total Students</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($divisions)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No divisions found. Click "Add Division" to create one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($divisions as $idx => $div): ?>
                            <tr>
                                <td><span class="badge bg-light text-muted border"><?= $idx + 1 ?></span></td>
                                <td class="fw-bold fs-6 text-dark">Division <?= e($div['division_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= $div['batch_count'] ?> Batches</span></td>
                                <td><span class="badge bg-info text-dark"><?= $div['student_count'] ?> Students</span></td>
                                <td><?= status_badge($div['status']) ?></td>
                                <td class="text-end">
                                    <a href="divisions.php?action=toggle&id=<?= $div['division_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Toggle Status">
                                        <i class="fa-solid fa-power-off"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editDivModal<?= $div['division_id'] ?>" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="divisions.php?action=delete&id=<?= $div['division_id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete Division <?= e($div['division_name']) ?>?" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                    <!-- Edit Modal -->
                                    <div class="modal fade text-start" id="editDivModal<?= $div['division_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="divisions.php">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="division_id" value="<?= $div['division_id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Division</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Division Name</label>
                                                            <input type="text" name="division_name" class="form-control" value="<?= e($div['division_name']) ?>" required>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Status</label>
                                                            <select name="status" class="form-select">
                                                                <option value="active" <?= $div['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                <option value="inactive" <?= $div['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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

<!-- Add Division Modal -->
<div class="modal fade" id="addDivisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="divisions.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add New Division</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Division Name</label>
                        <input type="text" name="division_name" class="form-control" placeholder="e.g. A or B" required>
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
                    <button type="submit" class="btn btn-primary">Create Division</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
