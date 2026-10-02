/**
 * SMART QUESTION ALLOCATION SYSTEM
 * Client-side JavaScript & Exam Timer
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Mobile Sidebar Toggle
    const sidebar = document.querySelector('.app-sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const backdrop = document.createElement('div');
    backdrop.className = 'sidebar-backdrop';
    document.body.appendChild(backdrop);

    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
        });

        backdrop.addEventListener('click', function () {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    }

    // 2. Exam Countdown Timer
    const timerElement = document.getElementById('examCountdownTimer');
    if (timerElement) {
        let remainingSeconds = parseInt(timerElement.getAttribute('data-remaining-seconds'), 10);
        const timerDisplay = document.getElementById('timerDisplay');
        const examEndBanner = document.getElementById('examEndBanner');
        const examActionArea = document.getElementById('examActionArea');

        function updateTimer() {
            if (isNaN(remainingSeconds) || remainingSeconds <= 0) {
                if (timerDisplay) timerDisplay.textContent = "00:00:00";
                if (examEndBanner) examEndBanner.classList.remove('d-none');
                if (examActionArea) examActionArea.classList.add('opacity-75');
                return;
            }

            const hours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;

            const pad = (n) => n.toString().padStart(2, '0');
            if (timerDisplay) {
                timerDisplay.textContent = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
                if (remainingSeconds < 300) { // Less than 5 mins
                    timerDisplay.style.color = '#ef4444'; // Red alert
                }
            }

            remainingSeconds--;
            setTimeout(updateTimer, 1000);
        }

        updateTimer();
    }

    // 3. Confirm Dialog Helpers
    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // 4. Print Trigger
    const printBtns = document.querySelectorAll('.btn-print');
    printBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            window.print();
        });
    });
});
