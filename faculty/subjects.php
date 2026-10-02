<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_auth(['faculty']);
$pageTitle = 'My Assigned Subjects';

$facultyId = $_SESSION['user_detail_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT fs.*, s.subject_name, s.subject_code, sem.semester_name, d.division_name, b.batch_name,
           (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.subject_id) as total_questions,
           (SELECT COUNT(*) FROM exams e WHERE e.subject_id = s.subject_id AND e.faculty_id = fs.faculty_id) as exams_count
    FROM faculty_subjects fs
    JOIN subjects s ON fs.subject_id = s.subject_id
    JOIN semesters sem ON fs.semester_id = sem.semester_id
    JOIN divisions d ON fs.division_id = d.division_id
    LEFT JOIN batches b ON fs.batch_id = b.batch_id
    WHERE fs.faculty_id = ?
    ORDER BY s.subject_code ASC
");
$stmt->execute([$facultyId]);
$assignments = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="fw-bold text-dark mb-1">My Assigned Practical Subjects</h3>
        <p class="text-muted small mb-0">Practical courses and laboratory batches assigned to you for examination coordination.</p>
    </div>
    <a href="upload_questions.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-file-excel me-1"></i> Upload Questions (Excel)
    </a>
</div>

<div class="row g-4">
    <?php if (empty($assignments)): ?>
        <div class="col-12">
            <div class="card p-5 text-center">
                <i class="fa-solid fa-book-open text-muted fs-1 mb-3"></i>
                <h5>No Subjects Assigned Yet</h5>
                <p class="text-muted">The administrator has not yet assigned any subjects to your account. Please contact college administration.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($assignments as $a): ?>
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 border-start border-primary border-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1">
                                <?= e($a['subject_code']) ?>
                            </span>
                            <span class="badge bg-light text-dark border">
                                <?= e($a['semester_name']) ?>
                            </span>
                        </div>
                        <h5 class="fw-bold text-dark mb-2"><?= e($a['subject_name']) ?></h5>
                        <div class="small text-muted mb-3">
                            <div><i class="fa-solid fa-sitemap me-1 text-primary"></i> Division: <strong><?= e($a['division_name']) ?></strong></div>
                            <div><i class="fa-solid fa-users-rectangle me-1 text-primary"></i> Batch: <strong><?= $a['batch_name'] ? e($a['batch_name']) : 'All Batches' ?></strong></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded mb-3 small">
                            <div>
                                <span class="text-muted">Question Bank:</span>
                                <strong class="text-dark"><?= $a['total_questions'] ?> Qs</strong>
                            </div>
                            <div>
                                <span class="text-muted">Exams:</span>
                                <strong class="text-dark"><?= $a['exams_count'] ?> Sessions</strong>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <a href="upload_questions.php?subject_id=<?= $a['subject_id'] ?>" class="btn btn-outline-success btn-sm flex-fill">
                                <i class="fa-solid fa-file-excel me-1"></i> Upload Qs
                            </a>
                            <a href="questions.php?subject_id=<?= $a['subject_id'] ?>" class="btn btn-outline-primary btn-sm flex-fill">
                                <i class="fa-solid fa-list-check me-1"></i> Bank
                            </a>
                            <a href="create_exam.php?subject_id=<?= $a['subject_id'] ?>" class="btn btn-primary btn-sm flex-fill">
                                <i class="fa-solid fa-plus me-1"></i> Exam
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
