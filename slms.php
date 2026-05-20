<?php
// Start output buffering at the very beginning
ob_start();

header('Content-Type: application/json');

// Debug: Check if headers already sent
if (headers_sent($file, $line)) {
    error_log("Headers already sent in $file on line $line");
    ob_end_clean();
    echo json_encode(['ok' => false, 'msg' => 'Headers already sent']);
    exit;
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "D1a2n3i4e5l6.";
$dbname = "slms";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'msg' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Get action from both POST and GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Debug logging
error_log("Action received: '$action'");

// ==============================
// === ACTION HANDLER ===
// ==============================

try {
    switch ($action) {

        // === KCSE RESULTS LOGIC ===
        case 'parse_pdf':
            if (!isset($_FILES['pdf'])) {
                throw new Exception('No PDF uploaded');
            }

            $year = intval($_POST['year'] ?? 0);
            $filePath = $_FILES['pdf']['tmp_name'];

            $cmd = escapeshellcmd("python3 /home/daniel/Documents/githiga_high/parse_pdf.py \"$filePath\" $year");
            $output = shell_exec($cmd);

            if (!$output) {
                throw new Exception('Python script failed or returned nothing');
            }

            $json = json_decode($output, true);
            if ($json === null) {
                throw new Exception('Invalid JSON from Python: ' . $output);
            }

            echo json_encode($json);
            break;

        case 'import_csv':
            $data = json_decode(file_get_contents('php://input'), true);
            $csv = $data['csv'] ?? '';
            $year = (int)($data['year'] ?? 0);

            if (!$csv) {
                throw new Exception('No CSV provided');
            }

            $rows = array_map('str_getcsv', explode("\n", $csv));
            if (count($rows) < 2) {
                throw new Exception('No data in CSV');
            }

            array_shift($rows);

            $stmt = $conn->prepare("
                INSERT INTO kcse_results 
                (name, english, math, kiswahili, cre, chemistry, physics, biology, geography, history, computer, agriculture, business, mean_points, mean_grade, year)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $count = 0;
            foreach ($rows as $row) {
                if (count($row) < 15) continue;

                $name = trim($row[0]);
                $eng = trim($row[1]);
                $math = trim($row[2]);
                $kisw = trim($row[3]);
                $cre = trim($row[4]);
                $chem = trim($row[5]);
                $phys = trim($row[6]);
                $bio = trim($row[7]);
                $geo = trim($row[8]);
                $hist = trim($row[9]);
                $comp = trim($row[10]);
                $agri = trim($row[11]);
                $bus = trim($row[12]);
                $meanPts = trim($row[13]);
                $meanGrade = trim($row[14]);

                if (empty($name)) continue;

                $stmt->bind_param(
                    "ssssssssssssssss",
                    $name, $eng, $math, $kisw, $cre, $chem, $phys, $bio, $geo, $hist,
                    $comp, $agri, $bus, $meanPts, $meanGrade, $year
                );
                if ($stmt->execute()) $count++;
            }

            $stmt->close();
            echo json_encode([
                'success' => true,
                'count' => $count,
                'message' => "$count records imported."
            ]);
            break;

case 'fetch_years':
    $sql = "
        SELECT year, AVG(mean_points) AS avg_points
        FROM kcse_results
        WHERE mean_points IS NOT NULL AND mean_points != ''
        GROUP BY year
        ORDER BY year
    ";

    $result = $conn->query($sql);
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'year' => (int)$row['year'],
            'avg_points' => round($row['avg_points'], 2)
        ];
    }

    echo json_encode(['ok' => true, 'data' => $data]);
    break;
    case 'fetch_subjects':
    // Get latest year
    $res = $conn->query("SELECT MAX(year) AS max_year FROM kcse_results");
    $row = $res->fetch_assoc();
    $latest_year = $row['max_year'] ?? null;

    if (!$latest_year) {
        echo json_encode(['ok' => true, 'year' => null, 'data' => []]);
        break;
    }

    // List of subjects
    $subjects = [
        'english','math','kiswahili','cre','chemistry',
        'physics','biology','geography','history','computer','agriculture','business'
    ];

    $data = [];

    foreach ($subjects as $subj) {
        $stmt = $conn->prepare("SELECT `$subj` FROM kcse_results WHERE year = ?");
        $stmt->bind_param("i", $latest_year);
        $stmt->execute();
        $result = $stmt->get_result();

        $total = 0;
        $count = 0;

        while ($r = $result->fetch_assoc()) {
            $val = $r[$subj];

            // Only numeric values count
            if (is_numeric($val)) {
                $total += (float)$val;
                $count++;
            }
        }

        $avg = $count > 0 ? $total / $count : 0;

        $data[] = [
            'subject' => ucfirst($subj),
            'avg' => round($avg, 2)
        ];

        $stmt->close();
    }

    // Sort descending: highest average first
    usort($data, fn($a, $b) => $b['avg'] <=> $a['avg']);

    echo json_encode([
        'ok' => true,
        'year' => $latest_year,
        'data' => $data
    ]);
    break;

    case 'parse_exam_pdf':
    // Keep only the most critical initialization log
    error_log("DEBUG: parse_exam_pdf - Starting PDF parsing process");
    
    if (!isset($_FILES['pdf'])) {
        error_log("DEBUG: parse_exam_pdf - No PDF file uploaded");
        echo json_encode(['success' => false, 'error' => 'No PDF uploaded']);
        exit;
    }
    
    $filePath = $_FILES['pdf']['tmp_name'];
    $class     = $_POST['class'] ?? '';
    $stream    = $_POST['stream'] ?? '';
    $exam_name = $_POST['exam_name'] ?? '';
    $term      = $_POST['term'] ?? '';
    
    // Validate required parameters
    if (empty($class) || empty($stream) || empty($exam_name) || empty($term)) {
        error_log("DEBUG: parse_exam_pdf - Missing required parameters");
        echo json_encode(['success' => false, 'error' => 'Missing required parameters: class, stream, exam_name, or term']);
        exit;
    }

    // Verify the file is a PDF
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $filePath);
    finfo_close($finfo);
    
    if ($mime !== 'application/pdf') {
        error_log("DEBUG: parse_exam_pdf - Invalid MIME type: $mime");
        echo json_encode(['success' => false, 'error' => 'Uploaded file is not a PDF']);
        exit;
    }

    // Use a relative path or make the path configurable
    $pythonScriptPath = '/home/daniel/Documents/githiga_high/parse_pdf.py';
    
    // Check if Python script exists
    if (!file_exists($pythonScriptPath)) {
        error_log("DEBUG: parse_exam_pdf - Python script not found at: $pythonScriptPath");
        echo json_encode(['success' => false, 'error' => 'Python parser script not found']);
        exit;
    }

    $cmd = sprintf(
        'python3 %s --exam-mode %s --class %s --stream %s --exam-name %s --term %s',
        escapeshellarg($pythonScriptPath),
        escapeshellarg($filePath),
        escapeshellarg($class),
        escapeshellarg($stream),
        escapeshellarg($exam_name),
        escapeshellarg($term)
    );
    
    $output = shell_exec($cmd . ' 2>&1'); // Capture stderr as well
    if (!$output) {
        error_log("DEBUG: parse_exam_pdf - Python script returned empty output");
        echo json_encode(['success' => false, 'error' => 'Python script failed or returned nothing']);
        exit;
    }

    $json = json_decode($output, true);
    if ($json === null) {
        error_log("DEBUG: parse_exam_pdf - Invalid JSON from Python");
        echo json_encode(['success' => false, 'error' => 'Invalid JSON from Python']);
        exit;
    }
    
    echo json_encode($json);
    break;

case 'import_exam_csv':
    // Keep only the most critical initialization log
    error_log("DEBUG: import_exam_csv - Starting CSV import process");
    
    // Read and decode JSON input
    $input = file_get_contents('php://input');
    if (!$input) {
        error_log("DEBUG: import_exam_csv - No input data received");
        echo json_encode(['success' => false, 'message' => 'No data provided']);
        exit;
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("DEBUG: import_exam_csv - JSON decode error");
        echo json_encode(['success' => false, 'message' => 'Invalid JSON format']);
        exit;
    }

    $csv = $data['csv'] ?? '';
    $class = $data['class'] ?? '';
    $stream = $data['stream'] ?? '';
    $exam_name = $data['exam_name'] ?? '';
    $term = $data['term'] ?? '';

    if (empty($csv)) {
        error_log("DEBUG: import_exam_csv - CSV is empty");
        echo json_encode(['success' => false, 'message' => 'CSV data is missing or empty']);
        exit;
    }

    // Parse CSV
    $rows = array_map('str_getcsv', explode("\n", trim($csv)));
    if (count($rows) < 2) {
        error_log("DEBUG: import_exam_csv - Insufficient rows in CSV");
        echo json_encode(['success' => false, 'message' => 'No data found in CSV']);
        exit;
    }

    // Extract and normalize header
    $header = array_map('trim', array_shift($rows));
    
    // Updated expected header order to match Python changes
    $expectedHeader = [
        "student_admission_no", "student_name", "class", "stream", "exam_name", "term",
        "english", "math", "kiswahili", "cre", "chemistry",
        "physics", "biology", "geography", "history", "business",
        "agriculture", "computer", "mean_points", "mean_grade"
    ];

    if ($header !== $expectedHeader) {
        error_log("DEBUG: import_exam_csv - Header mismatch detected");
        echo json_encode([
            'success' => false, 
            'message' => 'CSV header format mismatch'
        ]);
        exit;
    }

    // Prepare SQL statement - updated to include student_admission_no
    $stmt = $conn->prepare("
        INSERT INTO exams 
        (student_admission_no, student_name, class, stream, exam_name, term,
         english, math, kiswahili, cre, chemistry,
         physics, biology, geography, history, business,
         agriculture, computer, mean_points, mean_grade)
        VALUES 
        (?, ?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?, ?, ?,
         ?, ?, ?, ?)
    ");

    if (!$stmt) {
        error_log("DEBUG: import_exam_csv - Prepare failed: " . $conn->error);
        echo json_encode([
            'success' => false, 
            'message' => 'Database error: Prepare failed'
        ]);
        exit;
    }

    $importCount = 0;
    $errors = [];

    foreach ($rows as $index => $row) {
        // Now expecting 20 columns instead of 19
        if (count($row) < 20) {
            $errors[] = "Row $index: Expected 20 columns, got " . count($row);
            continue;
        }

        // Extract and sanitize values - updated column indices
        $student_admission_no = trim($row[0]);
        $student_name  = trim($row[1]);
        $class_val     = trim($row[2]) ?: $class;
        $stream_val    = trim($row[3]) ?: $stream;
        $exam_name_val = trim($row[4]) ?: $exam_name;
        $term_val      = trim($row[5]) ?: $term;

        // Parse numeric scores, allow null - updated column indices
        $english     = is_numeric(trim($row[6]))  ? (float)trim($row[6])  : null;
        $math        = is_numeric(trim($row[7]))  ? (float)trim($row[7])  : null;
        $kiswahili   = is_numeric(trim($row[8]))  ? (float)trim($row[8])  : null;
        $cre         = is_numeric(trim($row[9]))  ? (float)trim($row[9])  : null;
        $chemistry   = is_numeric(trim($row[10])) ? (float)trim($row[10]) : null;
        $physics     = is_numeric(trim($row[11])) ? (float)trim($row[11]) : null;
        $biology     = is_numeric(trim($row[12])) ? (float)trim($row[12]) : null;
        $geography   = is_numeric(trim($row[13])) ? (float)trim($row[13]) : null;
        $history     = is_numeric(trim($row[14])) ? (float)trim($row[14]) : null;
        $business    = is_numeric(trim($row[15])) ? (float)trim($row[15]) : null;
        $agriculture = is_numeric(trim($row[16])) ? (float)trim($row[16]) : null;
        $computer    = is_numeric(trim($row[17])) ? (float)trim($row[17]) : null;
        $mean_points = is_numeric(trim($row[18])) ? (float)trim($row[18]) : null;
        $mean_grade  = trim($row[19]) ?: null;

        if (empty($student_admission_no) || empty($student_name)) {
            $errors[] = "Row $index: Missing student admission number or name";
            continue;
        }

        // Bind parameters in correct order - updated to include student_admission_no
        $stmt->bind_param(
            "ssssssssssssssssssss",
            $student_admission_no, $student_name, $class_val, $stream_val, $exam_name_val, $term_val,
            $english, $math, $kiswahili, $cre, $chemistry,
            $physics, $biology, $geography, $history, $business,
            $agriculture, $computer, $mean_points, $mean_grade
        );

        if ($stmt->execute()) {
            $importCount++;
        } else {
            $errors[] = "Row $index: " . $stmt->error;
        }
    }

    $stmt->close();

    // Prepare response
    $response = [
        'success' => true,
        'count' => $importCount,
        'message' => "$importCount exam records imported successfully."
    ];

    if (!empty($errors)) {
        $response['warnings'] = $errors;
    }

    echo json_encode($response);
    break;
        // === STUDENT DASHBOARD ===
        case 'student_info':
            $student_id = intval($_GET['student_id'] ?? 0);
            $stmt = $conn->prepare("SELECT id, name, class, stream FROM students WHERE id=?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            echo json_encode(['ok' => true, 'data' => $result]);
            break;

            //for displaying student results

        // === FETCH STUDENT EXAMS WITH KCSE MEAN CALCULATION ===
      case 'fetch_student_exams':
    // Get student ID from POST data
    $student_id = intval($_POST['student_id'] ?? 0);

    if ($student_id <= 0) {
        error_log("DEBUG: fetch_student_exams - Missing student ID");
        echo json_encode(['ok' => false, 'message' => 'Missing student ID']);
        exit;
    }

    // First, get the student admission number, class and stream from students table
    $sql = "SELECT id, name, admissionNo, class, stream FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("DEBUG: fetch_student_exams - Student not found: $student_id");
        echo json_encode(['ok' => false, 'message' => 'Student not found']);
        $stmt->close();
        exit;
    }

    $user = $result->fetch_assoc();
    $student_admission_no = $user['admissionNo'];
    $student_class = $user['class'];
    $student_stream = $user['stream'];
    $stmt->close();

    // Now query exams table using the admission number + class + stream
    // Proper ordering: Term1 -> Term2 -> Term3, and Opener -> Midterm -> Endterm
    $sql = "SELECT * FROM exams 
            WHERE student_admission_no = ? 
              AND class = ? 
              AND stream = ?
            ORDER BY 
              CASE term 
                WHEN 'Term1' THEN 1
                WHEN 'Term2' THEN 2
                WHEN 'Term3' THEN 3
              END,
              CASE exam_name
                WHEN 'Opener' THEN 1
                WHEN 'Midterm' THEN 2
                WHEN 'Endterm' THEN 3
              END,
              id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $student_admission_no, $student_class, $student_stream);
    $stmt->execute();
    $result = $stmt->get_result();

    // Helper function: Convert raw mark (%) into KCSE points (1–12)
    function markToPoints($mark) {
        if ($mark >= 80) return 12;
        if ($mark >= 75) return 11;
        if ($mark >= 70) return 10;
        if ($mark >= 65) return 9;
        if ($mark >= 60) return 8;
        if ($mark >= 55) return 7;
        if ($mark >= 50) return 6;
        if ($mark >= 45) return 5;
        if ($mark >= 40) return 4;
        if ($mark >= 35) return 3;
        if ($mark >= 30) return 2;
        return 1; // 0–29
    }

    $exams = [];
    while ($row = $result->fetch_assoc()) {
        // Subject fields to process
        $subjectFields = [
            'english','math','kiswahili','cre','chemistry',
            'physics','biology','geography','history',
            'computer','agriculture','business'
        ];

        $totalPoints = 0;
        $count = 0;

        foreach ($subjectFields as $field) {
            if (isset($row[$field]) && $row[$field] !== null && $row[$field] !== '') {
                $mark = (float)$row[$field];
                $points = markToPoints($mark);

                // Store both raw mark and computed points
                $row[$field] = $mark;
                $row[$field . "_points"] = $points;

                $totalPoints += $points;
                $count++;
            }
        }

        // Calculate KCSE mean points (1–12 scale)
        $row['mean_points'] = $count > 0 ? round($totalPoints / $count, 2) : 0;

        $exams[] = $row;
    }

    $stmt->close();

    // Return both the exam data and the student name
    echo json_encode([
        'ok' => true, 
        'data' => $exams,
        'student_name' => $user['name']
    ]);
    break;

    

       case 'student_materials':
    try {
        $class_stream = $_POST['class_stream'] ?? ''; // Changed from 'stream' to 'class_stream'
        
        if (empty($class_stream)) {
            echo json_encode(['ok' => false, 'error' => 'Class stream parameter missing']);
            exit;
        }
        
        $sql = "SELECT id, title, file_path, type, subject 
                FROM materials 
                WHERE class=?"; // This should match the combined class-stream format
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo json_encode(['ok' => false, 'error' => 'Database prepare failed: ' . $conn->error]);
            exit;
        }
        
        $stmt->bind_param("s", $class_stream); // Search for the combined value
        $stmt->execute();
        $res = $stmt->get_result();
        $materials = $res->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode(['ok' => true, 'data' => $materials]);
        
    } catch (Exception $e) {
        error_log("Error in student_materials: " . $e->getMessage());
        echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
    break;
       
        case 'student_payments':
    // Get student_id from request (coming from JS as admission_no)
    $student_id = intval($_GET['admission_no'] ?? 0);

    if ($student_id <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid student ID']);
        exit;
    }

    // First: Get admission number, name, class
    $sql = "SELECT admissionNo, name, class 
            FROM students 
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        echo json_encode(['ok' => false, 'msg' => 'Student not found']);
        exit;
    }

    $admissionNo = $student['admissionNo'];

    // Next: Get payments for this admission number
    $sql = "SELECT receipt_number, transaction_date, amount, term 
            FROM student_payments 
            WHERE admission_no = ?
            ORDER BY transaction_date ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $admissionNo);
    $stmt->execute();
    $result = $stmt->get_result();

    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();

    // Get term fees from database based on student's class
    // Handle both "Form4" and "Form 4" formats
    $class_name = $student['class'];
    $class_name_with_space = preg_replace('/(Form)(\d)/', '$1 $2', $class_name);

    $sql = "SELECT term, amount 
            FROM termly_fees 
            WHERE class_name IN (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $class_name, $class_name_with_space);
    $stmt->execute();
    $result = $stmt->get_result();

    $termly_fees = [];
    while ($row = $result->fetch_assoc()) {
        $termly_fees[$row['term']] = $row['amount'];
    }
    $stmt->close();

    // Define term order for processing
    $term_order = ['Term1', 'Term2', 'Term3'];
    
    // Calculate balances with carry forward
    $term_totals = [];
    $term_balances = [];
    $carry_forward_values = [];
    $previousBalance = 0;

    foreach ($term_order as $term) {
        if (!isset($termly_fees[$term])) {
            // If term fee is not defined, skip this term
            continue;
        }
        
        $fee = $termly_fees[$term];
        $paid = array_sum(array_column(
            array_filter($payments, fn($p) => $p['term'] === $term),
            'amount'
        ));

        // Net balance = fee - paid + previous balance (which might be + or -)
        $balance = $fee - $paid + $previousBalance;

        // Save carry forward for this term (can be positive = credit, negative = debt)
        $carry_forward_values[$term] = $previousBalance;

        // Store the calculated values
        $term_totals[$term] = $paid;
        $term_balances[$term] = $balance;

        // Move balance forward to next loop
        $previousBalance = $balance;
    }

    // === Fetch historical carry forward records for this student ===
    $sql = "SELECT from_term, to_term, amount, created_at 
            FROM carry_forward 
            WHERE admission_no = ?
            ORDER BY created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $admissionNo);
    $stmt->execute();
    $result = $stmt->get_result();

    $carry_forward_history = [];
    while ($row = $result->fetch_assoc()) {
        $carry_forward_history[] = $row;
    }
    $stmt->close();

    // === Final response ===
    echo json_encode([
        'ok' => true,
        'student' => [
            'name' => $student['name'],
            'admissionNo' => $admissionNo,
            'class' => $student['class']
        ],
        'payments' => $payments,
        'termly_fees' => $termly_fees,
        'term_totals' => $term_totals,
        'term_balances' => $term_balances,
        'carry_forward' => $carry_forward_values, // Calculated carry forward values
        'carry_forward_history' => $carry_forward_history // Historical records
    ]);
    exit;
       case 'fetch_class_perf':
    $student_id = intval($_POST['student_id'] ?? 0);
    if ($student_id <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Invalid student ID']);
        exit;
    }

    // Get student's class
    $stmt = $conn->prepare("SELECT class FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    $stmt->close();

    if (!$student) {
        echo json_encode(['ok' => false, 'msg' => 'Student not found']);
        exit;
    }

    $class = $student['class']; // e.g., "Form4"

    // Get latest exam for this class (by term + exam_name order)
    $sql = "
        SELECT exam_name, term
        FROM exams
        WHERE class = ?
        ORDER BY
    FIELD(term, 'Term3', 'Term2', 'Term1'),
    FIELD(exam_name, 'Endterm', 'Midterm', 'Opener'),
    id DESC
    LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $class);
    $stmt->execute();
    $result = $stmt->get_result();
    $latest = $result->fetch_assoc();
    $stmt->close();

    if (!$latest) {
        echo json_encode(['ok' => false, 'msg' => 'No exam data found for class']);
        exit;
    }

    $exam_name = $latest['exam_name'];
    $term = $latest['term'];

    // Fetch mean_grade per student for this exam/term
    $sql = "
        SELECT stream, mean_grade
        FROM exams
        WHERE class = ? AND exam_name = ? AND term = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $class, $exam_name, $term);
    $stmt->execute();
    $result = $stmt->get_result();

    // Grade to numeric scale
    $gradeScale = [
        "A" => 12, "A-" => 11, "B+" => 10, "B" => 9, "B-" => 8,
        "C+" => 7, "C" => 6, "C-" => 5, "D+" => 4, "D" => 3,
        "D-" => 2, "E" => 1
    ];

    $streamGrades = [];
    while ($row = $result->fetch_assoc()) {
        $stream = $row['stream'];
        $grade = strtoupper(trim($row['mean_grade'])); // normalize case/spacing
        if (isset($gradeScale[$grade])) {
            $streamGrades[$stream][] = $gradeScale[$grade];
        }
    }
    $stmt->close();

    // Compute average per stream
    $streams = [];
    $averages = [];
    foreach ($streamGrades as $stream => $grades) {
        $streams[] = $stream;
        $averages[] = round(array_sum($grades) / count($grades), 2);
    }

    echo json_encode([
        'ok' => true,
        'class' => $class,
        'exam_name' => $exam_name,
        'term' => $term,
        'streams' => $streams,
        'averages' => $averages
    ]);
    break;

        case 'student_notifications':
            $sql = "SELECT id, title, message, created_at 
                    FROM notifications 
                    ORDER BY created_at DESC";
            $res = $conn->query($sql);
            $data = $res->fetch_all(MYSQLI_ASSOC);

            echo json_encode(['ok' => true, 'data' => $data]);
            break;

        // === ADMIN DASHBOARD ===
        case 'view_results':
            $form = $_GET['form'] ?? '';
            $stream = $_GET['stream'] ?? '';
            $sql = "SELECT r.*, s.name as student_name, s.stream, s.class 
                    FROM results r 
                    JOIN students s ON r.student_id=s.id 
                    WHERE s.class=? AND s.stream=? 
                    ORDER BY s.name";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $form, $stream);
            $stmt->execute();
            $res = $stmt->get_result();
            $data = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            echo json_encode(['ok' => true, 'data' => $data]);
            break;

        
            case 'upload_material':
    $title   = trim($_POST['title'] ?? '');
    $class_stream = trim($_POST['class_stream'] ?? ''); // Combined class-stream value
    $subject = trim($_POST['subject'] ?? '');
    $type    = $_POST['type'] ?? '';

    if (empty($title) || empty($class_stream) || empty($subject) || !in_array($type, ['assignment', 'revision', 'report'])) {
        throw new Exception('Invalid or missing required fields');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['file'];

    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File too large. Maximum 10MB allowed.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    if (!in_array($detectedMimeType, $allowedMimes)) {
        throw new Exception('Only PDF, DOC, and DOCX files are allowed.');
    }

    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $originalName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
    $originalName = substr($originalName, 0, 100);

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeFileName = $originalName . '_' . uniqid() . '.' . $extension;
    $uploadDir = 'uploads';
    $dest = "$uploadDir/$safeFileName";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory is not writable');
    }

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new Exception('Failed to move uploaded file');
    }

    // Use the combined class_stream value in the database
    $stmt = $conn->prepare("INSERT INTO materials (title, class, subject, type, file_path) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        @unlink($dest);
        throw new Exception('Database error: Could not prepare statement');
    }

    $stmt->bind_param("sssss", $title, $class_stream, $subject, $type, $dest);
    if (!$stmt->execute()) {
        @unlink($dest);
        $stmt->close();
        throw new Exception('Failed to save record: ' . $conn->error);
    }
    $stmt->close();

    echo json_encode([
        'ok' => true,
        'msg' => 'Material uploaded successfully',
        'file' => $safeFileName
    ]);

    break;
    
       case 'fetch_class_perf_admin':
    $class = $_POST['class'] ?? '';
    if (!$class) {
        echo json_encode(['ok' => false, 'msg' => 'Missing class']);
        exit;
    }

    // Get latest exam for this class (by term + exam_name order)
    $sql = "
        SELECT exam_name, term
        FROM exams
        WHERE class = ?
        ORDER BY
            FIELD(term, 'Term3', 'Term2', 'Term1'),
            FIELD(exam_name, 'Endterm', 'Midterm', 'Opener'),
            id DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $class);
    $stmt->execute();
    $result = $stmt->get_result();
    $latest = $result->fetch_assoc();
    $stmt->close();

    if (!$latest) {
        echo json_encode(['ok' => false, 'msg' => "No exam data found for $class"]);
        exit;
    }

    $exam_name = $latest['exam_name'];
    $term = $latest['term'];

    // Fetch mean_grade per student for this exam/term
    $sql = "
        SELECT stream, mean_grade
        FROM exams
        WHERE class = ? AND exam_name = ? AND term = ?
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $class, $exam_name, $term);
    $stmt->execute();
    $result = $stmt->get_result();

    $gradeScale = [
        "A" => 12, "A-" => 11, "B+" => 10, "B" => 9, "B-" => 8,
        "C+" => 7, "C" => 6, "C-" => 5, "D+" => 4, "D" => 3,
        "D-" => 2, "E" => 1
    ];

    $streamGrades = [];
    while ($row = $result->fetch_assoc()) {
        $stream = $row['stream'];
        $grade = strtoupper(trim($row['mean_grade']));
        if (isset($gradeScale[$grade])) {
            $streamGrades[$stream][] = $gradeScale[$grade];
        }
    }
    $stmt->close();

    $streams = [];
    $averages = [];
    foreach ($streamGrades as $stream => $grades) {
        $streams[] = $stream;
        $averages[] = round(array_sum($grades) / count($grades), 2);
    }

    echo json_encode([
        'ok' => true,
        'class' => $class,
        'exam_name' => $exam_name,
        'term' => $term,
        'streams' => $streams,
        'averages' => $averages
    ]);
    break;

          case 'admin_payments_overview':

    // Get class filter from request
    $class_name = $_GET['class'] ?? '';
    if (!$class_name) {
        echo json_encode(['ok' => false, 'msg' => 'Class not specified']);
        exit;
    }

    // Normalize class format (Form1 -> Form 1)
    $class_with_space = preg_replace('/(Form)(\d)/', '$1 $2', $class_name);

    // Get term fees for the class
    $sql = "SELECT term, amount FROM termly_fees WHERE class_name IN (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $class_name, $class_with_space);
    $stmt->execute();
    $result = $stmt->get_result();

    $term_fees = [];
    while ($row = $result->fetch_assoc()) {
        $term_fees[$row['term']] = floatval($row['amount']);
    }
    $stmt->close();

    if (empty($term_fees)) {
        echo json_encode(['ok' => false, 'msg' => 'No term fees found for this class']);
        exit;
    }

    // Get students in the class
    $sql = "SELECT id, name, admissionNo FROM students WHERE class = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $class_name);
    $stmt->execute();
    $result = $stmt->get_result();

    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();

    if (empty($students)) {
        echo json_encode(['ok' => false, 'msg' => 'No students found in this class']);
        exit;
    }

    $term_order = ['Term1', 'Term2', 'Term3'];
    $overview = [];

    foreach ($students as $student) {
        $admission_no = $student['admissionNo'];

        // Fetch student payments by term
        $sql = "SELECT term, SUM(amount) as paid_amount 
                FROM student_payments 
                WHERE admission_no = ? 
                GROUP BY term";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $admission_no);
        $stmt->execute();
        $result = $stmt->get_result();

        $payments_by_term = [];
        while ($row = $result->fetch_assoc()) {
            $payments_by_term[$row['term']] = floatval($row['paid_amount']);
        }
        $stmt->close();

        // Calculate term balances with carry forward
        $term_balances = [];
        $previous_balance = 0;
        $include_student = false;

        foreach ($term_order as $term) {
            if (!isset($term_fees[$term])) continue;

            $fee = $term_fees[$term];
            $paid = $payments_by_term[$term] ?? 0;

            // Net balance = fee - paid + previous term balance
            $balance = $fee - $paid + $previous_balance;
            $term_balances[$term] = $balance;

            // Check if student paid less than 80% cumulatively for this term
            if (($paid + max(0, $previous_balance)) / $fee < 0.8) {
                $include_student = true;
            }

            $previous_balance = $balance;
        }

        if ($include_student) {
            $overview[] = [
                'student' => $student['name'],
                'admission_no' => $admission_no,
                'term_balances' => $term_balances,
                'term_paid' => $payments_by_term
            ];
        }
    }

    echo json_encode([
        'ok' => true,
        'term_order' => $term_order,
        'term_fees' => $term_fees,
        'data' => $overview
    ]);
    break;
      
        case 'fetch_logs':
    // ✅ Require password before fetching logs
    // ✅ Require password before fetching logs
$inputPassword = $_POST['password'] ?? '';

// ✅ Fetch stored hash from DB
$passQuery = $conn->query("SELECT password_hash FROM log_t LIMIT 1");
$row = $passQuery ? $passQuery->fetch_assoc() : null;
$storedHash = $row ? $row['password_hash'] : '';

if (!$storedHash || !password_verify($inputPassword, $storedHash)) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Invalid password"]);
    break;
}


    // ✅ Fetch latest 300 raw logs
    $sql = "SELECT * FROM database_audit ORDER BY changed_at DESC LIMIT 300";
    $result = $conn->query($sql);

    $groupedLogs = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Round timestamp to the second
            $timestamp = date("Y-m-d H:i:s", strtotime($row['changed_at']));

            // Unique group key
            $key = $timestamp . "_" . $row['action_type'] . "_" . $row['table_name'];

            if (!isset($groupedLogs[$key])) {
                $groupedLogs[$key] = [
                    "changed_at"  => $timestamp,
                    "action_type" => $row['action_type'],
                    "table_name"  => $row['table_name'],
                    "count"       => 0,
                    "ids"         => [],
                    "example"     => $row['new_data'] ?: $row['old_data']
                ];
            }

            $groupedLogs[$key]["count"]++;
            $groupedLogs[$key]["ids"][] = $row['record_id'];
        }
    }

    // Format final logs list
    $logs = [];
    foreach ($groupedLogs as $entry) {
        $idCount = count($entry["ids"]);
        $idDisplay = "";

        if ($idCount > 10) {
            $idDisplay = $entry["ids"][0] . "–" . end($entry["ids"]); // e.g. 100–250
        } else {
            $idDisplay = implode(", ", $entry["ids"]);
        }

        $logs[] = [
            "changed_at"  => $entry["changed_at"],
            "action_type" => strtoupper($entry["action_type"]) . ($entry["count"] > 1 ? "S" : ""),
            "table_name"  => $entry["table_name"],
            "record_id"   => $idDisplay,
            "old_data"    => "—",
            "new_data"    => $entry["example"],
            "count"       => $entry["count"]
        ];
    }

    // Sort newest first and limit to 30 grouped entries
    usort($logs, function($a, $b) {
        return strtotime($b["changed_at"]) - strtotime($a["changed_at"]);
    });

    $logs = array_slice($logs, 0, 30);

    header('Content-Type: application/json');
    echo json_encode($logs);
    break;


        // === LOGIN & REGISTER ===
        case 'register_student':
            $name        = trim($_POST['name'] ?? '');
            $username    = trim($_POST['username'] ?? '');
            $admissionNo = trim($_POST['admissionNo'] ?? '');
            $password    = $_POST['password'] ?? '';
            $class       = $_POST['class'] ?? '';
            $stream      = $_POST['stream'] ?? '';

            if (!$name || !$username || !$admissionNo || !$password || !$class || !$stream) {
                throw new Exception('All fields are required');
            }

            if (!in_array($class, ['Form1','Form2','Form3','Form4'])) {
                throw new Exception('Invalid class');
            }

            if (!preg_match('/^[A-Z0-9]+$/', $stream)) {
                throw new Exception('Invalid stream');
            }

            $stmt = $conn->prepare("SELECT id FROM students WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                throw new Exception('Username already exists');
            }
            $stmt->close();

            $stmt = $conn->prepare("SELECT id FROM students WHERE admissionNo = ? LIMIT 1");
            $stmt->bind_param("s", $admissionNo);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $stmt->close();
                throw new Exception('Admission number already exists');
            }
            $stmt->close();

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO students (name, username, admissionNo, password_hash, class, stream, role) VALUES (?, ?, ?, ?, ?, ?, 'student')");
            $stmt->bind_param("ssssss", $name, $username, $admissionNo, $hash, $class, $stream);

            if ($stmt->execute()) {
                echo json_encode(['ok' => true, 'msg' => '✅ Student registered']);
            } else {
                throw new Exception('Database error: ' . $stmt->error);
            }
            $stmt->close();
            break;

        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            if (!$username || !$password) {
                throw new Exception('Missing username or password');
            }

            // Students
            $stmt = $conn->prepare("SELECT id, name, password_hash, class, stream, role 
                                    FROM students WHERE username=? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res && password_verify($password, $res['password_hash'])) {
                echo json_encode([
                    'ok' => true,
                    'role' => $res['role'],
                    'id' => $res['id'],
                    'name' => $res['name'],
                    'class' => $res['class'],
                    'stream' => $res['stream']
                ]);
                break;
            }

            // Admins
            $stmt = $conn->prepare("SELECT id, name, password_hash 
                                    FROM admins WHERE username=? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($res && password_verify($password, $res['password_hash'])) {
                echo json_encode([
                    'ok' => true,
                    'role' => 'admin',
                    'id' => $res['id'],
                    'name' => $res['name']
                ]);
                break;
            }

            throw new Exception('Invalid username or password');
            break;

        case 'logout':
            session_start();
            session_unset();
            session_destroy();
            echo json_encode(['ok' => true, 'msg' => 'Logged out']);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
    exit;
}

// Clean output buffer and send response
ob_end_flush();
$conn->close();
?>