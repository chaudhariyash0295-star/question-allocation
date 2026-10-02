<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to respective dashboard
if (is_logged_in()) {
    $role = $_SESSION['user_role'] ?? '';
    if ($role === 'admin') {
        header("Location: " . BASE_URL . "admin/dashboard.php");
        exit;
    } elseif ($role === 'faculty') {
        header("Location: " . BASE_URL . "faculty/dashboard.php");
        exit;
    } elseif ($role === 'student') {
        header("Location: " . BASE_URL . "student/dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Question Allocation System | Paperless Practical Exam</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }
        .hero-banner {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            color: white;
            padding: 5rem 0 4rem;
        }
        .feature-icon-box {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
        }
        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-custom:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 20px -5px rgba(0,0,0,0.08);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark py-3" style="background-color: #0f172a !important;">
    <div class="container">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= BASE_URL ?>">
            <i class="fa-solid fa-layer-group text-primary"></i>
            <span>SMART QUESTION ALLOCATION</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="<?= BASE_URL ?>login.php" class="btn btn-primary px-4 fw-semibold">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Login to System
            </a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero-banner text-center">
    <div class="container">
        <div class="badge bg-primary bg-opacity-25 text-white border border-light border-opacity-25 px-3 py-2 rounded-pill mb-3">
            <i class="fa-solid fa-leaf me-1 text-success"></i> 100% Paperless College Practical Examination
        </div>
        <h1 class="display-4 fw-extrabold mb-3">SMART QUESTION ALLOCATION SYSTEM</h1>
        <p class="lead text-light text-opacity-75 max-w-2xl mx-auto mb-4" style="max-width: 780px;">
            Eliminating printed paper chits from practical exams. Our secure, server-side allocation engine guarantees randomized, fair, and non-consecutive question distribution for college laboratories.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="<?= BASE_URL ?>login.php" class="btn btn-primary btn-lg px-4 shadow">
                <i class="fa-solid fa-right-to-bracket me-2"></i> Access Examination Portal
            </a>
            <a href="#features" class="btn btn-outline-light btn-lg px-4">
                <i class="fa-solid fa-circle-info me-2"></i> Learn More
            </a>
        </div>
    </div>
</section>

<!-- Stats / Problem & Solution Banner -->
<section class="py-5 bg-white border-bottom">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-3">
                <div class="p-3">
                    <div class="text-primary fs-1 mb-2"><i class="fa-solid fa-scissors"></i></div>
                    <h5 class="fw-bold">No Paper Chits</h5>
                    <p class="text-muted small">No manual printing, cutting, folding, or chit bowls.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <div class="text-success fs-1 mb-2"><i class="fa-solid fa-shuffle"></i></div>
                    <h5 class="fw-bold">Smart Non-Consecutive</h5>
                    <p class="text-muted small">Consecutive roll numbers never receive identical questions.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <div class="text-warning fs-1 mb-2"><i class="fa-solid fa-lock"></i></div>
                    <h5 class="fw-bold">Fixed & Immutable</h5>
                    <p class="text-muted small">Question remains permanent once allocated; refreshing changes nothing.</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-3">
                    <div class="text-info fs-1 mb-2"><i class="fa-solid fa-file-excel"></i></div>
                    <h5 class="fw-bold">Excel Bank Upload</h5>
                    <p class="text-muted small">Faculty can bulk import 50+ questions with one-click validation.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Grid -->
<section class="py-5" id="features">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Key Architectural Features</h2>
            <p class="text-muted">Designed for colleges, universities, and BCA/BSc/BTech IT departments.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card card-custom h-100 p-4">
                    <div class="feature-icon-box bg-primary text-white">
                        <i class="fa-solid fa-user-shield"></i>
                    </div>
                    <h5 class="fw-bold">Three Role Architecture</h5>
                    <p class="text-muted small">Dedicated secure dashboards for College Admin, Course Faculty, and Enrolled Students with strict PHP session access control.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-custom h-100 p-4">
                    <div class="feature-icon-box bg-success text-white">
                        <i class="fa-solid fa-microchip"></i>
                    </div>
                    <h5 class="fw-bold">Smart Allocation Engine</h5>
                    <p class="text-muted small">PHP server-side algorithm enforces non-consecutive seat assignments and uniform frequency distribution across the lab.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-custom h-100 p-4">
                    <div class="feature-icon-box bg-warning text-white">
                        <i class="fa-solid fa-stopwatch-20"></i>
                    </div>
                    <h5 class="fw-bold">Live Synchronized Timer</h5>
                    <p class="text-muted small">Real-time countdown timer synchronized with server timestamps. Question visibility automatically locks when duration elapses.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-custom h-100 p-4">
                    <div class="feature-icon-box bg-info text-white">
                        <i class="fa-solid fa-users-viewfinder"></i>
                    </div>
                    <h5 class="fw-bold">Batch & Roll No Aware</h5>
                    <p class="text-muted small">Organize by Semester, Division, and Roll Number ranges (e.g. Batch A: 101-130). Auto-loads students directly into the exam.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-custom h-100 p-4">
                    <div class="feature-icon-box bg-purple text-white" style="background:#7c3aed;">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <h5 class="fw-bold">Permanent Audit Trail</h5>
                    <p class="text-muted small">Complete allocation history archived permanently in MySQL for accreditation, external examiner review, and verification.</p>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card card-custom h-100 p-4">
                    <div class="feature-icon-box bg-danger text-white">
                        <i class="fa-solid fa-print"></i>
                    </div>
                    <h5 class="fw-bold">Print & Export Reports</h5>
                    <p class="text-muted small">Instant printable allocation sheets for practical exam examiners with signature columns and CSV data exports.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to action -->
<section class="py-5 bg-white border-top">
    <div class="container text-center">
        <h3 class="fw-bold mb-3">Ready to conduct practical examinations?</h3>
        <p class="text-muted mb-4">Log in with Admin, Faculty, or Student demo credentials to test the complete workflow.</p>
        <a href="<?= BASE_URL ?>login.php" class="btn btn-primary btn-lg px-5">
            <i class="fa-solid fa-arrow-right-to-bracket me-2"></i> Launch Login Portal
        </a>
    </div>
</section>

<!-- Footer -->
<footer class="bg-dark text-white-50 py-4" style="background-color: #0f172a !important;">
    <div class="container d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <strong class="text-white">Smart Question Allocation System</strong> &copy; <?= date('Y') ?>
        </div>
        <div>
            Built with PHP 8, MySQL, Bootstrap 5 & Vanilla CSS.
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
