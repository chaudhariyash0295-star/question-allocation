<?php
$currentScript = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$role = $currentUser['role'] ?? '';

function is_active($page, $currentScript) {
    return ($currentScript === $page) ? 'active' : '';
}
?>
<aside class="app-sidebar no-print">
    <a href="<?= BASE_URL ?>" class="sidebar-brand">
        <i class="fa-solid fa-layer-group"></i>
        <span>SQAS Portal</span>
    </a>

    <ul class="sidebar-menu">
        <?php if ($role === 'admin'): ?>
            <!-- ADMIN MENU -->
            <li class="sidebar-heading">Core</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="sidebar-link <?= is_active('dashboard.php', $currentScript) ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="sidebar-heading">Academic Structure</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/semesters.php" class="sidebar-link <?= is_active('semesters.php', $currentScript) ?>">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>Manage Semesters</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/divisions.php" class="sidebar-link <?= is_active('divisions.php', $currentScript) ?>">
                    <i class="fa-solid fa-sitemap"></i>
                    <span>Manage Divisions</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/batches.php" class="sidebar-link <?= is_active('batches.php', $currentScript) ?>">
                    <i class="fa-solid fa-users-rectangle"></i>
                    <span>Manage Batches</span>
                </a>
            </li>

            <li class="sidebar-heading">User Management</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/students.php" class="sidebar-link <?= is_active('students.php', $currentScript) ?>">
                    <i class="fa-solid fa-user-graduate"></i>
                    <span>Manage Students</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/faculty.php" class="sidebar-link <?= is_active('faculty.php', $currentScript) ?>">
                    <i class="fa-solid fa-chalkboard-user"></i>
                    <span>Manage Faculty</span>
                </a>
            </li>

            <li class="sidebar-heading">Curriculum</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/subjects.php" class="sidebar-link <?= is_active('subjects.php', $currentScript) ?>">
                    <i class="fa-solid fa-book-open"></i>
                    <span>Manage Subjects</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/assignments.php" class="sidebar-link <?= is_active('assignments.php', $currentScript) ?>">
                    <i class="fa-solid fa-link"></i>
                    <span>Assign Subjects</span>
                </a>
            </li>

            <li class="sidebar-heading">Examinations</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/questions.php" class="sidebar-link <?= is_active('questions.php', $currentScript) ?>">
                    <i class="fa-solid fa-file-circle-question"></i>
                    <span>Manage Question Bank</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/exams.php" class="sidebar-link <?= is_active('exams.php', $currentScript) ?>">
                    <i class="fa-solid fa-laptop-code"></i>
                    <span>Manage Exams</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/allocations.php" class="sidebar-link <?= is_active('allocations.php', $currentScript) ?>">
                    <i class="fa-solid fa-dice"></i>
                    <span>Question Allocations</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/reports.php" class="sidebar-link <?= is_active('reports.php', $currentScript) ?>">
                    <i class="fa-solid fa-chart-column"></i>
                    <span>Reports & History</span>
                </a>
            </li>

            <li class="sidebar-heading">Account</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>admin/profile.php" class="sidebar-link <?= is_active('profile.php', $currentScript) ?>">
                    <i class="fa-solid fa-id-badge"></i>
                    <span>Profile</span>
                </a>
            </li>

        <?php elseif ($role === 'faculty'): ?>
            <!-- FACULTY MENU -->
            <li class="sidebar-heading">Overview</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>faculty/dashboard.php" class="sidebar-link <?= is_active('dashboard.php', $currentScript) ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>faculty/subjects.php" class="sidebar-link <?= is_active('subjects.php', $currentScript) ?>">
                    <i class="fa-solid fa-book-bookmark"></i>
                    <span>My Subjects</span>
                </a>
            </li>

            <li class="sidebar-heading">Questions & Bank</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>faculty/upload_questions.php" class="sidebar-link <?= is_active('upload_questions.php', $currentScript) ?>">
                    <i class="fa-solid fa-file-excel"></i>
                    <span>Upload Questions</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>faculty/questions.php" class="sidebar-link <?= is_active('questions.php', $currentScript) ?>">
                    <i class="fa-solid fa-file-circle-question"></i>
                    <span>Manage Questions</span>
                </a>
            </li>

            <li class="sidebar-heading">Examination Portal</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>faculty/create_exam.php" class="sidebar-link <?= is_active('create_exam.php', $currentScript) ?>">
                    <i class="fa-solid fa-circle-plus"></i>
                    <span>Create Exam</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>faculty/start_exam.php" class="sidebar-link <?= is_active('start_exam.php', $currentScript) ?>">
                    <i class="fa-solid fa-play"></i>
                    <span>Start Exam</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>faculty/allocations.php" class="sidebar-link <?= is_active('allocations.php', $currentScript) ?>">
                    <i class="fa-solid fa-table-list"></i>
                    <span>View Allocations</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>faculty/reports.php" class="sidebar-link <?= is_active('reports.php', $currentScript) ?>">
                    <i class="fa-solid fa-print"></i>
                    <span>Reports & History</span>
                </a>
            </li>

            <li class="sidebar-heading">Account</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>faculty/profile.php" class="sidebar-link <?= is_active('profile.php', $currentScript) ?>">
                    <i class="fa-solid fa-id-badge"></i>
                    <span>Profile</span>
                </a>
            </li>

        <?php elseif ($role === 'student'): ?>
            <!-- STUDENT MENU -->
            <li class="sidebar-heading">Examination</li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>student/dashboard.php" class="sidebar-link <?= is_active('dashboard.php', $currentScript) ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>student/active_exam.php" class="sidebar-link <?= is_active('active_exam.php', $currentScript) ?>">
                    <i class="fa-solid fa-ticket-simple"></i>
                    <span>Active Exam Chit</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?= BASE_URL ?>student/profile.php" class="sidebar-link <?= is_active('profile.php', $currentScript) ?>">
                    <i class="fa-solid fa-id-badge"></i>
                    <span>My Profile</span>
                </a>
            </li>
        <?php endif; ?>

        <li class="sidebar-heading">Session</li>
        <li class="sidebar-item">
            <a href="<?= BASE_URL ?>logout.php" class="sidebar-link text-danger">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>
