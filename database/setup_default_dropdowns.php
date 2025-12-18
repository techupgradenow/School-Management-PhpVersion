<?php
/**
 * Default Dropdown Values Setup Script
 * EduManage Pro - School/College Management System
 *
 * This script inserts default dropdown values for School and College modes.
 * Uses INSERT IGNORE to prevent duplicate records.
 * Run this during initial setup.
 */

echo "=== EduManage Pro - Default Dropdown Setup ===\n\n";

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
    echo "Connected to database.\n\n";

    // Get institution type IDs
    $stmt = $pdo->query("SELECT id, name FROM institution_types");
    $institutionTypes = [];
    while ($row = $stmt->fetch()) {
        $institutionTypes[$row['name']] = $row['id'];
    }

    $schoolTypeId = $institutionTypes['School'] ?? 1;
    $collegeTypeId = $institutionTypes['College'] ?? 2;

    echo "School Type ID: $schoolTypeId\n";
    echo "College Type ID: $collegeTypeId\n\n";

    // ============================================
    // DEFINE ALL DEFAULT DROPDOWN VALUES
    // ============================================

    $defaults = [
        // ==== SCHOOL-SPECIFIC DROPDOWNS ====
        'class' => [
            'name' => 'Class',
            'institution_type_id' => $schoolTypeId,
            'description' => 'Academic classes for school',
            'values' => [
                'LKG',
                'UKG',
                '1st Standard',
                '2nd Standard',
                '3rd Standard',
                '4th Standard',
                '5th Standard',
                '6th Standard',
                '7th Standard',
                '8th Standard',
                '9th Standard',
                '10th Standard',
                '11th Standard',
                '12th Standard'
            ]
        ],

        'section' => [
            'name' => 'Section',
            'institution_type_id' => $schoolTypeId,
            'description' => 'Class sections for school',
            'values' => ['A', 'B', 'C', 'D', 'E', 'F']
        ],

        'subject' => [
            'name' => 'Subject',
            'institution_type_id' => $schoolTypeId,
            'description' => 'Academic subjects for school',
            'values' => [
                'English',
                'Hindi',
                'Mathematics',
                'Science',
                'Social Studies',
                'Computer Science',
                'Physical Education',
                'Art & Craft',
                'Music',
                'Moral Science',
                'Environmental Studies',
                'General Knowledge',
                'Physics',
                'Chemistry',
                'Biology',
                'History',
                'Geography',
                'Civics',
                'Economics',
                'Accountancy',
                'Business Studies',
                'Sanskrit',
                'French',
                'German'
            ]
        ],

        // ==== COLLEGE-SPECIFIC DROPDOWNS ====
        'course' => [
            'name' => 'Course',
            'institution_type_id' => $collegeTypeId,
            'description' => 'Academic courses for college',
            'values' => [
                'B.Tech',
                'B.E.',
                'B.Sc',
                'B.Com',
                'B.A.',
                'BBA',
                'BCA',
                'B.Arch',
                'B.Pharm',
                'MBBS',
                'BDS',
                'LLB',
                'B.Ed',
                'M.Tech',
                'M.Sc',
                'M.Com',
                'M.A.',
                'MBA',
                'MCA',
                'M.Pharm',
                'MD',
                'LLM',
                'M.Ed',
                'Ph.D'
            ]
        ],

        'year' => [
            'name' => 'Year',
            'institution_type_id' => $collegeTypeId,
            'description' => 'Academic year for college',
            'values' => [
                '1st Year',
                '2nd Year',
                '3rd Year',
                '4th Year',
                '5th Year',
                '6th Year'
            ]
        ],

        'semester' => [
            'name' => 'Semester',
            'institution_type_id' => $collegeTypeId,
            'description' => 'Academic semester for college',
            'values' => [
                'Semester 1',
                'Semester 2',
                'Semester 3',
                'Semester 4',
                'Semester 5',
                'Semester 6',
                'Semester 7',
                'Semester 8'
            ]
        ],

        'department' => [
            'name' => 'Department',
            'institution_type_id' => $collegeTypeId,
            'description' => 'Academic departments for college',
            'values' => [
                'Computer Science & Engineering',
                'Electronics & Communication',
                'Electrical Engineering',
                'Mechanical Engineering',
                'Civil Engineering',
                'Information Technology',
                'Chemical Engineering',
                'Biotechnology',
                'Physics',
                'Chemistry',
                'Mathematics',
                'Commerce',
                'Management',
                'Arts & Humanities',
                'Law',
                'Medicine',
                'Pharmacy'
            ]
        ],

        'degree' => [
            'name' => 'Degree',
            'institution_type_id' => $collegeTypeId,
            'description' => 'Degree types for college',
            'values' => [
                'Undergraduate (UG)',
                'Postgraduate (PG)',
                'Doctorate (Ph.D)',
                'Diploma',
                'Certificate'
            ]
        ],

        'subject_college' => [
            'name' => 'Subject (College)',
            'institution_type_id' => $collegeTypeId,
            'description' => 'Academic subjects for college',
            'values' => [
                'Data Structures',
                'Algorithms',
                'Database Management',
                'Operating Systems',
                'Computer Networks',
                'Software Engineering',
                'Web Development',
                'Machine Learning',
                'Artificial Intelligence',
                'Digital Electronics',
                'Microprocessors',
                'Control Systems',
                'Power Systems',
                'Thermodynamics',
                'Fluid Mechanics',
                'Structural Analysis',
                'Financial Accounting',
                'Business Law',
                'Marketing Management',
                'Human Resource Management',
                'Organic Chemistry',
                'Inorganic Chemistry',
                'Calculus',
                'Linear Algebra',
                'Statistics'
            ]
        ],

        // ==== COMMON DROPDOWNS (Both School & College) ====
        'gender' => [
            'name' => 'Gender',
            'institution_type_id' => null,
            'description' => 'Gender options',
            'values' => ['Male', 'Female', 'Other']
        ],

        'blood_group' => [
            'name' => 'Blood Group',
            'institution_type_id' => null,
            'description' => 'Blood group options',
            'values' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
        ],

        'religion' => [
            'name' => 'Religion',
            'institution_type_id' => null,
            'description' => 'Religion options',
            'values' => ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist', 'Jain', 'Other']
        ],

        'category' => [
            'name' => 'Category',
            'institution_type_id' => null,
            'description' => 'Student category',
            'values' => ['General', 'OBC', 'SC', 'ST', 'EWS']
        ],

        'nationality' => [
            'name' => 'Nationality',
            'institution_type_id' => null,
            'description' => 'Nationality options',
            'values' => [
                'Indian',
                'American',
                'British',
                'Canadian',
                'Australian',
                'German',
                'French',
                'Japanese',
                'Chinese',
                'Other'
            ]
        ],

        'marital_status' => [
            'name' => 'Marital Status',
            'institution_type_id' => null,
            'description' => 'Marital status options',
            'values' => ['Single', 'Married', 'Divorced', 'Widowed', 'Other']
        ],

        'designation' => [
            'name' => 'Designation',
            'institution_type_id' => null,
            'description' => 'Staff designation',
            'values' => [
                'Principal',
                'Vice Principal',
                'HOD',
                'Professor',
                'Associate Professor',
                'Assistant Professor',
                'Senior Teacher',
                'Teacher',
                'Junior Teacher',
                'Lab Assistant',
                'Librarian',
                'Accountant',
                'Clerk',
                'Peon',
                'Security Guard',
                'Driver',
                'Gardener'
            ]
        ],

        'fee_type' => [
            'name' => 'Fee Type',
            'institution_type_id' => null,
            'description' => 'Types of fees',
            'values' => [
                'Tuition Fee',
                'Admission Fee',
                'Registration Fee',
                'Exam Fee',
                'Lab Fee',
                'Library Fee',
                'Sports Fee',
                'Transport Fee',
                'Hostel Fee',
                'Mess Fee',
                'Caution Deposit',
                'Development Fee',
                'Computer Fee',
                'Annual Fee',
                'Late Fee'
            ]
        ],

        'payment_mode' => [
            'name' => 'Payment Mode',
            'institution_type_id' => null,
            'description' => 'Payment methods',
            'values' => [
                'Cash',
                'Credit Card',
                'Debit Card',
                'Net Banking',
                'UPI',
                'Cheque',
                'Demand Draft',
                'Bank Transfer',
                'Wallet'
            ]
        ],

        'exam_type' => [
            'name' => 'Exam Type',
            'institution_type_id' => null,
            'description' => 'Types of examinations',
            'values' => [
                'Unit Test 1',
                'Unit Test 2',
                'Unit Test 3',
                'Mid Term',
                'Pre-Final',
                'Final',
                'Practical',
                'Viva',
                'Assignment',
                'Project',
                'Internal Assessment'
            ]
        ],

        'grade' => [
            'name' => 'Grade',
            'institution_type_id' => null,
            'description' => 'Grading system',
            'values' => [
                'A+', 'A', 'A-',
                'B+', 'B', 'B-',
                'C+', 'C', 'C-',
                'D', 'E', 'F'
            ]
        ],

        'status' => [
            'name' => 'Status',
            'institution_type_id' => null,
            'description' => 'General status options',
            'values' => ['Active', 'Inactive', 'Pending', 'Completed', 'Cancelled']
        ]
    ];

    // ============================================
    // INSERT CATEGORIES AND VALUES
    // ============================================

    $insertedCategories = 0;
    $insertedValues = 0;
    $skippedValues = 0;

    foreach ($defaults as $categoryKey => $categoryData) {
        // Check if category exists
        $stmt = $pdo->prepare("
            SELECT id FROM dropdown_categories
            WHERE category_key = ?
            AND (institution_type_id = ? OR (institution_type_id IS NULL AND ? IS NULL))
        ");
        $stmt->execute([$categoryKey, $categoryData['institution_type_id'], $categoryData['institution_type_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $categoryId = $existing['id'];
        } else {
            // Insert new category
            $stmt = $pdo->prepare("
                INSERT INTO dropdown_categories
                (category_key, category_name, institution_type_id, description, is_system, is_active)
                VALUES (?, ?, ?, ?, 1, 1)
            ");
            $stmt->execute([
                $categoryKey,
                $categoryData['name'],
                $categoryData['institution_type_id'],
                $categoryData['description']
            ]);
            $categoryId = $pdo->lastInsertId();
            $insertedCategories++;
            echo "Created category: {$categoryData['name']}\n";
        }

        // Insert values (using INSERT IGNORE to prevent duplicates)
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO dropdown_values (category_id, value, display_order, is_active)
            VALUES (?, ?, ?, 1)
        ");

        foreach ($categoryData['values'] as $order => $value) {
            $stmt->execute([$categoryId, $value, $order + 1]);
            if ($stmt->rowCount() > 0) {
                $insertedValues++;
            } else {
                $skippedValues++;
            }
        }
    }

    echo "\n=== Setup Complete ===\n";
    echo "New categories created: $insertedCategories\n";
    echo "New values inserted: $insertedValues\n";
    echo "Duplicate values skipped: $skippedValues\n";

    // Show summary by institution type
    echo "\n=== Summary by Institution Type ===\n";

    echo "\n--- SCHOOL Dropdowns ---\n";
    $stmt = $pdo->prepare("
        SELECT dc.category_key, dc.category_name, COUNT(dv.id) as value_count
        FROM dropdown_categories dc
        LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
        WHERE dc.institution_type_id = ?
        GROUP BY dc.id
        ORDER BY dc.category_name
    ");
    $stmt->execute([$schoolTypeId]);
    while ($row = $stmt->fetch()) {
        echo "  {$row['category_name']}: {$row['value_count']} values\n";
    }

    echo "\n--- COLLEGE Dropdowns ---\n";
    $stmt->execute([$collegeTypeId]);
    while ($row = $stmt->fetch()) {
        echo "  {$row['category_name']}: {$row['value_count']} values\n";
    }

    echo "\n--- COMMON Dropdowns (Both) ---\n";
    $stmt = $pdo->prepare("
        SELECT dc.category_key, dc.category_name, COUNT(dv.id) as value_count
        FROM dropdown_categories dc
        LEFT JOIN dropdown_values dv ON dc.id = dv.category_id AND dv.is_active = 1
        WHERE dc.institution_type_id IS NULL
        GROUP BY dc.id
        ORDER BY dc.category_name
    ");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        echo "  {$row['category_name']}: {$row['value_count']} values\n";
    }

    // Total count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dropdown_values WHERE is_active = 1");
    $total = $stmt->fetch()['total'];
    echo "\n=== Total Active Dropdown Values: $total ===\n";

} catch (PDOException $e) {
    die("\nDatabase Error: " . $e->getMessage() . "\n");
}
?>
