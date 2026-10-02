-- ============================================================
-- SMART QUESTION ALLOCATION SYSTEM
-- Database Schema & Realistic Sample Data
-- For XAMPP / MySQL / MariaDB / PHP 8+
-- ============================================================

CREATE DATABASE IF NOT EXISTS `smart_question_allocation` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smart_question_allocation`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `question_allocations`;
DROP TABLE IF EXISTS `exams`;
DROP TABLE IF EXISTS `questions`;
DROP TABLE IF EXISTS `faculty_subjects`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `faculty`;
DROP TABLE IF EXISTS `students`;
DROP TABLE IF EXISTS `batches`;
DROP TABLE IF EXISTS `divisions`;
DROP TABLE IF EXISTS `semesters`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'faculty', 'student') NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Semesters Table
CREATE TABLE `semesters` (
  `semester_id` INT AUTO_INCREMENT PRIMARY KEY,
  `semester_name` VARCHAR(50) NOT NULL,
  `semester_code` VARCHAR(20) NOT NULL UNIQUE,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Divisions Table
CREATE TABLE `divisions` (
  `division_id` INT AUTO_INCREMENT PRIMARY KEY,
  `division_name` VARCHAR(20) NOT NULL UNIQUE,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Batches Table
CREATE TABLE `batches` (
  `batch_id` INT AUTO_INCREMENT PRIMARY KEY,
  `batch_name` VARCHAR(50) NOT NULL,
  `semester_id` INT NOT NULL,
  `division_id` INT NOT NULL,
  `start_roll_no` INT NOT NULL,
  `end_roll_no` INT NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_batch_sem_div` (`semester_id`, `division_id`),
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`semester_id`) ON DELETE CASCADE,
  FOREIGN KEY (`division_id`) REFERENCES `divisions`(`division_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Faculty Table
CREATE TABLE `faculty` (
  `faculty_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `faculty_code` VARCHAR(50) NOT NULL UNIQUE,
  `department` VARCHAR(100) NOT NULL DEFAULT 'Computer Applications',
  `designation` VARCHAR(100) NOT NULL DEFAULT 'Assistant Professor',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Students Table
CREATE TABLE `students` (
  `student_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `enrollment_no` VARCHAR(50) NOT NULL UNIQUE,
  `roll_no` INT NOT NULL,
  `semester_id` INT NOT NULL,
  `division_id` INT NOT NULL,
  `batch_id` INT NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_student_roll` (`roll_no`),
  INDEX `idx_student_batch` (`batch_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`semester_id`) ON DELETE CASCADE,
  FOREIGN KEY (`division_id`) REFERENCES `divisions`(`division_id`) ON DELETE CASCADE,
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`batch_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Subjects Table
CREATE TABLE `subjects` (
  `subject_id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_code` VARCHAR(50) NOT NULL UNIQUE,
  `subject_name` VARCHAR(150) NOT NULL,
  `semester_id` INT NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`semester_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Faculty Subject Assignments Table
CREATE TABLE `faculty_subjects` (
  `assignment_id` INT AUTO_INCREMENT PRIMARY KEY,
  `faculty_id` INT NOT NULL,
  `subject_id` INT NOT NULL,
  `semester_id` INT NOT NULL,
  `division_id` INT NOT NULL,
  `batch_id` INT DEFAULT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`faculty_id`) ON DELETE CASCADE,
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`semester_id`) ON DELETE CASCADE,
  FOREIGN KEY (`division_id`) REFERENCES `divisions`(`division_id`) ON DELETE CASCADE,
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`batch_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Question Bank Table
CREATE TABLE `questions` (
  `question_id` INT AUTO_INCREMENT PRIMARY KEY,
  `subject_id` INT NOT NULL,
  `semester_id` INT NOT NULL,
  `batch_id` INT DEFAULT NULL,
  `question_number` INT NOT NULL,
  `question_text` TEXT NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_questions_subject` (`subject_id`),
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`semester_id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Exams Table
CREATE TABLE `exams` (
  `exam_id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_name` VARCHAR(150) NOT NULL,
  `exam_code` VARCHAR(50) NOT NULL UNIQUE,
  `subject_id` INT NOT NULL,
  `semester_id` INT NOT NULL,
  `division_id` INT NOT NULL,
  `batch_id` INT NOT NULL,
  `faculty_id` INT NOT NULL,
  `exam_date` DATE NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `duration_minutes` INT NOT NULL DEFAULT 120,
  `total_marks` INT NOT NULL DEFAULT 50,
  `instructions` TEXT DEFAULT NULL,
  `allocation_rule` ENUM('random_no_consecutive', 'pure_random') NOT NULL DEFAULT 'random_no_consecutive',
  `status` ENUM('draft', 'scheduled', 'running', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
  `started_at` DATETIME DEFAULT NULL,
  `ended_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_exam_status` (`status`),
  FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`subject_id`) ON DELETE CASCADE,
  FOREIGN KEY (`semester_id`) REFERENCES `semesters`(`semester_id`) ON DELETE CASCADE,
  FOREIGN KEY (`division_id`) REFERENCES `divisions`(`division_id`) ON DELETE CASCADE,
  FOREIGN KEY (`batch_id`) REFERENCES `batches`(`batch_id`) ON DELETE CASCADE,
  FOREIGN KEY (`faculty_id`) REFERENCES `faculty`(`faculty_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Question Allocations Table (Permanent Allocation Record)
CREATE TABLE `question_allocations` (
  `allocation_id` INT AUTO_INCREMENT PRIMARY KEY,
  `exam_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `question_id` INT NOT NULL,
  `allocated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `allocated_by` INT NOT NULL,
  `status` ENUM('allocated', 'in_progress', 'completed', 'absent') NOT NULL DEFAULT 'allocated',
  `remarks` VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY `unique_exam_student` (`exam_id`, `student_id`),
  FOREIGN KEY (`exam_id`) REFERENCES `exams`(`exam_id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`student_id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`question_id`) ON DELETE CASCADE,
  FOREIGN KEY (`allocated_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SAMPLE DATA INSERTION
-- Password hashes generated with bcrypt:
-- Admin: Admin@123 ($2y$10$Y/6dUDh8MgQyvH8EFhJXRO83cTrZmlgnZatPL3agDBfjnF/VG3.kS)
-- Faculty: Faculty@123 ($2y$10$/I/JnucZznoIlqHJUqJ9fOqnRCdjMxHseZpbQKWyHEUGImdyVH2Y.)
-- Student: Student@123 ($2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq)
-- ============================================================

-- Users: Admin & Faculty
INSERT INTO `users` (`user_id`, `name`, `email`, `username`, `password`, `role`, `status`) VALUES
(1, 'System Administrator', 'admin@example.com', 'admin', '$2y$10$Y/6dUDh8MgQyvH8EFhJXRO83cTrZmlgnZatPL3agDBfjnF/VG3.kS', 'admin', 'active'),
(2, 'Prof. Rajesh Sharma', 'faculty@example.com', 'faculty', '$2y$10$/I/JnucZznoIlqHJUqJ9fOqnRCdjMxHseZpbQKWyHEUGImdyVH2Y.', 'faculty', 'active');

-- Semesters
INSERT INTO `semesters` (`semester_id`, `semester_name`, `semester_code`, `status`) VALUES
(1, 'Semester 1', 'SEM-1', 'active'),
(2, 'Semester 2', 'SEM-2', 'active'),
(3, 'Semester 3', 'SEM-3', 'active'),
(4, 'Semester 4', 'SEM-4', 'active'),
(5, 'Semester 5', 'SEM-5', 'active'),
(6, 'Semester 6', 'SEM-6', 'active');

-- Divisions
INSERT INTO `divisions` (`division_id`, `division_name`, `status`) VALUES
(1, 'A', 'active'),
(2, 'B', 'active'),
(3, 'C', 'active');

-- Batches for Semester 4, Division A
INSERT INTO `batches` (`batch_id`, `batch_name`, `semester_id`, `division_id`, `start_roll_no`, `end_roll_no`, `status`) VALUES
(1, 'Batch A', 4, 1, 101, 130, 'active'),
(2, 'Batch B', 4, 1, 131, 160, 'active');

-- Faculty Details
INSERT INTO `faculty` (`faculty_id`, `user_id`, `faculty_code`, `department`, `designation`, `status`) VALUES
(1, 2, 'FAC-BCA-01', 'Computer Applications', 'Associate Professor', 'active');

-- Subjects
INSERT INTO `subjects` (`subject_id`, `subject_code`, `subject_name`, `semester_id`, `status`) VALUES
(1, 'BCA401', 'Web Programming', 4, 'active'),
(2, 'BCA402', 'Python Programming', 4, 'active'),
(3, 'BCA403', 'Database Management Systems', 4, 'active'),
(4, 'BCA201', 'Data Structures & Algorithms', 2, 'active');

-- Faculty Subject Assignment
INSERT INTO `faculty_subjects` (`assignment_id`, `faculty_id`, `subject_id`, `semester_id`, `division_id`, `batch_id`) VALUES
(1, 1, 1, 4, 1, 1),
(2, 1, 2, 4, 1, 1);

-- Students (10 students in Batch A, Roll 101 to 110)
INSERT INTO `users` (`user_id`, `name`, `email`, `username`, `password`, `role`, `status`) VALUES
(3, 'Aarav Patel', 'student@example.com', 'student', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active'),
(4, 'Rahul Mehta', 'rahul@example.com', 'roll102', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active'),
(5, 'Priya Shah', 'priya@example.com', 'roll103', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active'),
(6, 'Amit Joshi', 'amit@example.com', 'roll104', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active'),
(7, 'Sneha Deshmukh', 'sneha@example.com', 'roll105', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active'),
(8, 'Rohit Verma', 'rohit@example.com', 'roll106', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active'),
(9, 'Ananya Iyer', 'ananya@example.com', 'roll107', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active'),
(10, 'Vikram Singh', 'vikram@example.com', 'roll108', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active'),
(11, 'Pooja Kulkarni', 'pooja@example.com', 'roll109', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active'),
(12, 'Karan Malhotra', 'karan@example.com', 'roll110', '$2y$10$kIrVgzd.ID4Vs/CSXLX0JeiLPjH7VPTBFXG4z.YJYkcI3eKdEpWiq', 'student', 'active');

INSERT INTO `students` (`student_id`, `user_id`, `enrollment_no`, `roll_no`, `semester_id`, `division_id`, `batch_id`, `status`) VALUES
(1, 3, 'EN2024BCA0101', 101, 4, 1, 1, 'active'),
(2, 4, 'EN2024BCA0102', 102, 4, 1, 1, 'active'),
(3, 5, 'EN2024BCA0103', 103, 4, 1, 1, 'active'),
(4, 6, 'EN2024BCA0104', 104, 4, 1, 1, 'active'),
(5, 7, 'EN2024BCA0105', 105, 4, 1, 1, 'active'),
(6, 8, 'EN2024BCA0106', 106, 4, 1, 1, 'active'),
(7, 9, 'EN2024BCA0107', 107, 4, 1, 1, 'active'),
(8, 10, 'EN2024BCA0108', 108, 4, 1, 1, 'active'),
(9, 11, 'EN2024BCA0109', 109, 4, 1, 1, 'active'),
(10, 12, 'EN2024BCA0110', 110, 4, 1, 1, 'active');

-- 25 Realistic Questions for BCA401 (Web Programming)
INSERT INTO `questions` (`question_id`, `subject_id`, `semester_id`, `batch_id`, `question_number`, `question_text`, `status`, `created_by`) VALUES
(1, 1, 4, 1, 1, 'Create a PHP script to accept a user registration form with username, password, email, and mobile number. Validate all fields server-side and display appropriate error or success messages.', 'active', 2),
(2, 1, 4, 1, 2, 'Design an HTML5 form with client-side JavaScript validation for student registration (Name, Email, Phone, Password, and Confirm Password match). Highlight invalid inputs in red.', 'active', 2),
(3, 1, 4, 1, 3, 'Write a PHP and MySQL application to perform complete CRUD operations (Create, Read, Update, Delete) for an Employee record (EmpID, Name, Dept, Salary).', 'active', 2),
(4, 1, 4, 1, 4, 'Develop a PHP session-based shopping cart system that allows users to add items, view cart summary, calculate total price, and clear the cart upon checkout.', 'active', 2),
(5, 1, 4, 1, 5, 'Create an AJAX-powered live search in PHP and MySQL that filters student records in real-time as the user types into a search text field without page reload.', 'active', 2),
(6, 1, 4, 1, 6, 'Write a PHP program to upload an image file (PNG/JPG only, max 2MB). Validate file extension, MIME type, and size, rename with timestamp, and save to an upload folder.', 'active', 2),
(7, 1, 4, 1, 7, 'Build a secure login authentication system using PHP sessions and password_hash()/password_verify(). Implement logout and redirect unauthenticated users to login.', 'active', 2),
(8, 1, 4, 1, 8, 'Create an interactive quiz application using JavaScript and DOM manipulation. Calculate score upon submission, display grade, and provide an option to review answers.', 'active', 2),
(9, 1, 4, 1, 9, 'Write a PHP script using PDO to fetch and display student examination results in a responsive Bootstrap table with pagination (5 records per page).', 'active', 2),
(10, 1, 4, 1, 10, 'Create a dynamic cascading dropdown using JavaScript and PHP: selecting a State dynamically populates its corresponding Cities via Fetch API / AJAX.', 'active', 2),
(11, 1, 4, 1, 11, 'Design a responsive modern portfolio card layout using CSS Flexbox and Grid. Include hover zoom animations, social media links, and a contact modal dialog.', 'active', 2),
(12, 1, 4, 1, 12, 'Write a PHP program to generate an HTML invoice table dynamically from a multidimensional associative array containing product details, tax (18%), and grand total.', 'active', 2),
(13, 1, 4, 1, 13, 'Implement a "Remember Me" persistent login functionality in PHP using secure HTTP-only cookies and cryptographic tokens stored in the database.', 'active', 2),
(14, 1, 4, 1, 14, 'Create a JavaScript countdown timer for an online auction. When time runs out, disable bidding buttons and display an alert notifying the auction has closed.', 'active', 2),
(15, 1, 4, 1, 15, 'Write a PHP script to read a CSV file containing student marks, calculate aggregate percentages, assign grades (A/B/C/Fail), and display the tabulated output.', 'active', 2),
(16, 1, 4, 1, 16, 'Build a simple web-based calculator using HTML, CSS, and JavaScript capable of addition, subtraction, multiplication, division, clear, and decimal operations.', 'active', 2),
(17, 1, 4, 1, 17, 'Create a PHP and MySQL Feedback management system where visitors submit feedback and admin can view, approve, and delete feedback entries.', 'active', 2),
(18, 1, 4, 1, 18, 'Write a JavaScript program that fetches weather data from a mock JSON API using async/await and dynamically updates temperature, humidity, and condition icon on the DOM.', 'active', 2),
(19, 1, 4, 1, 19, 'Develop a PHP script to implement basic CSRF (Cross-Site Request Forgery) token generation and verification on a money transfer / fund update form.', 'active', 2),
(20, 1, 4, 1, 20, 'Design a clean student attendance tracker in PHP & MySQL that lets a teacher mark students Present or Absent for a selected date and batch with a single submit button.', 'active', 2),
(21, 1, 4, 1, 21, 'Write a PHP script using regex (preg_match) to validate email format, strong password criteria (upper, lower, digit, symbol, min 8 chars), and Indian PAN card format.', 'active', 2),
(22, 1, 4, 1, 22, 'Create a JavaScript dark mode toggle that switches CSS color variables between light and dark themes and persists user preference using localStorage.', 'active', 2),
(23, 1, 4, 1, 23, 'Build a PHP contact form that sends an email notification via PHP mail() / PHPMailer simulation and saves the message into a contact_queries database table.', 'active', 2),
(24, 1, 4, 1, 24, 'Create a drag-and-drop Kanban task board (To-Do, In-Progress, Completed) using HTML5 Drag and Drop API with task state updates in JavaScript.', 'active', 2),
(25, 1, 4, 1, 25, 'Write a PHP script to export a MySQL table of student attendance into a downloadable CSV file with appropriate HTTP headers.', 'active', 2);

-- Sample Scheduled Exam
INSERT INTO `exams` (`exam_id`, `exam_name`, `exam_code`, `subject_id`, `semester_id`, `division_id`, `batch_id`, `faculty_id`, `exam_date`, `start_time`, `end_time`, `duration_minutes`, `total_marks`, `instructions`, `allocation_rule`, `status`, `started_at`, `ended_at`) VALUES
(1, 'Web Programming Practical Examination - 2026', 'EXAM-BCA401-A1', 1, 4, 1, 1, 1, CURDATE(), '09:00:00', '12:00:00', 120, 50, '1. Each student is allocated one unique practical problem.\n2. Consecutive students must not receive identical questions.\n3. The allocated question is permanent and cannot be modified.\n4. Complete and demonstrate the code to the practical examiner before time expires.', 'random_no_consecutive', 'scheduled', NULL, NULL);
