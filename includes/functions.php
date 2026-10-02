<?php
/**
 * Utility Functions & Smart Allocation Engine
 * SMART QUESTION ALLOCATION SYSTEM
 */

require_once __DIR__ . '/../config/database.php';

function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

function set_flash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

function get_flash() {
    $types = ['success', 'error', 'warning', 'info'];
    $flash = [];
    foreach ($types as $type) {
        if (isset($_SESSION['flash_' . $type])) {
            $flash[$type] = $_SESSION['flash_' . $type];
            unset($_SESSION['flash_' . $type]);
        }
    }
    return $flash;
}

function render_flash_messages() {
    $flash = get_flash();
    $html = '';
    foreach ($flash as $type => $message) {
        $alertClass = match($type) {
            'success' => 'alert-success',
            'error'   => 'alert-danger',
            'warning' => 'alert-warning',
            'info'    => 'alert-info',
            default   => 'alert-secondary'
        };
        $icon = match($type) {
            'success' => 'fa-check-circle',
            'error'   => 'fa-circle-exclamation',
            'warning' => 'fa-triangle-exclamation',
            'info'    => 'fa-circle-info',
            default   => 'fa-bell'
        };
        $html .= '<div class="alert ' . $alertClass . ' alert-dismissible fade show shadow-sm" role="alert">
                    <i class="fa-solid ' . $icon . ' me-2"></i> ' . e($message) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>';
    }
    return $html;
}

function format_date($dateStr) {
    if (!$dateStr) return '-';
    return date('d M Y', strtotime($dateStr));
}

function format_time($timeStr) {
    if (!$timeStr) return '-';
    return date('h:i A', strtotime($timeStr));
}

function format_datetime($dtStr) {
    if (!$dtStr) return '-';
    return date('d M Y, h:i A', strtotime($dtStr));
}

function status_badge($status) {
    $status = strtolower($status);
    $map = [
        'active'     => ['class' => 'bg-success', 'text' => 'Active'],
        'inactive'   => ['class' => 'bg-secondary', 'text' => 'Inactive'],
        'scheduled'  => ['class' => 'bg-info text-dark', 'text' => 'Scheduled'],
        'running'    => ['class' => 'bg-warning text-dark', 'text' => 'Running'],
        'completed'  => ['class' => 'bg-primary', 'text' => 'Completed'],
        'cancelled'  => ['class' => 'bg-danger', 'text' => 'Cancelled'],
        'draft'      => ['class' => 'bg-secondary', 'text' => 'Draft'],
        'allocated'  => ['class' => 'bg-info text-dark', 'text' => 'Allocated'],
        'submitted'  => ['class' => 'bg-success', 'text' => 'Submitted'],
        'absent'     => ['class' => 'bg-danger', 'text' => 'Absent']
    ];
    $item = $map[$status] ?? ['class' => 'bg-secondary', 'text' => ucfirst($status)];
    return '<span class="badge ' . $item['class'] . '">' . $item['text'] . '</span>';
}

/**
 * SMART QUESTION ALLOCATION ENGINE
 * 
 * Rules:
 * 1. Automatic server-side allocation (no client-side manipulation).
 * 2. Randomized assignment using PHP cryptographic / pseudo-random shuffling.
 * 3. NO SAME QUESTION FOR CONSECUTIVE STUDENTS (based on roll number order).
 * 4. Fair uniform distribution if students count > questions count.
 * 5. Permanent database record: once allocated, it never changes.
 */
function allocate_exam_questions(PDO $pdo, int $exam_id, int $allocated_by): array {
    // 1. Fetch Exam Details
    $stmt = $pdo->prepare("
        SELECT e.*, s.subject_name, b.batch_name, sem.semester_name, d.division_name
        FROM exams e
        JOIN subjects s ON e.subject_id = s.subject_id
        JOIN batches b ON e.batch_id = b.batch_id
        JOIN semesters sem ON e.semester_id = sem.semester_id
        JOIN divisions d ON e.division_id = d.division_id
        WHERE e.exam_id = ?
    ");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();

    if (!$exam) {
        return ['success' => false, 'message' => 'Exam record not found.'];
    }

    if ($exam['status'] === 'running') {
        return ['success' => false, 'message' => 'Exam is already running and questions are already allocated.'];
    }

    if ($exam['status'] === 'completed') {
        return ['success' => false, 'message' => 'Exam has already been completed. Allocation cannot be altered.'];
    }

    // 2. Fetch Eligible Students in Batch, sorted by Roll Number ASC
    $studentStmt = $pdo->prepare("
        SELECT s.student_id, s.roll_no, s.enrollment_no, u.name, u.email
        FROM students s
        JOIN users u ON s.user_id = u.user_id
        WHERE s.batch_id = ? AND s.semester_id = ? AND s.division_id = ? AND s.status = 'active'
        ORDER BY s.roll_no ASC
    ");
    $studentStmt->execute([$exam['batch_id'], $exam['semester_id'], $exam['division_id']]);
    $students = $studentStmt->fetchAll();

    if (empty($students)) {
        return [
            'success' => false,
            'message' => 'No active students found in ' . $exam['batch_name'] . ' (Semester: ' . $exam['semester_name'] . ', Division: ' . $exam['division_name'] . ').'
        ];
    }

    // 3. Fetch Eligible Questions for Subject & Semester
    $questionStmt = $pdo->prepare("
        SELECT question_id, question_number, question_text
        FROM questions
        WHERE subject_id = ? AND semester_id = ? AND status = 'active'
        ORDER BY question_number ASC
    ");
    $questionStmt->execute([$exam['subject_id'], $exam['semester_id']]);
    $questions = $questionStmt->fetchAll();

    if (empty($questions)) {
        return [
            'success' => false,
            'message' => 'No active questions available in the question bank for ' . $exam['subject_name'] . '.'
        ];
    }

    $numStudents = count($students);
    $numQuestions = count($questions);

    // Rule validation: If 2 or more students, at least 2 questions are strictly needed for consecutive non-collision
    if ($numStudents > 1 && $numQuestions < 2) {
        return [
            'success' => false,
            'message' => 'At least 2 questions are required in the question bank to guarantee non-consecutive allocation for ' . $numStudents . ' students.'
        ];
    }

    // 4. SMART ALLOCATION ALGORITHM
    $allocations = []; // [student_id => question_id]
    $qIds = array_column($questions, 'question_id');

    if ($exam['allocation_rule'] === 'pure_random' && $numQuestions >= 2) {
        // Pure random with consecutive check
        $lastQ = null;
        foreach ($students as $student) {
            $pool = array_values(array_filter($qIds, fn($qid) => $qid !== $lastQ));
            $chosen = $pool[array_rand($pool)];
            $allocations[$student['student_id']] = $chosen;
            $lastQ = $chosen;
        }
    } else {
        // DEFAULT: 'random_no_consecutive' with fair uniform frequency distribution
        if ($numQuestions >= $numStudents) {
            // Case A: More or equal questions than students
            // Shuffle questions and assign unique question to each student. Zero collision!
            $shuffled = $qIds;
            shuffle($shuffled);
            foreach ($students as $idx => $student) {
                $allocations[$student['student_id']] = $shuffled[$idx];
            }
        } else {
            // Case B: More students than questions (e.g. 30 students, 10 questions)
            // Distribute with maximum fairness (uniform counts) while strictly avoiding consecutive duplicates
            $counts = array_fill_keys($qIds, 0);
            $lastQ = null;

            foreach ($students as $student) {
                // Filter out the question of the previous student to prevent consecutive duplicate
                $candidateIds = array_values(array_filter($qIds, fn($qid) => $qid !== $lastQ));

                // Find candidate questions with minimum allocation count so far
                $minCount = PHP_INT_MAX;
                foreach ($candidateIds as $cid) {
                    if ($counts[$cid] < $minCount) {
                        $minCount = $counts[$cid];
                    }
                }

                $bestCandidates = array_values(array_filter($candidateIds, fn($cid) => $counts[$cid] === $minCount));

                // Randomly select among the least-used non-consecutive candidates
                $chosen = $bestCandidates[array_rand($bestCandidates)];

                $allocations[$student['student_id']] = $chosen;
                $counts[$chosen]++;
                $lastQ = $chosen;
            }
        }
    }

    // 5. ATOMIC PERSISTENCE IN DATABASE
    try {
        $pdo->beginTransaction();

        // Check if any existing allocations exist for this exam and remove them if draft/scheduled
        $delStmt = $pdo->prepare("DELETE FROM question_allocations WHERE exam_id = ?");
        $delStmt->execute([$exam_id]);

        $insStmt = $pdo->prepare("
            INSERT INTO question_allocations (exam_id, student_id, question_id, allocated_at, allocated_by, status)
            VALUES (?, ?, ?, NOW(), ?, 'allocated')
        ");

        foreach ($students as $student) {
            $sid = $student['student_id'];
            $qid = $allocations[$sid];
            $insStmt->execute([$exam_id, $sid, $qid, $allocated_by]);
        }

        // Update Exam Status to 'running' and set started_at
        $updateExam = $pdo->prepare("
            UPDATE exams 
            SET status = 'running', started_at = NOW() 
            WHERE exam_id = ?
        ");
        $updateExam->execute([$exam_id]);

        $pdo->commit();

        return [
            'success'         => true,
            'message'         => 'Exam started and ' . count($allocations) . ' questions allocated successfully without consecutive duplicates.',
            'total_students'  => $numStudents,
            'total_questions' => $numQuestions,
            'allocated_count' => count($allocations)
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'message' => 'Database error during question allocation: ' . $e->getMessage()
        ];
    }
}

/**
 * Built-in Excel / CSV Reader
 * Supports:
 * - .csv (native PHP fgetcsv)
 * - .xlsx (built-in OpenXML parser using ZipArchive + SimpleXML)
 * 
 * Returns array of rows: [['question_no' => 1, 'question_text' => '...'], ...]
 */
function parse_uploaded_question_file(string $filePath, string $extension): array {
    $extension = strtolower($extension);
    $rows = [];

    if ($extension === 'csv') {
        if (($handle = fopen($filePath, "r")) !== false) {
            $lineCount = 0;
            while (($data = fgetcsv($handle, 4096, ",")) !== false) {
                $lineCount++;
                if (empty($data) || (count($data) === 1 && trim($data[0]) === '')) {
                    continue;
                }
                // Check if first line is a header row
                if ($lineCount === 1) {
                    $c0 = strtolower(trim($data[0] ?? ''));
                    $c1 = strtolower(trim($data[1] ?? ''));
                    if (str_contains($c0, 'question') || str_contains($c0, 'sr') || str_contains($c0, 'no') || str_contains($c1, 'question') || str_contains($c1, 'text')) {
                        continue;
                    }
                }

                $qNo = trim($data[0] ?? '');
                $qText = trim($data[1] ?? '');

                // If only one column was provided or columns inverted
                if (empty($qText) && !empty($qNo) && !is_numeric($qNo)) {
                    $qText = $qNo;
                    $qNo = count($rows) + 1;
                }

                if (!empty($qText)) {
                    $rows[] = [
                        'question_number' => is_numeric($qNo) ? (int)$qNo : (count($rows) + 1),
                        'question_text'   => $qText
                    ];
                }
            }
            fclose($handle);
        }
    } elseif ($extension === 'xlsx') {
        if (!class_exists('ZipArchive')) {
            throw new Exception("PHP ZipArchive extension is not enabled. Please enable extension=zip in php.ini or upload as CSV.");
        }
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            // Read shared strings
            $sharedStrings = [];
            $sharedXmlData = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXmlData) {
                $xml = simplexml_load_string($sharedXmlData);
                if ($xml && isset($xml->si)) {
                    foreach ($xml->si as $si) {
                        if (isset($si->t)) {
                            $sharedStrings[] = (string)$si->t;
                        } elseif (isset($si->r)) {
                            $text = '';
                            foreach ($si->r as $r) {
                                $text .= (string)$r->t;
                            }
                            $sharedStrings[] = $text;
                        } else {
                            $sharedStrings[] = '';
                        }
                    }
                }
            }

            // Read sheet1.xml
            $sheetXmlData = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXmlData) {
                $sheetXml = simplexml_load_string($sheetXmlData);
                if ($sheetXml && isset($sheetXml->sheetData->row)) {
                    $isHeader = true;
                    foreach ($sheetXml->sheetData->row as $r) {
                        $rowCells = [];
                        foreach ($r->c as $c) {
                            $val = (string)$c->v;
                            $type = (string)$c['t'];
                            if ($type === 's' && isset($sharedStrings[(int)$val])) {
                                $val = $sharedStrings[(int)$val];
                            }
                            $rowCells[] = trim($val);
                        }

                        if (empty($rowCells)) continue;

                        $c0 = strtolower($rowCells[0] ?? '');
                        $c1 = strtolower($rowCells[1] ?? '');

                        if ($isHeader) {
                            $isHeader = false;
                            if (str_contains($c0, 'question') || str_contains($c0, 'sr') || str_contains($c0, 'no') || str_contains($c1, 'question')) {
                                continue;
                            }
                        }

                        $qNo = $rowCells[0] ?? '';
                        $qText = $rowCells[1] ?? '';

                        if (empty($qText) && !empty($qNo) && !is_numeric($qNo)) {
                            $qText = $qNo;
                            $qNo = count($rows) + 1;
                        }

                        if (!empty($qText)) {
                            $rows[] = [
                                'question_number' => is_numeric($qNo) ? (int)$qNo : (count($rows) + 1),
                                'question_text'   => $qText
                            ];
                        }
                    }
                }
            }
            $zip->close();
        } else {
            throw new Exception("Unable to open XLSX file archive.");
        }
    } else {
        throw new Exception("Unsupported file format. Please upload .xlsx or .csv files.");
    }

    return $rows;
}
