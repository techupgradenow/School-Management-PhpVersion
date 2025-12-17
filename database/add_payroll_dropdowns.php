<?php
/**
 * Add Payroll-related Dropdown Values
 * EduManage Pro - School/College Management System
 */

echo "=== Adding Payroll Dropdown Values ===\n\n";

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

    // Add payroll-related dropdown categories
    $payrollCategories = [
        ['salary_type', 'Salary Type', NULL, 'Types of salary components', 1],
        ['allowance_type', 'Allowance Type', NULL, 'Types of allowances', 1],
        ['deduction_type', 'Deduction Type', NULL, 'Types of deductions', 1],
        ['pay_frequency', 'Pay Frequency', NULL, 'Payment frequency options', 1],
        ['bank_name', 'Bank Name', NULL, 'Bank names for salary transfer', 1],
        ['payroll_status', 'Payroll Status', NULL, 'Payroll processing status', 1]
    ];

    $insertCategory = $pdo->prepare("
        INSERT IGNORE INTO dropdown_categories
        (category_key, category_name, institution_type_id, description, is_system)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($payrollCategories as $cat) {
        $insertCategory->execute($cat);
    }
    echo "Added payroll dropdown categories.\n";

    // Get all category IDs
    $categories = [];
    $stmt = $pdo->query("SELECT id, category_key FROM dropdown_categories");
    while ($row = $stmt->fetch()) {
        $categories[$row['category_key']] = $row['id'];
    }

    // Prepare insert statement
    $insertValue = $pdo->prepare("
        INSERT IGNORE INTO dropdown_values (category_id, value, display_order)
        VALUES (?, ?, ?)
    ");

    // Salary Types
    if (isset($categories['salary_type'])) {
        $values = ['Basic Salary', 'Gross Salary', 'Net Salary', 'CTC', 'Take Home'];
        foreach ($values as $i => $v) {
            $insertValue->execute([$categories['salary_type'], $v, $i + 1]);
        }
        echo "Added salary types.\n";
    }

    // Allowance Types
    if (isset($categories['allowance_type'])) {
        $values = [
            'House Rent Allowance (HRA)',
            'Dearness Allowance (DA)',
            'Transport Allowance',
            'Medical Allowance',
            'Special Allowance',
            'City Compensatory Allowance',
            'Leave Travel Allowance',
            'Meal Allowance',
            'Mobile Allowance',
            'Education Allowance',
            'Conveyance Allowance',
            'Performance Bonus',
            'Incentive',
            'Overtime Pay',
            'Arrears'
        ];
        foreach ($values as $i => $v) {
            $insertValue->execute([$categories['allowance_type'], $v, $i + 1]);
        }
        echo "Added allowance types.\n";
    }

    // Deduction Types
    if (isset($categories['deduction_type'])) {
        $values = [
            'Provident Fund (PF)',
            'Professional Tax',
            'Income Tax (TDS)',
            'ESI',
            'Leave Deduction',
            'Loan EMI',
            'Advance Recovery',
            'Insurance Premium',
            'Union Fee',
            'Late Penalty',
            'Salary Advance',
            'Other Deduction'
        ];
        foreach ($values as $i => $v) {
            $insertValue->execute([$categories['deduction_type'], $v, $i + 1]);
        }
        echo "Added deduction types.\n";
    }

    // Pay Frequency
    if (isset($categories['pay_frequency'])) {
        $values = ['Monthly', 'Bi-Weekly', 'Weekly', 'Daily'];
        foreach ($values as $i => $v) {
            $insertValue->execute([$categories['pay_frequency'], $v, $i + 1]);
        }
        echo "Added pay frequency options.\n";
    }

    // Bank Names (Indian Banks)
    if (isset($categories['bank_name'])) {
        $values = [
            'State Bank of India (SBI)',
            'HDFC Bank',
            'ICICI Bank',
            'Axis Bank',
            'Kotak Mahindra Bank',
            'Punjab National Bank (PNB)',
            'Bank of Baroda',
            'Canara Bank',
            'Union Bank of India',
            'Indian Bank',
            'Bank of India',
            'Central Bank of India',
            'IndusInd Bank',
            'Yes Bank',
            'IDBI Bank',
            'Federal Bank',
            'South Indian Bank',
            'RBL Bank',
            'Bandhan Bank',
            'Other'
        ];
        foreach ($values as $i => $v) {
            $insertValue->execute([$categories['bank_name'], $v, $i + 1]);
        }
        echo "Added bank names.\n";
    }

    // Payroll Status
    if (isset($categories['payroll_status'])) {
        $values = ['Pending', 'Processing', 'Paid', 'On Hold', 'Cancelled'];
        foreach ($values as $i => $v) {
            $insertValue->execute([$categories['payroll_status'], $v, $i + 1]);
        }
        echo "Added payroll status options.\n";
    }

    // Count total values added
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM dropdown_values");
    $totalCount = $stmt->fetch()['count'];

    echo "\n=== Summary ===\n";
    echo "Total dropdown values in database: $totalCount\n";

    // Show payroll categories
    echo "\n=== Payroll Categories ===\n";
    $stmt = $pdo->query("
        SELECT dc.category_key, dc.category_name, COUNT(dv.id) as value_count
        FROM dropdown_categories dc
        LEFT JOIN dropdown_values dv ON dc.id = dv.category_id
        WHERE dc.category_key IN ('salary_type', 'allowance_type', 'deduction_type', 'pay_frequency', 'bank_name', 'payroll_status')
        GROUP BY dc.id
        ORDER BY dc.category_name
    ");
    while ($row = $stmt->fetch()) {
        echo "- {$row['category_name']}: {$row['value_count']} values\n";
    }

    echo "\n=== Payroll Dropdowns Added Successfully! ===\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage() . "\n");
}
?>
