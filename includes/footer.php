<?php if (is_logged_in()): ?>
        </main>
        <footer class="bg-white border-top py-3 px-4 text-muted small d-flex flex-wrap justify-content-between align-items-center no-print mt-auto">
            <div>
                &copy; <?= date('Y') ?> <strong><?= APP_NAME ?></strong>. Practical Examination Management.
            </div>
            <div>
                <span class="badge bg-light text-secondary border">PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?></span>
                <span class="badge bg-light text-secondary border">Bootstrap 5</span>
                <span class="badge bg-light text-secondary border">Paperless Exam</span>
            </div>
        </footer>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Application Script -->
<script src="<?= BASE_URL ?>assets/js/script.js"></script>
</body>
</html>
