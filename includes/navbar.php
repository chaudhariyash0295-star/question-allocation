<?php
$roleBadgeClass = match($currentUser['role'] ?? '') {
    'admin'   => 'bg-danger',
    'faculty' => 'bg-primary',
    'student' => 'bg-success',
    default   => 'bg-secondary'
};
?>
<header class="app-navbar no-print">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-outline-secondary btn-sm d-lg-none" id="sidebarToggle" type="button">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="d-flex flex-column">
            <span class="fw-bold text-dark fs-6 d-none d-sm-inline">College Practical Examination Portal</span>
            <small class="text-muted d-none d-md-inline" style="font-size: 0.75rem;">Paperless Secure Digital Chit Allocation</small>
        </div>
    </div>

    <div class="d-flex align-items-center gap-3">
        <span class="badge <?= $roleBadgeClass ?> text-uppercase px-2 py-1" style="font-size: 0.75rem;">
            <?= e($currentUser['role'] ?? 'User') ?>
        </span>

        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle gap-2" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 36px; height: 36px;">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div class="d-none d-md-block text-start">
                    <div class="fw-semibold small leading-tight"><?= e($currentUser['name'] ?? 'User') ?></div>
                    <div class="text-muted" style="font-size: 0.75rem;"><?= e($currentUser['email'] ?? '') ?></div>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="userMenuDropdown">
                <li><h6 class="dropdown-header">Signed in as <strong><?= e($currentUser['username'] ?? '') ?></strong></h6></li>
                <li><hr class="dropdown-divider"></li>
                <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/profile.php"><i class="fa-solid fa-user-gear me-2 text-muted"></i> Profile Settings</a></li>
                <?php elseif (($currentUser['role'] ?? '') === 'faculty'): ?>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>faculty/profile.php"><i class="fa-solid fa-user-gear me-2 text-muted"></i> Profile Settings</a></li>
                <?php else: ?>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>student/profile.php"><i class="fa-solid fa-user-graduate me-2 text-muted"></i> My Profile</a></li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</header>
