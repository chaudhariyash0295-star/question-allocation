<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Assign Subjects to Faculty';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Dropdown lists
$facultyList = $pdo->query("SELECT f.*, u.name, u.email FROM faculty f JOIN users u ON f.user_id = u.user_id WHERE f.status = 'active' ORDER BY u.name ASC")->fetchAll();
$subjects = $pdo->query("SELECT s.*, sem.semester_name FROM subjects s JOIN semesters sem ON s.semester_id = sem.semester_id WHERE s.status = 'active' ORDER BY s.subject_code ASC")->fetchAll();
$semesters = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY semester_id ASC")->fetchAll();
$divisions = $pdo->query("SELECT * FROM divisions WHERE status = 'active' ORDER BY division_name ASC")->fetchAll();
$batches = $pdo->query("SELECT b.*, sem.semester_name, d.division_name FROM batches b JOIN semesters sem ON b.semester_id = sem.semester_id JOIN divisions d ON b.division_id = d.division_id WHERE b.status = 'active' ORDER BY b.batch_name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'assign') {
        $faculty_id = (int)($_POST['faculty_id'] ?? 0);
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $division_id = (int)($_POST['division_id'] ?? 0);
        $batch_id = !empty($_POST['batch_id']) ? (int)$_POST['batch_id'] : null;

        if (!$faculty_id || !$subject_id || !$semester_id || !$division_id) {
            set_flash('error', 'Please select Faculty, Subject, Semester, and Division.');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO faculty_subjects (faculty_id, subject_id, semester_id, division_id, batch_id) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$faculty_id, $subject_id, $semester_id, $division_id, $batch_id]);
                set_flash('success', "Subject assignment recorded successfully.");
            } catch (PDOException $e) {
                set_flash('error', 'Error assigning subject: ' . $e->getMessage());
            }
        }
        header("Location: assignments.php");
        exit;
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM faculty_subjects WHERE assignment_id = ?");
        $stmt->execute([$id]);
        set_flash('success', "Assignment removed successfully.");
    } catch (PDOException $e) {
        set_flash('error', 'Error deleting assignment: ' . $e->getMessage());
    }
    header("Location: assignments.php");
    exit;
}

// Fetch all assignments with names
$assignments = $pdo->query("
    SELECT fs.*, u.name as faculty_name, f.faculty_code, s.subject_code, s.subject_name,
           sem.semester_name, d.division_name, b.batch_name
    FROM faculty_subjects fs
    JOIN faculty f ON fs.faculty_id = f.faculty_id
    JOIN users u ON f.user_id = u.user_id
    JOIN subjects s ON fs.subject_id = s.subject_id
    JOIN semesters sem ON fs.semester_id = sem.semester_id
    JOIN divisions d ON fs.division_id = d.division_id
    LEFT JOIN batches b ON fs.batch_id = b.batch_id
    ORDER BY s.subject_code ASC, u.name ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Assign Subjects to Faculty</h3>
        <p class="text-muted small mb-0">Map subjects to faculty professors and lab batches for exam authority and question uploading.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal">
        <i class="fa-solid fa-link me-1"></i> New Subject Assignment
    </button>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-graduation-cap me-2 text-primary"></i> Active Faculty Assignments (<?= count($assignments) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Assigned Faculty</th>
                        <th>Semester</th>
                        <th>Division</th>
                        <th>Batch</th>
                        <th>Assigned Date</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No subject assignments created yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($assignments as $a): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($a['subject_name']) ?></div>
                                    <code><?= e($a['subject_code']) ?></code>
                                </td>
                                <td>
                                    <div class="fw-semibold text-primary"><?= e($a['faculty_name']) ?></div>
                                    <small class="text-muted"><?= e($a['faculty_code']) ?></small>
                                </td>
                                <td><span class="badge bg-secondary"><?= e($a['semester_name']) ?></span></td>
                                <td><span class="badge bg-light text-dark border">Div <?= e($a['division_name']) ?></span></td>
                                <td>
                                    <?php if ($a['batch_name']): ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25"><?= e($a['batch_name']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">All Batches</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= format_date($a['assigned_at']) ?></small></td>
                                <td class="text-end">
                                    <a href="assignments.php?action=delete&id=<?= $a['assignment_id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Remove this faculty subject assignment?" title="Remove Assignment">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="assignments.php">
                <input type="hidden" name="action" value="assign">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Assign Subject to Faculty</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Faculty</label>
                            <select name="faculty_id" class="form-select" required>
                                <option value="">Choose Faculty Member</option>
                                <?php foreach ($facultyList as $fac): ?>
                                    <option value="<?= $fac['faculty_id'] ?>">
                                        <?= e($fac['name']) ?> (<?= e($fac['faculty_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Subject</label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Choose Subject</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= $s['subject_id'] ?>">
                                        <?= e($s['subject_code']) ?> - <?= e($s['subject_name']) ?> (<?= e($s['semester_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Semester</label>
                            <select name="semester_id" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php foreach ($semesters as $sem): ?>
                                    <option value="<?= $sem['semester_id'] ?>"><?= e($sem['semester_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Division</label>
                            <select name="division_id" class="form-select" required>
                                <option value="">Select Division</option>
                                <?php foreach ($divisions as $d): ?>
                                    <option value="<?= $d['division_id'] ?>">Division <?= e($d['division_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Batch (Optional)</label>
                            <select name="batch_id" class="form-select">
                                <option value="">All Batches in Division</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?= $b['batch_id'] ?>">
                                        <?= e($b['batch_name']) ?> (<?= e($b['semester_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
