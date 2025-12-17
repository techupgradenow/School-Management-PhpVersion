<?php
/**
 * Add More Default Dropdown Values
 * EduManage Pro - School/College Management System
 *
 * This script adds comprehensive default values for all dropdowns
 * Run this AFTER run_institution_setup.php
 */

echo "=== Adding More Default Dropdown Values ===\n\n";

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=edumanage_pro;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    echo "Connected to database successfully.\n\n";

    // Get category IDs
    $categories = [];
    $stmt = $pdo->query("SELECT id, category_key, institution_type_id FROM dropdown_categories");
    while ($row = $stmt->fetch()) {
        $key = $row['category_key'] . '_' . ($row['institution_type_id'] ?? 'null');
        $categories[$key] = $row['id'];
    }

    // Helper function to insert values
    function insertValues($pdo, $categoryId, $values, $categoryName) {
        if (!$categoryId) {
            echo "Category ID not found for $categoryName\n";
            return 0;
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO dropdown_values (category_id, value, display_order) VALUES (?, ?, ?)");
        $count = 0;

        // Get current max display_order
        $maxOrder = $pdo->query("SELECT MAX(display_order) FROM dropdown_values WHERE category_id = $categoryId")->fetchColumn();
        $order = ($maxOrder ?? 0) + 1;

        foreach ($values as $value) {
            try {
                $stmt->execute([$categoryId, $value, $order++]);
                if ($stmt->rowCount() > 0) $count++;
            } catch (Exception $e) {
                // Ignore duplicates
            }
        }

        if ($count > 0) {
            echo "Added $count new values to $categoryName\n";
        }
        return $count;
    }

    // ============================================================
    // SCHOOL-SPECIFIC DEFAULTS
    // ============================================================
    echo "\n--- SCHOOL DEFAULTS ---\n";

    // More School Classes (if needed)
    $additionalClasses = ['Pre-Nursery', 'Play Group'];
    insertValues($pdo, $categories['class_1'] ?? null, $additionalClasses, 'School Classes');

    // More School Sections
    $additionalSections = ['F', 'G', 'H'];
    insertValues($pdo, $categories['section_1'] ?? null, $additionalSections, 'School Sections');

    // More School Subjects
    $additionalSubjects = [
        'Environmental Science', 'General Knowledge', 'Moral Science',
        'Drawing & Painting', 'Music', 'Dance', 'Sanskrit', 'French',
        'German', 'Economics', 'Accountancy', 'Business Studies',
        'Political Science', 'History', 'Geography', 'Psychology',
        'Sociology', 'Home Science', 'Agriculture', 'Vocational Studies'
    ];
    insertValues($pdo, $categories['subject_1'] ?? null, $additionalSubjects, 'School Subjects');

    // ============================================================
    // COLLEGE-SPECIFIC DEFAULTS
    // ============================================================
    echo "\n--- COLLEGE DEFAULTS ---\n";

    // More College Departments
    $moreDepartments = [
        'Physics', 'Chemistry', 'Mathematics', 'Biology',
        'Commerce', 'Economics', 'English', 'History',
        'Political Science', 'Psychology', 'Sociology',
        'Mass Communication', 'Journalism', 'Law',
        'Architecture', 'Pharmacy', 'Nursing', 'Education',
        'Fine Arts', 'Performing Arts', 'Sports Science'
    ];
    insertValues($pdo, $categories['department_2'] ?? null, $moreDepartments, 'College Departments');

    // More College Courses
    $moreCourses = [
        // Undergraduate
        'BA', 'B.Ed', 'BBA', 'BCA', 'B.Arch', 'B.Pharm', 'B.Nursing',
        'LLB', 'BJMC', 'BFA', 'B.Design', 'B.Voc',
        // Postgraduate
        'MA', 'M.Ed', 'M.Arch', 'M.Pharm', 'LLM', 'MJMC', 'MFA',
        // Diploma
        'Diploma in Engineering', 'Diploma in Pharmacy',
        'Diploma in Nursing', 'Diploma in Education',
        // Doctorate
        'Ph.D', 'M.Phil'
    ];
    insertValues($pdo, $categories['course_2'] ?? null, $moreCourses, 'College Courses');

    // College Subjects (create category if not exists)
    $collegeSubjectCatId = $categories['subject_college_2'] ?? null;
    if ($collegeSubjectCatId) {
        $collegeSubjects = [
            // Engineering
            'Data Structures', 'Algorithms', 'Database Management', 'Operating Systems',
            'Computer Networks', 'Software Engineering', 'Artificial Intelligence',
            'Machine Learning', 'Web Development', 'Mobile App Development',
            'Cloud Computing', 'Cyber Security', 'Digital Electronics',
            'Microprocessors', 'Control Systems', 'Power Electronics',
            'Thermodynamics', 'Fluid Mechanics', 'Strength of Materials',
            'Engineering Drawing', 'Engineering Mathematics',
            // Science
            'Organic Chemistry', 'Inorganic Chemistry', 'Physical Chemistry',
            'Quantum Physics', 'Classical Mechanics', 'Electromagnetism',
            'Calculus', 'Linear Algebra', 'Statistics', 'Probability',
            'Molecular Biology', 'Genetics', 'Microbiology', 'Biochemistry',
            // Commerce
            'Financial Accounting', 'Cost Accounting', 'Management Accounting',
            'Business Law', 'Corporate Law', 'Taxation', 'Auditing',
            'Financial Management', 'Marketing Management', 'Human Resource Management',
            'Operations Management', 'Strategic Management', 'Entrepreneurship',
            // Arts
            'English Literature', 'Hindi Literature', 'World History',
            'Indian History', 'Political Theory', 'International Relations',
            'Microeconomics', 'Macroeconomics', 'Development Economics',
            'Social Psychology', 'Abnormal Psychology', 'Research Methodology'
        ];
        insertValues($pdo, $collegeSubjectCatId, $collegeSubjects, 'College Subjects');
    }

    // Degree Types
    $degreeId = $categories['degree_2'] ?? null;
    if ($degreeId) {
        $degrees = [
            'Bachelor of Arts (BA)', 'Bachelor of Science (B.Sc)',
            'Bachelor of Commerce (B.Com)', 'Bachelor of Technology (B.Tech)',
            'Bachelor of Engineering (BE)', 'Bachelor of Business Administration (BBA)',
            'Bachelor of Computer Applications (BCA)', 'Bachelor of Education (B.Ed)',
            'Master of Arts (MA)', 'Master of Science (M.Sc)',
            'Master of Commerce (M.Com)', 'Master of Technology (M.Tech)',
            'Master of Business Administration (MBA)', 'Master of Computer Applications (MCA)',
            'Doctor of Philosophy (Ph.D)', 'Master of Philosophy (M.Phil)',
            'Diploma', 'Post Graduate Diploma', 'Certificate Course'
        ];
        insertValues($pdo, $degreeId, $degrees, 'Degree Types');
    }

    // Specializations
    $specId = $categories['specialization_2'] ?? null;
    if ($specId) {
        $specializations = [
            // Engineering
            'Computer Science & Engineering', 'Information Technology',
            'Electronics & Communication', 'Electrical Engineering',
            'Mechanical Engineering', 'Civil Engineering',
            'Chemical Engineering', 'Biotechnology',
            'Artificial Intelligence & ML', 'Data Science',
            'Cyber Security', 'Cloud Computing',
            // Management
            'Finance', 'Marketing', 'Human Resources',
            'Operations', 'International Business',
            'Healthcare Management', 'IT Management',
            // Science
            'Applied Physics', 'Applied Chemistry',
            'Applied Mathematics', 'Biotechnology',
            'Environmental Science', 'Food Technology'
        ];
        insertValues($pdo, $specId, $specializations, 'Specializations');
    }

    // ============================================================
    // COMMON DEFAULTS (Both School & College)
    // ============================================================
    echo "\n--- COMMON DEFAULTS ---\n";

    // More Designations
    $moreDesignations = [
        'Professor', 'Associate Professor', 'Assistant Professor',
        'Lecturer', 'Guest Faculty', 'Lab Technician',
        'Office Superintendent', 'Peon', 'Security Guard',
        'Driver', 'Gardener', 'Hostel Warden', 'Sports Coach',
        'Counselor', 'Nurse', 'IT Administrator', 'Registrar',
        'Dean', 'Director', 'Chancellor', 'Vice Chancellor'
    ];
    insertValues($pdo, $categories['designation_null'] ?? null, $moreDesignations, 'Designations');

    // More Fee Types
    $moreFeeTypes = [
        'Hostel Fee', 'Mess Fee', 'Development Fee', 'Building Fee',
        'Security Deposit', 'Caution Money', 'Late Fee', 'Fine',
        'Registration Fee', 'Prospectus Fee', 'Identity Card Fee',
        'Magazine Fee', 'Cultural Fee', 'Alumni Fee', 'Placement Fee',
        'Internet Fee', 'Computer Lab Fee', 'Workshop Fee'
    ];
    insertValues($pdo, $categories['fee_type_null'] ?? null, $moreFeeTypes, 'Fee Types');

    // More Payment Modes
    $morePaymentModes = [
        'NEFT', 'RTGS', 'IMPS', 'Net Banking', 'Debit Card',
        'Credit Card', 'Mobile Wallet', 'PayTM', 'PhonePe',
        'Google Pay', 'Bank Transfer', 'Demand Draft'
    ];
    insertValues($pdo, $categories['payment_mode_null'] ?? null, $morePaymentModes, 'Payment Modes');

    // More Exam Types
    $moreExamTypes = [
        // School
        'Weekly Test', 'Monthly Test', 'Surprise Test',
        'Practical Exam', 'Oral Exam', 'Project Submission',
        'Assignment', 'Board Exam',
        // College
        'Internal Assessment', 'External Exam', 'Semester Exam',
        'Supplementary Exam', 'Re-Exam', 'Improvement Exam',
        'Viva Voce', 'Lab Practical', 'Project Defense',
        'Comprehensive Exam', 'Entrance Exam', 'Aptitude Test'
    ];
    insertValues($pdo, $categories['exam_type_null'] ?? null, $moreExamTypes, 'Exam Types');

    // Grades
    $gradeId = $categories['grade_null'] ?? null;
    if ($gradeId) {
        $grades = [
            'A+ (Outstanding)', 'A (Excellent)', 'A- (Very Good)',
            'B+ (Good)', 'B (Above Average)', 'B- (Average)',
            'C+ (Satisfactory)', 'C (Pass)', 'C- (Below Average)',
            'D (Minimum Pass)', 'F (Fail)', 'I (Incomplete)',
            'W (Withdrawn)', 'P (Pass)', 'NP (Not Pass)',
            'O (Outstanding - 10 CGPA)', 'S (Satisfactory)'
        ];
        insertValues($pdo, $gradeId, $grades, 'Grades');
    }

    // Status options
    $statusId = $categories['status_null'] ?? null;
    if ($statusId) {
        $statuses = [
            'Active', 'Inactive', 'Pending', 'Approved', 'Rejected',
            'On Leave', 'Suspended', 'Terminated', 'Graduated',
            'Dropped Out', 'Transferred', 'Deceased', 'Alumni'
        ];
        insertValues($pdo, $statusId, $statuses, 'Status Options');
    }

    // Nationalities (create if category exists)
    // Add Nationality category if not exists
    $pdo->exec("
        INSERT IGNORE INTO dropdown_categories (category_key, category_name, institution_type_id, description, is_system)
        VALUES ('nationality', 'Nationality', NULL, 'Country/Nationality options', 1)
    ");

    // Get the new category ID
    $stmt = $pdo->query("SELECT id FROM dropdown_categories WHERE category_key = 'nationality'");
    $nationalityId = $stmt->fetchColumn();

    if ($nationalityId) {
        $nationalities = [
            'Indian', 'American', 'British', 'Canadian', 'Australian',
            'German', 'French', 'Japanese', 'Chinese', 'Korean',
            'Russian', 'Brazilian', 'Mexican', 'Italian', 'Spanish',
            'Dutch', 'Swedish', 'Norwegian', 'Danish', 'Finnish',
            'Swiss', 'Austrian', 'Belgian', 'Irish', 'Scottish',
            'Nepalese', 'Bangladeshi', 'Pakistani', 'Sri Lankan', 'Other'
        ];
        insertValues($pdo, $nationalityId, $nationalities, 'Nationalities');
    }

    // Marital Status
    $pdo->exec("
        INSERT IGNORE INTO dropdown_categories (category_key, category_name, institution_type_id, description, is_system)
        VALUES ('marital_status', 'Marital Status', NULL, 'Marital status options', 1)
    ");

    $stmt = $pdo->query("SELECT id FROM dropdown_categories WHERE category_key = 'marital_status'");
    $maritalId = $stmt->fetchColumn();

    if ($maritalId) {
        $maritalStatuses = ['Single', 'Married', 'Divorced', 'Widowed', 'Separated'];
        insertValues($pdo, $maritalId, $maritalStatuses, 'Marital Status');
    }

    // ============================================================
    // SHOW SUMMARY
    // ============================================================
    echo "\n=== SUMMARY ===\n";

    $stmt = $pdo->query("
        SELECT dc.category_name, dc.category_key,
               COALESCE(it.name, 'Common') as institution,
               COUNT(dv.id) as value_count
        FROM dropdown_categories dc
        LEFT JOIN dropdown_values dv ON dc.id = dv.category_id
        LEFT JOIN institution_types it ON dc.institution_type_id = it.id
        GROUP BY dc.id
        ORDER BY institution, dc.category_name
    ");

    $currentInst = '';
    while ($row = $stmt->fetch()) {
        if ($currentInst !== $row['institution']) {
            $currentInst = $row['institution'];
            echo "\n[$currentInst]\n";
        }
        echo "  - {$row['category_name']}: {$row['value_count']} values\n";
    }

    // Total counts
    $totalCategories = $pdo->query("SELECT COUNT(*) FROM dropdown_categories")->fetchColumn();
    $totalValues = $pdo->query("SELECT COUNT(*) FROM dropdown_values")->fetchColumn();

    echo "\n=== TOTALS ===\n";
    echo "Total Categories: $totalCategories\n";
    echo "Total Dropdown Values: $totalValues\n";

    echo "\n=== Done! ===\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>
