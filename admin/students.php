<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['admin']);
$pageTitle = 'Manage Students';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Filters
$search = trim($_GET['search'] ?? '');
$filterSem = (int)($_GET['semester_id'] ?? 0);
$filterBatch = (int)($_GET['batch_id'] ?? 0);

// Dropdown options
$semesters = $pdo->query("SELECT * FROM semesters WHERE status = 'active' ORDER BY semester_id ASC")->fetchAll();
$divisions = $pdo->query("SELECT * FROM divisions WHERE status = 'active' ORDER BY division_name ASC")->fetchAll();
$batches = $pdo->query("SELECT b.*, sem.semester_name, d.division_name FROM batches b JOIN semesters sem ON b.semester_id = sem.semester_id JOIN divisions d ON b.division_id = d.division_id ORDER BY b.batch_id ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $enrollment_no = trim($_POST['enrollment_no'] ?? '');
        $roll_no = (int)($_POST['roll_no'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $division_id = (int)($_POST['division_id'] ?? 0);
        $batch_id = (int)($_POST['batch_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email) || empty($username) || empty($password) || empty($enrollment_no) || !$roll_no || !$batch_id) {
            set_flash('error', 'Please fill in all required fields including roll number, batch, and credentials.');
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Create in users table
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $uStmt = $pdo->prepare("INSERT INTO users (name, email, username, password, role, status) VALUES (?, ?, ?, ?, 'student', ?)");
                $uStmt->execute([$name, $email, $username, $hashed, $status]);
                $userId = $pdo->lastInsertId();

                // 2. Create in students table
                $sStmt = $pdo->prepare("
                    INSERT INTO students (user_id, enrollment_no, roll_no, semester_id, division_id, batch_id, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $sStmt->execute([$userId, $enrollment_no, $roll_no, $semester_id, $division_id, $batch_id, $status]);

                $pdo->commit();
                set_flash('success', "Student '{$name}' (Roll #{$roll_no}) enrolled successfully.");
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('error', 'Error creating student (Duplicate email, username, roll no, or enrollment): ' . $e->getMessage());
            }
        }
        header("Location: students.php");
        exit;
    }

    if ($postAction === 'edit') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $enrollment_no = trim($_POST['enrollment_no'] ?? '');
        $roll_no = (int)($_POST['roll_no'] ?? 0);
        $semester_id = (int)($_POST['semester_id'] ?? 0);
        $division_id = (int)($_POST['division_id'] ?? 0);
        $batch_id = (int)($_POST['batch_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if ($student_id > 0 && $user_id > 0 && !empty($name) && !empty($email) && !empty($username)) {
            try {
                $pdo->beginTransaction();

                // Update users table
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $uStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, username = ?, password = ?, status = ? WHERE user_id = ?");
                    $uStmt->execute([$name, $email, $username, $hashed, $status, $user_id]);
                } else {
                    $uStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, username = ?, status = ? WHERE user_id = ?");
                    $uStmt->execute([$name, $email, $username, $status, $user_id]);
                }

                // Update students table
                $sStmt = $pdo->prepare("
                    UPDATE students 
                    SET enrollment_no = ?, roll_no = ?, semester_id = ?, division_id = ?, batch_id = ?, status = ? 
                    WHERE student_id = ?
                ");
                $sStmt->execute([$enrollment_no, $roll_no, $semester_id, $division_id, $batch_id, $status, $student_id]);

                $pdo->commit();
                set_flash('success', "Student record updated successfully.");
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                set_flash('error', 'Error updating student record: ' . $e->getMessage());
            }
        }
        header("Location: students.php");
        exit;
    }
}

if ($action === 'delete' && $id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$row['user_id']]);
            set_flash('success', "Student record and account deleted.");
        }
    } catch (PDOException $e) {
        set_flash('error', "Cannot delete student because allocation records or exam history exist.");
    }
    header("Location: students.php");
    exit;
}

if ($action === 'toggle' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE students SET status = IF(status='active', 'inactive', 'active') WHERE student_id = ?");
    $stmt->execute([$id]);
    set_flash('success', "Student status toggled.");
    header("Location: students.php");
    exit;
}

// Build Query with Filters
$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(u.name LIKE ? OR u.email LIKE ? OR s.roll_no LIKE ? OR s.enrollment_no LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term]);
}
if ($filterSem > 0) {
    $where[] = "s.semester_id = ?";
    $params[] = $filterSem;
}
if ($filterBatch > 0) {
    $where[] = "s.batch_id = ?";
    $params[] = $filterBatch;
}

$whereClause = implode(" AND ", $where);
$studentsStmt = $pdo->prepare("
    SELECT s.*, u.name, u.email, u.username, sem.semester_name, d.division_name, b.batch_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    JOIN semesters sem ON s.semester_id = sem.semester_id
    JOIN divisions d ON s.division_id = d.division_id
    JOIN batches b ON s.batch_id = b.batch_id
    WHERE {$whereClause}
    ORDER BY sem.semester_id ASC, b.batch_id ASC, s.roll_no ASC
");
$studentsStmt->execute($params);
$students = $studentsStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">Manage Students</h3>
        <p class="text-muted small mb-0">Enrolled examinees, roll numbers, credentials, and laboratory batch associations.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
        <i class="fa-solid fa-user-plus me-1"></i> Add Student
    </button>
</div>

<!-- Filters Bar -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="students.php" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-muted">Search Name, Email, or Roll No</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="e.g. 101 or Rahul" value="<?= e($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Semester</label>
                <select name="semester_id" class="form-select">
                    <option value="">All Semesters</option>
                    <?php foreach ($semesters as $s): ?>
                        <option value="<?= $s['semester_id'] ?>" <?= $filterSem == $s['semester_id'] ? 'selected' : '' ?>>
                            <?= e($s['semester_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Filter by Batch</label>
                <select name="batch_id" class="form-select">
                    <option value="">All Batches</option>
                    <?php foreach ($batches as $b): ?>
                        <option value="<?= $b['batch_id'] ?>" <?= $filterBatch == $b['batch_id'] ? 'selected' : '' ?>>
                            <?= e($b['batch_name']) ?> (<?= e($b['semester_name']) ?> - Div <?= e($b['division_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                <a href="students.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- Students List -->
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold text-dark"><i class="fa-solid fa-user-graduate me-2 text-primary"></i> Registered Examinees (<?= count($students) ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Roll No</th>
                        <th>Student Name & Email</th>
                        <th>Enrollment No</th>
                        <th>Semester & Div</th>
                        <th>Batch</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No student records found matching the criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $stu): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary fs-6 px-2 py-1">
                                        <?= e($stu['roll_no']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($stu['name']) ?></div>
                                    <small class="text-muted"><?= e($stu['email']) ?></small>
                                </td>
                                <td><code><?= e($stu['enrollment_no']) ?></code></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e($stu['semester_name']) ?></span>
                                    <span class="badge bg-light text-dark border">Div <?= e($stu['division_name']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= e($stu['batch_name']) ?></span>
                                </td>
                                <td><span class="text-muted small">@<?= e($stu['username']) ?></span></td>
                                <td><?= status_badge($stu['status']) ?></td>
                                <td class="text-end">
                                    <a href="students.php?action=toggle&id=<?= $stu['student_id'] ?>" class="btn btn-sm btn-outline-secondary" title="Toggle Status">
                                        <i class="fa-solid fa-power-off"></i>
                                    </a>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editStudentModal<?= $stu['student_id'] ?>" title="Edit Student">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <a href="students.php?action=delete&id=<?= $stu['student_id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm="Are you sure you want to delete student <?= e($stu['name']) ?>?" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>

                                    <!-- Edit Student Modal -->
                                    <div class="modal fade text-start" id="editStudentModal<?= $stu['student_id'] ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <form method="POST" action="students.php">
                                                    <input type="hidden" name="action" value="edit">
                                                    <input type="hidden" name="student_id" value="<?= $stu['student_id'] ?>">
                                                    <input type="hidden" name="user_id" value="<?= $stu['user_id'] ?>">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title fw-bold">Edit Student Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row g-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Full Name</label>
                                                                <input type="text" name="name" class="form-control" value="<?= e($stu['name']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Email Address</label>
                                                                <input type="email" name="email" class="form-control" value="<?= e($stu['email']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Username</label>
                                                                <input type="text" name="username" class="form-control" value="<?= e($stu['username']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-semibold">Reset Password (leave blank to keep)</label>
                                                                <input type="password" name="password" class="form-control" placeholder="••••••••">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Roll Number</label>
                                                                <input type="number" name="roll_no" class="form-control" value="<?= $stu['roll_no'] ?>" required>
                                                            </div>
                                                            <div class="col-md-8">
                                                                <label class="form-label fw-semibold">Enrollment Number</label>
                                                                <input type="text" name="enrollment_no" class="form-control" value="<?= e($stu['enrollment_no']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Semester</label>
                                                                <select name="semester_id" class="form-select" required>
                                                                    <?php foreach ($semesters as $s): ?>
                                                                        <option value="<?= $s['semester_id'] ?>" <?= $stu['semester_id'] == $s['semester_id'] ? 'selected' : '' ?>>
                                                                            <?= e($s['semester_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Division</label>
                                                                <select name="division_id" class="form-select" required>
                                                                    <?php foreach ($divisions as $d): ?>
                                                                        <option value="<?= $d['division_id'] ?>" <?= $stu['division_id'] == $d['division_id'] ? 'selected' : '' ?>>
                                                                            Division <?= e($d['division_name']) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label fw-semibold">Batch</label>
                                                                <select name="batch_id" class="form-select" required>
                                                                    <?php foreach ($batches as $b): ?>
                                                                        <option value="<?= $b['batch_id'] ?>" <?= $stu['batch_id'] == $b['batch_id'] ? 'selected' : '' ?>>
                                                                            <?= e($b['batch_name']) ?> (Roll <?= $b['start_roll_no'] ?>-<?= $b['end_roll_no'] ?>)
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label class="form-label fw-semibold">Status</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="active" <?= $stu['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                    <option value="inactive" <?= $stu['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="students.php">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Enroll New Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Full Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. John Doe" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="e.g. student@college.edu" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="e.g. roll101 or john_doe" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Initial Password</label>
                            <input type="password" name="password" class="form-control" value="Student@123" required>
                            <small class="text-muted">Default is <code>Student@123</code></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Roll Number</label>
                            <input type="number" name="roll_no" class="form-control" placeholder="e.g. 101" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Enrollment Number</label>
                            <input type="text" name="enrollment_no" class="form-control" placeholder="e.g. EN2026BCA0101" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Semester</label>
                            <select name="semester_id" class="form-select" required>
                                <option value="">Select Semester</option>
                                <?php foreach ($semesters as $s): ?>
                                    <option value="<?= $s['semester_id'] ?>"><?= e($s['semester_name']) ?></option>
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
                            <label class="form-label fw-semibold">Batch</label>
                            <select name="batch_id" class="form-select" required>
                                <option value="">Select Batch</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?= $b['batch_id'] ?>">
                                        <?= e($b['batch_name']) ?> (Roll <?= $b['start_roll_no'] ?>-<?= $b['end_roll_no'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                    <button type="submit" class="btn btn-primary">Enroll Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
