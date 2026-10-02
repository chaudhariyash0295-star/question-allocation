<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Manage Batches';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Fetch Semesters and Divisions for dropdowns
$semesters = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY semester_id ASC")->fetchAll();
$divisions = $pdo->query("SELECT * FROM divisions WHERE status = 'active' ORDER BY division_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $name = trim($_POST['batch_name'] ?? '');
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $division_id = (int)($_POST['division_id'] ?? 0);
        $start_roll = (int)($_POST['start_roll_no'] ?? 0);
        $end_roll = (int)($_POST['end_roll_no'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || !$semester_id || !$division_id || $start_roll <= 0 || $end_roll < $start_roll) {
            set_flash('error', 'Please provide valid batch details. Starting roll number must be <= ending roll number.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO batches (batch_name, semester_id, division_id, start_roll_no, end_roll_no, status) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $semester_id, $division_id, $start_roll, $end_roll, $status]);
                set_flash('success', "Batch '{$name}' created with roll range {$start_roll} - {$end_roll}.");
            } catch (PDOException $e) {
                set_flash('error', 'Database error: ' . $e->getMessage());
            }
        }
        header("Location: batches.php");
        exit;
    }

    if ($postAction === 'edit') {
        $batch_id = (int)($_POST['batch_id'] ?? 0);
        $name = trim($_POST['batch_name'] ?? '');
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $division_id = (int)($_POST['division_id'] ?? 0);
        $start_roll = (int)($_POST['start_roll_no'] ?? 0);
        $end_roll = (int)($_POST['end_roll_no'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if ($batch_id > 0 && !empty($name) && $semester_id && $division_id && $start_roll <= $end_roll) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE batches 
                    SET batch_name = ?, semester_id = ?, division_id = ?, start_roll_no = ?, end_roll_no = ?, status = ? 
                    WHERE batch_id = ?
                ");
                $stmt->execute([$name, $semester_id, $division_id, $start_roll, $end_roll, $status, $batch_id]);
                set_flash('success', "Batch updated successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Error updating batch: ' . $e->getMessage());
            }
        }
        header("Location: batches.php");
        exit;
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM batches WHERE batch_id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Batch deleted successfully.");
    } catch (PDOException $e) {
        set_flash('error', "Cannot delete batch because students or exams are assigned to it.");
    }
    header("Location: batches.php");
    exit;
}

if ($action === 'toggle' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE batches SET status = IF(status='active', 'inactive', 'active') WHERE batch_id = ?");
    $stmt->execute([$id]);
    set_flash('success', "Batch status toggled.");
    header("Location: batches.php");
    exit;
}

// Fetch all batches with joined semester & division
$batches = $pdo->query("
    SELECT b.*, sem.semester_name, sem.semester_code, d.division_name,
           (SELECT COUNT(*) FROM students s WHERE s.batch_id = b.batch_id) as enrolled_students
    FROM batches b
    JOIN semesters sem ON b.semester_id = sem.semester_id
    JOIN divisions d ON b.division_id = d.division_id
    ORDER BY sem.semester_id ASC, d.division_name ASC, b.start_roll_no ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Manage Practical Lab Batches</h3>
        <p class="text-muted small mb-0">Define lab batches with roll number boundaries for intelligent student grouping during examinations.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBatchModal">
        <i class="fa-solid fa-plus me-1"></i> Create Batch
    </button>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-users-rectangle me-2 text-primary"></i> Practical Batches (<?= count($batches) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 70px;">#</th>
                        <th>Batch Name</th>
                        <th>Semester</th>
                        <th>Division</th>
                        <th>Roll Number Range</th>
                        <th>Students Enrolled</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($batches)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No batches created yet. Click "Create Batch" to define one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($batches as $idx => $batch): ?>
                            <tr>
                                <td><span class="badge bg-light text-muted border"><?= $idx + 1 ?></span></td>
                                <td class="fw-bold text-dark"><?= e($batch['batch_name']) ?></td>
                                <td><span class="badge bg-secondary"><?= e($batch['semester_name']) ?></span></td>
                                <td><span class="badge bg-light text-dark border">Div <?= e($batch['division_name']) ?></span></td>
                                <td>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                        Roll <?= $batch['start_roll_no'] ?> &rarr; <?= $batch['end_roll_no'] ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= $batch['enrolled_students'] ?></strong> 
                                    <span class="text-muted small">/ <?= ($batch['end_roll_no'] - $batch['start_roll_no'] + 1) ?> capacity</span>
                                </td>
                                <td><?= status_badge($batch['status']) ?></td>
                                <td class="text-end">
                                    <a href="batches.php?action=toggle&id=<?= $batch['batch_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Toggle Status">
                                        <i class="fa-solid fa-power-off"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editBatchModal<?= $batch['batch_id'] ?>" title="Edit">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="batches.php?action=delete&id=<?= $batch['batch_id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete <?= e($batch['batch_name']) ?>?" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                    <!-- Edit Modal -->
                                    <div class="modal fade text-start" id="editBatchModal<?= $batch['batch_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST" action="batches.php">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="batch_id" value="<?= $batch['batch_id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Batch</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Batch Name</label>
                                                            <input type="text" name="batch_name" class="form-control" value="<?= e($batch['batch_name']) ?>" required>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-6">
                                                                <label class="form-label fw-semibold">Semester</label>
                                                                <select name="semester_id" class="form-select" required>
                                                                    <?php foreach ($semesters as $s): ?>
                                                                        <option value="<?= $s['semester_id'] ?>" <?= $batch['semester_id'] == $s['semester_id'] ? 'selected' : '' ?>>
                                                                            <?= e($s['semester_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label fw-semibold">Division</label>
                                                                <select name="division_id" class="form-select" required>
                                                                    <?php foreach ($divisions as $d): ?>
                                                                        <option value="<?= $d['division_id'] ?>" <?= $batch['division_id'] == $d['division_id'] ? 'selected' : '' ?>>
                                                                            Division <?= e($d['division_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-6">
                                                                <label class="form-label fw-semibold">Starting Roll No</label>
                                                                <input type="number" name="start_roll_no" class="form-control" value="<?= $batch['start_roll_no'] ?>" required>
                                                            </div>
                                                            <div class="col-6">
                                                                <label class="form-label fw-semibold">Ending Roll No</label>
                                                                <input type="number" name="end_roll_no" class="form-control" value="<?= $batch['end_roll_no'] ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label fw-semibold">Status</label>
                                                            <select name="status" class="form-select">
                                                                <option value="active" <?= $batch['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                <option value="inactive" <?= $batch['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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

<!-- Add Batch Modal -->
<div class="modal fade" id="addBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="batches.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create New Batch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Batch Name</label>
                        <input type="text" name="batch_name" class="form-control" placeholder="e.g. Batch A" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Semester</label>
                            <select name="semester_id" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?= $s['semester_id'] ?>"><?= e($s['semester_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Division</label>
                            <select name="division_id" class="form-select" required>
                                <option value="">Select Division</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= $d['division_id'] ?>">Division <?= e($d['division_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Starting Roll Number</label>
                            <input type="number" name="start_roll_no" class="form-control" placeholder="e.g. 101" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Ending Roll Number</label>
                            <input type="number" name="end_roll_no" class="form-control" placeholder="e.g. 130" required>
                        </div>
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
                    <button type="submit" class="btn btn-primary">Create Batch</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
