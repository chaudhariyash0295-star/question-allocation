# SMART QUESTION ALLOCATION SYSTEM (SQAS)
### Digital & Paperless College Practical Examination Platform

A comprehensive, production-grade web application developed in pure PHP 8+ and MySQL to eliminate printed question chits in college laboratory examinations. The system automates the randomized, collision-free allocation of practical exam questions to students based on batch boundaries and roll numbers, ensuring adjacent lab seats never receive identical questions.

---

## 📌 Project Objectives & Highlights

1. **100% Paperless Examination**: Eliminates printing, cutting, folding, and manual chit distribution bowls.
2. **Server-Side Smart Allocation Engine**: Mathematical non-consecutive seat allocation prevents bench neighbors from receiving duplicate questions.
3. **Immutable Assignment Records**: Once allocated, a student's question is permanently bound to their database record. Refreshing the browser, logging out, or switching devices will **never** change their assigned question.
4. **Batch & Roll Number Aware**: Understands laboratory batch ranges (e.g., Batch A: Roll 101–130) and automatically loads eligible examinees.
5. **Excel & CSV Question Bank Import**: 7-step wizard parses `.xlsx` and `.csv` files, checks for duplicates, and validates questions before committing to MySQL.
6. **Live Synchronized Timer**: Countdown timer synchronized with server timestamps automatically locks the practical session when the duration elapses.
7. **Three-Tier Role Architecture**: Separate, secured portals for College Administrators, Course Faculty, and Enrolled Students.

---

## 🛠️ Technology Stack (Strict Compliance)

- **Frontend**: HTML5, Vanilla CSS3, JavaScript (ES6+), Bootstrap 5.3 (CDN), Font Awesome 6.5 (CDN), Google Fonts (Inter)
- **Backend**: Core PHP 8+ (No frameworks, pure object-oriented PDO, secure password hashing)
- **Database**: MySQL / MariaDB (Normalized with foreign key constraints and indexing)
- **Environment**: XAMPP on Windows (Apache + MySQL + phpMyAdmin)
- **Root Directory**: `C:\xampp\htdocs\smart-question-allocation-system\`
- **Local URL**: `http://localhost/smart-question-allocation-system/`

---

## 🔑 Demo Login Credentials

| Role | Username / Email | Password | Description |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@example.com` *(or `admin`)* | `Admin@123` | Full administrative control over semesters, batches, students, faculty, subjects, and audits. |
| **Faculty** | `faculty@example.com` *(or `faculty`)* | `Faculty@123` | Course coordinator (Prof. Rajesh Sharma). Uploads questions, creates exams, and starts allocation. |
| **Student** | `student@example.com` *(or `student`)* | `Student@123` | Examinee Aarav Patel (Roll #101, Batch A). Views digital question chit and live exam countdown. |

> *Tip: On the login page, you can click the **Quick Demo Login Helper** buttons ("Demo Admin", "Demo Faculty", "Demo Student") to auto-fill credentials with a single click!*

---

## ⚙️ XAMPP Installation & Setup Instructions

### Step 1: Place Project Files in XAMPP
The project must reside in your XAMPP web root directory:
```
C:\xampp\htdocs\smart-question-allocation-system\
```

### Step 2: Start Apache and MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.

### Step 3: Enable the PHP ZIP Extension (For Excel `.xlsx` Uploads)
1. Open `C:\xampp\php\php.ini` in any text editor.
2. Search for `;extension=zip`.
3. Remove the leading semicolon (`;`) so it reads:
   ```ini
   extension=zip
   ```
4. Save the file and restart Apache in XAMPP.
*(Note: CSV format `.csv` is 100% supported natively without any configuration!)*

### Step 4: Import the Database
1. Open your browser and navigate to **phpMyAdmin**:
   ```
   http://localhost/phpmyadmin/
   ```
2. Click on the **Import** tab.
3. Choose the file located at:
   ```
   database/smart_question_allocation.sql
   ```
4. Click **Go** to create the `smart_question_allocation` database and load sample records.

### Step 5: Launch the Application
Open your web browser and visit:
```
http://localhost/smart-question-allocation-system/
```

---

## 🧠 Smart Question Allocation Engine: How It Works

The allocation algorithm is implemented in PHP in [`includes/functions.php`](includes/functions.php):

1. **Seat Ordering**: Students in the target laboratory batch are fetched and ordered strictly by `roll_no ASC` ($R_1, R_2, \dots, R_N$). In college computer labs, students sit in sequential roll order; therefore, adjacent indices ($i$ and $i+1$) correspond to physical bench neighbors.
2. **Eligibility Validation**:
   - The engine validates that the exam is currently in `scheduled` status.
   - Enrolled students count $N$ must be $\ge 1$.
   - Available questions in the question bank for this subject $M$ must be $\ge 2$ (if $N \ge 2$).
3. **Collision-Free Distribution**:
   - **Case A ($M \ge N$)**: When the question bank contains more questions than students, the questions array is shuffled cryptographically (`shuffle()`). Each student is assigned a unique question. Zero duplication occurs throughout the entire laboratory.
   - **Case B ($N > M$)**: When there are more students than questions (e.g., 30 students, 10 questions):
     - Each question must be reused approximately $\lceil N / M \rceil$ times.
     - For examinee $i$ ($i > 0$), candidate questions are filtered to strictly exclude the question assigned to examinee $i - 1$ ($Q_i \neq Q_{i-1}$).
     - Among eligible non-adjacent candidates, the algorithm selects questions having the minimum allocation count so far, picking randomly if multiple candidates tie.
     - This guarantees:
       1. **No consecutive duplicate chits** ($Q_i \neq Q_{i-1}$ for all $i$).
       2. **Fair, uniform distribution** across the entire question bank.
4. **Atomic Persistence**:
   - All assignments are written to `question_allocations` within a single MySQL PDO transaction.
   - The exam status transitions to `running`, and `started_at` is stamped.
   - The `question_allocations` table has a `UNIQUE(exam_id, student_id)` constraint, making the allocation permanent and immutable.

---

## 📂 Directory Structure

```
smart-question-allocation-system/
│
├── index.php                     # Landing page with feature showcase & login link
├── login.php                     # Multi-role authentication with demo fill buttons
├── logout.php                    # Session termination & clean redirection
├── README.md                     # Documentation & setup guide
│
├── config/
│   └── database.php              # PDO MySQL connection & application constants
│
├── includes/
│   ├── auth.php                  # Session checking, role authorization, CSRF protection
│   ├── functions.php             # Core allocation algorithm, Excel/CSV parser, formatting
│   ├── header.php                # HTML head, CDN assets, Inter font, custom CSS
│   ├── navbar.php                # Top navigation bar with user profile dropdown
│   ├── sidebar.php               # Role-based dynamic sidebar navigation
│   └── footer.php                # Page footer, Bootstrap bundle JS, custom script
│
├── admin/                        # College Administrator Module
│   ├── dashboard.php             # System metrics, recent exams, live allocation feed
│   ├── semesters.php             # Semester CRUD & status controls
│   ├── divisions.php             # Class division CRUD (A, B, C)
│   ├── batches.php               # Lab batch CRUD with start/end roll number ranges
│   ├── students.php              # Student enrollment, search, filters & password reset
│   ├── faculty.php               # Faculty registry, department, & designation management
│   ├── subjects.php              # Curriculum subjects & question bank linkage
│   ├── assignments.php           # Faculty-subject-batch mapping
│   ├── questions.php             # Master question bank with search & filters
│   ├── exams.php                 # Exam scheduling, status controls, & start trigger
│   ├── allocations.php           # Allocation viewer, collision checker, print & CSV
│   ├── reports.php               # Consolidated audit reports with examiner signature lines
│   └── profile.php               # Administrator credentials & password update
│
├── faculty/                      # Course Faculty Module
│   ├── dashboard.php             # Faculty overview, assigned subjects, quick actions
│   ├── subjects.php              # View assigned subjects & batch scopes
│   ├── questions.php             # Question bank management for assigned courses
│   ├── upload_questions.php      # 7-Step Excel (.xlsx) & CSV (.csv) question importer
│   ├── create_exam.php           # Schedule lab examination session
│   ├── start_exam.php            # Exam commencement & confirmation dialog
│   ├── allocations.php           # Roster of allocated questions with collision analysis
│   ├── reports.php               # Print-friendly exam records & CSV export
│   └── profile.php               # Faculty profile & password management
│
├── student/                      # Student Examinee Module
│   ├── dashboard.php             # Student profile, roll number, active/upcoming exam alerts
│   ├── active_exam.php           # DIGITAL QUESTION CHIT with live countdown timer
│   └── profile.php               # Academic information & password update
│
├── assets/
│   ├── css/
│   │   └── style.css             # Professional ERP stylesheet, chit styling, print media rules
│   └── js/
│       └── script.js             # Live countdown timer, mobile sidebar toggle, print triggers
│
├── database/
│   └── smart_question_allocation.sql # Complete SQL schema, relationships & sample data
│
└── uploads/
    ├── sample_questions.csv      # Sample CSV file ready for Excel upload testing
    ├── sample_questions.xlsx     # Sample XLSX file ready for Excel upload testing
    └── questions/                # Storage directory for uploaded question spreadsheets
```

---

## 🧪 Comprehensive End-to-End Test Flow

### 1. Test Admin Workflow:
1. Navigate to `http://localhost/smart-question-allocation-system/login.php`.
2. Click **Demo Admin** (or enter `admin@example.com` / `Admin@123`).
3. Explore the **Dashboard**: view active students, faculty, subjects, and exams.
4. Go to **Manage Batches**: notice Batch A (Roll 101–130) and Batch B (Roll 131–160).
5. Go to **Manage Students**: search examinees by roll number or name.
6. Go to **Manage Question Bank**: browse through 25 pre-loaded BCA401 questions.

### 2. Test Faculty Workflow:
1. Log in as **Faculty** (`faculty@example.com` / `Faculty@123`).
2. Go to **Upload Questions**:
   - Step 1: Select Subject `BCA401 - Web Programming`.
   - Step 2: Select `Semester 4`.
   - Step 3: Choose file `uploads/sample_questions.csv` or `uploads/sample_questions.xlsx`.
   - Click **Validate & Preview File**.
   - Review validation statistics: Total, Valid, Duplicate, Invalid.
   - Click **Confirm & Import Valid Questions to MySQL**.
3. Go to **Start Exam**:
   - Locate the scheduled exam: `Web Programming Practical Examination - 2026`.
   - Click **Start Exam**.
   - Review confirmation dialog showing Batch A (10 students, 25+ questions).
   - Click **Confirm & Start Examination**.
   - Observe message: *"Exam Started Successfully! Questions Allocated: 10/10."*
4. Go to **View Allocations**:
   - Inspect table showing Roll #101 through Roll #110.
   - Notice **Consecutive Collision: 0 (100% Compliant)**.
   - Click **Print Roster** or **Export CSV**.

### 3. Test Student Workflow:
1. Log in as **Student** (`student@example.com` / `Student@123`).
2. Examine the dashboard: your assigned **Roll Number is 101** in **Batch A**.
3. Notice the live examination alert banner with a button: **View Allocated Question Chit**.
4. Click to open **Practical Examination Chit**:
   - View your allocated **Question No. X** and the complete problem statement.
   - Watch the synchronized **Live Countdown Timer** counting down in real-time.
   - **Refresh the page multiple times**: notice the question **never changes**.
   - Log out and log back in: the same question remains allocated permanently.
5. Click **Print Question Chit** to test generation of the student's submission receipt.

---

## 🛡️ Real-World Security Features

- **Password Security**: All user passwords hashed using PHP `password_hash($pwd, PASSWORD_BCRYPT)` and checked with `password_verify()`.
- **Prepared Statements**: All database operations use PDO prepared statements with parameterized queries, preventing SQL injection.
- **Session & Role Guard**: Role-based access control (`require_auth(['admin'])`) prevents students from accessing faculty or administrative pages.
- **Output Escaping**: All user-rendered data is sanitized through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` via the `e()` helper to prevent XSS.
- **CSRF & Form Validation**: State-changing POST actions validate inputs, file types, and session state.

---

## 🎓 Academic Viva & Presentation Readiness

When presenting this project to external examiners or professors:
- **Core USP**: *"Instead of faculty cutting 30 printed question chits and passing a bowl where students pick chits manually, the Smart Question Allocation System delivers questions directly to each student's screen with server-enforced seat separation."*
- **Algorithmic Fairness**: *"Our PHP allocation algorithm guarantees that physically adjacent students (Roll 101, 102, 103...) never receive identical tasks, and all questions in the question bank are utilized with uniform frequency across the laboratory batch."*
- **Auditability**: *"The system maintains a permanent digital record of every allocation with timestamps and examiner credentials for college university inspections."*
