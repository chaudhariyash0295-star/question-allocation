<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Manage Faculty';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $faculty_code = strtoupper(trim($_POST['faculty_code'] ?? ''));
        $department = trim($_POST['department'] ?? 'Computer Applications');
        $designation = trim($_POST['designation'] ?? 'Assistant Professor');
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email) || empty($username) || empty($password) || empty($faculty_code)) {
            set_flash('error', 'Please fill in all required fields.');
        } else {
            try {
                $pdo->beginTransaction();

                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $uStmt = $pdo->prepare("INSERT INTO users (name, email, username, password, role, status) VALUES (?, ?, ?, ?, 'faculty', ?)");
                $uStmt->execute([$name, $email, $username, $hashed, $status]);
                $userId = $pdo->lastInsertId();

                $fStmt = $pdo->prepare("INSERT INTO faculty (user_id, faculty_code, department, designation, status) VALUES (?, ?, ?, ?, ?)");
                $fStmt->execute([$userId, $faculty_code, $department, $designation, $status]);

                $pdo->commit();
                set_flash('success', "Faculty member '{$name}' registered successfully.");
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('error', 'Error creating faculty (Duplicate email, username, or code): ' . $e->getMessage());
            }
        }
        header("Location: faculty.php");
        exit;
    }

    if ($postAction === 'edit') {
        $faculty_id = (int)($_POST['faculty_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $faculty_code = strtoupper(trim($_POST['faculty_code'] ?? ''));
        $department = trim($_POST['department'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if ($faculty_id > 0 && $user_id > 0 && !empty($name) && !empty($email) && !empty($username)) {
            try {
                $pdo->beginTransaction();

                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $uStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, username = ?, password = ?, status = ? WHERE user_id = ?");
                    $uStmt->execute([$name, $email, $username, $hashed, $status, $user_id]);
                } else {
                    $uStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, username = ?, status = ? WHERE user_id = ?");
                    $uStmt->execute([$name, $email, $username, $status, $user_id]);
                }

                $fStmt = $pdo->prepare("UPDATE faculty SET faculty_code = ?, department = ?, designation = ?, status = ? WHERE faculty_id = ?");
                $fStmt->execute([$faculty_code, $department, $designation, $status, $faculty_id]);

                $pdo->commit();
                set_flash('success', "Faculty record updated successfully.");
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('error', 'Error updating faculty: ' . $e->getMessage());
            }
        }
        header("Location: faculty.php");
        exit;
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM faculty WHERE faculty_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$row['user_id']]);
            set_flash('success', "Faculty record and login account deleted.");
        }
    } catch (PDOException $e) {
        set_flash('error', "Cannot delete faculty because they are assigned to subjects or have conducted exams.");
    }
    header("Location: faculty.php");
    exit;
}

if ($action === 'toggle' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE faculty SET status = IF(status='active', 'inactive', 'active') WHERE faculty_id = ?");
    $stmt->execute([$id]);
    set_flash('success', "Faculty status toggled.");
    header("Location: faculty.php");
    exit;
}

// Fetch all faculty with assignment count
$facultyList = $pdo->query("
    SELECT f.*, u.name, u.email, u.username,
           (SELECT COUNT(*) FROM faculty_subjects fs WHERE fs.faculty_id = f.faculty_id) as assigned_subjects,
           (SELECT COUNT(*) FROM exams ex WHERE ex.faculty_id = f.faculty_id) as conducted_exams
    FROM faculty f
    JOIN users u ON f.user_id = u.user_id
    ORDER BY f.faculty_id ASC
")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Manage Academic Faculty</h3>
        <p class="text-muted small mb-0">Practical examination coordinators, professors, and subject teachers.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacultyModal">
        <i class="fa-solid fa-user-plus me-1"></i> Add Faculty
    </button>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-chalkboard-user me-2 text-primary"></i> Registered Faculty (<?= count($facultyList) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Faculty Code</th>
                        <th>Name & Email</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Assigned Subjects</th>
                        <th>Exams Conducted</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($facultyList)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No faculty members found. Click "Add Faculty" to register one.</td></tr>
                    <?php else: ?>
                        <?php foreach ($facultyList as $fac): ?>
                            <tr>
                                <td><code><?= e($fac['faculty_code']) ?></code></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($fac['name']) ?></div>
                                    <small class="text-muted"><?= e($fac['email']) ?> | @<?= e($fac['username']) ?></small>
                                </td>
                                <td><?= e($fac['department']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($fac['designation']) ?></span></td>
                                <td>
                                    <a href="assignments.php?faculty_id=<?= $fac['faculty_id'] ?>" class="badge bg-info text-dark text-decoration-none">
                                        <?= $fac['assigned_subjects'] ?> Subjects <i class="fa-solid fa-arrow-up-right-from-square ms-1"></i>
                                    </a>
                                </td>
                                <td><span class="badge bg-secondary"><?= $fac['conducted_exams'] ?></span></td>
                                <td><?= status_badge($fac['status']) ?></td>
                                <td class="text-end">
                                    <a href="faculty.php?action=toggle&id=<?= $fac['faculty_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Toggle Status">
                                        <i class="fa-solid fa-power-off"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editFacModal<?= $fac['faculty_id'] ?>" title="Edit Faculty">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="faculty.php?action=delete&id=<?= $fac['faculty_id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete <?= e($fac['name']) ?>?" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                    <!-- Edit Faculty Modal -->
                                    <div class="modal fade text-start" id="editFacModal<?= $fac['faculty_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="faculty.php">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="faculty_id" value="<?= $fac['faculty_id'] ?>">
                                                    <input type="hidden" name="user_id" value="<?= $fac['user_id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Faculty Information</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Faculty Name</label>
                                                                <input type="text" name="name" class="form-control" value="<?= e($fac['name']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Email Address</label>
                                                                <input type="email" name="email" class="form-control" value="<?= e($fac['email']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Username</label>
                                                                <input type="text" name="username" class="form-control" value="<?= e($fac['username']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Reset Password (leave empty to keep)</label>
                                                                <input type="password" name="password" class="form-control" placeholder="••••••••">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Faculty ID Code</label>
                                                                <input type="text" name="faculty_code" class="form-control" value="<?= e($fac['faculty_code']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Department</label>
                                                                <input type="text" name="department" class="form-control" value="<?= e($fac['department']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Designation</label>
                                                                <input type="text" name="designation" class="form-control" value="<?= e($fac['designation']) ?>" required>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label class="form-label fw-semibold">Status</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="active" <?= $fac['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                    <option value="inactive" <?= $fac['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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

<!-- Add Faculty Modal -->
<div class="modal fade" id="addFacultyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="faculty.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Register New Faculty</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Faculty Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Dr. Rajesh Sharma" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="e.g. faculty@college.edu" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="e.g. prof_rajesh" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Initial Password</label>
                            <input type="password" name="password" class="form-control" value="Faculty@123" required>
                            <small class="text-muted">Default is <code>Faculty@123</code></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Faculty ID Code</label>
                            <input type="text" name="faculty_code" class="form-control" placeholder="e.g. FAC-BCA-02" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Department</label>
                            <input type="text" name="department" class="form-control" value="Computer Applications" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Designation</label>
                            <input type="text" name="designation" class="form-control" value="Assistant Professor" required>
                        </div>
                        <div class="col-md-12">
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
                    <button type="submit" class="btn btn-primary">Register Faculty</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
