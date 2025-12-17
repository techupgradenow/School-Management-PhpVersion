<?php
/**
 * Payroll Management API
 * EduManage Pro - School/College Management System
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Database connection
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
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Create payroll table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS payroll (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_id VARCHAR(20) NOT NULL UNIQUE,
        employee_id VARCHAR(20) NOT NULL,
        employee_name VARCHAR(100) NOT NULL,
        designation VARCHAR(100),
        pay_period VARCHAR(7) NOT NULL,
        basic_salary DECIMAL(12,2) DEFAULT 0,
        hra DECIMAL(12,2) DEFAULT 0,
        da DECIMAL(12,2) DEFAULT 0,
        transport_allowance DECIMAL(12,2) DEFAULT 0,
        medical_allowance DECIMAL(12,2) DEFAULT 0,
        other_allowances DECIMAL(12,2) DEFAULT 0,
        pf DECIMAL(12,2) DEFAULT 0,
        professional_tax DECIMAL(12,2) DEFAULT 0,
        tds DECIMAL(12,2) DEFAULT 0,
        other_deductions DECIMAL(12,2) DEFAULT 0,
        leave_days INT DEFAULT 0,
        leave_deduction DECIMAL(12,2) DEFAULT 0,
        loan_emi DECIMAL(12,2) DEFAULT 0,
        gross_earnings DECIMAL(12,2) DEFAULT 0,
        total_deductions DECIMAL(12,2) DEFAULT 0,
        net_salary DECIMAL(12,2) DEFAULT 0,
        payment_mode VARCHAR(50),
        status ENUM('pending', 'processing', 'paid', 'hold') DEFAULT 'pending',
        paid_date DATE NULL,
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_employee (employee_id),
        INDEX idx_period (pay_period),
        INDEX idx_status (status)
    )
");

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo);
        break;
    case 'PUT':
        handlePut($pdo);
        break;
    case 'DELETE':
        handleDelete($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

function handleGet($pdo) {
    $id = $_GET['id'] ?? null;
    $employeeId = $_GET['employee_id'] ?? null;
    $payPeriod = $_GET['pay_period'] ?? null;
    $status = $_GET['status'] ?? null;
    $month = $_GET['month'] ?? null;
    $year = $_GET['year'] ?? null;

    if ($id) {
        // Get single record
        $stmt = $pdo->prepare("SELECT * FROM payroll WHERE payroll_id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch();

        if ($record) {
            echo json_encode(['success' => true, 'data' => formatPayrollRecord($record)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
        return;
    }

    // Build query with filters
    $where = [];
    $params = [];

    if ($employeeId) {
        $where[] = "employee_id = ?";
        $params[] = $employeeId;
    }

    if ($payPeriod) {
        $where[] = "pay_period = ?";
        $params[] = $payPeriod;
    }

    if ($month && $year) {
        $where[] = "pay_period = ?";
        $params[] = "$year-$month";
    }

    if ($status) {
        $where[] = "status = ?";
        $params[] = $status;
    }

    $sql = "SELECT * FROM payroll";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY pay_period DESC, employee_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $formattedRecords = array_map('formatPayrollRecord', $records);

    // Get summary stats
    $totalGross = array_sum(array_column($records, 'gross_earnings'));
    $totalNet = array_sum(array_column($records, 'net_salary'));
    $paidCount = count(array_filter($records, fn($r) => $r['status'] === 'paid'));
    $pendingCount = count(array_filter($records, fn($r) => $r['status'] !== 'paid'));

    echo json_encode([
        'success' => true,
        'data' => $formattedRecords,
        'summary' => [
            'total_records' => count($records),
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
            'paid_count' => $paidCount,
            'pending_count' => $pendingCount
        ]
    ]);
}

function handlePost($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        return;
    }

    // Generate payroll ID
    $payrollId = $data['payroll_id'] ?? 'PAY' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    // Calculate totals
    $grossEarnings = ($data['basic_salary'] ?? 0) + ($data['hra'] ?? 0) + ($data['da'] ?? 0) +
                     ($data['transport_allowance'] ?? 0) + ($data['medical_allowance'] ?? 0) +
                     ($data['other_allowances'] ?? 0);

    $leaveDeduction = (($data['basic_salary'] ?? 0) / 30) * ($data['leave_days'] ?? 0);

    $totalDeductions = ($data['pf'] ?? 0) + ($data['professional_tax'] ?? 0) + ($data['tds'] ?? 0) +
                       ($data['other_deductions'] ?? 0) + $leaveDeduction + ($data['loan_emi'] ?? 0);

    $netSalary = $grossEarnings - $totalDeductions;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO payroll (
                payroll_id, employee_id, employee_name, designation, pay_period,
                basic_salary, hra, da, transport_allowance, medical_allowance, other_allowances,
                pf, professional_tax, tds, other_deductions, leave_days, leave_deduction, loan_emi,
                gross_earnings, total_deductions, net_salary,
                payment_mode, status, paid_date, remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $payrollId,
            $data['employee_id'],
            $data['employee_name'],
            $data['designation'] ?? null,
            $data['pay_period'],
            $data['basic_salary'] ?? 0,
            $data['hra'] ?? 0,
            $data['da'] ?? 0,
            $data['transport_allowance'] ?? 0,
            $data['medical_allowance'] ?? 0,
            $data['other_allowances'] ?? 0,
            $data['pf'] ?? 0,
            $data['professional_tax'] ?? 0,
            $data['tds'] ?? 0,
            $data['other_deductions'] ?? 0,
            $data['leave_days'] ?? 0,
            $leaveDeduction,
            $data['loan_emi'] ?? 0,
            $grossEarnings,
            $totalDeductions,
            $netSalary,
            $data['payment_mode'] ?? null,
            $data['status'] ?? 'pending',
            $data['status'] === 'paid' ? date('Y-m-d') : null,
            $data['remarks'] ?? null
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Payroll record created successfully',
            'data' => ['payroll_id' => $payrollId, 'net_salary' => $netSalary]
        ]);

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'message' => 'Duplicate payroll record for this period']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
}

function handlePut($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data || !isset($data['payroll_id'])) {
        echo json_encode(['success' => false, 'message' => 'Payroll ID is required']);
        return;
    }

    // Recalculate totals
    $grossEarnings = ($data['basic_salary'] ?? 0) + ($data['hra'] ?? 0) + ($data['da'] ?? 0) +
                     ($data['transport_allowance'] ?? 0) + ($data['medical_allowance'] ?? 0) +
                     ($data['other_allowances'] ?? 0);

    $leaveDeduction = (($data['basic_salary'] ?? 0) / 30) * ($data['leave_days'] ?? 0);

    $totalDeductions = ($data['pf'] ?? 0) + ($data['professional_tax'] ?? 0) + ($data['tds'] ?? 0) +
                       ($data['other_deductions'] ?? 0) + $leaveDeduction + ($data['loan_emi'] ?? 0);

    $netSalary = $grossEarnings - $totalDeductions;

    try {
        $stmt = $pdo->prepare("
            UPDATE payroll SET
                employee_name = ?, designation = ?, pay_period = ?,
                basic_salary = ?, hra = ?, da = ?, transport_allowance = ?,
                medical_allowance = ?, other_allowances = ?,
                pf = ?, professional_tax = ?, tds = ?, other_deductions = ?,
                leave_days = ?, leave_deduction = ?, loan_emi = ?,
                gross_earnings = ?, total_deductions = ?, net_salary = ?,
                payment_mode = ?, status = ?, paid_date = ?, remarks = ?
            WHERE payroll_id = ?
        ");

        $stmt->execute([
            $data['employee_name'],
            $data['designation'] ?? null,
            $data['pay_period'],
            $data['basic_salary'] ?? 0,
            $data['hra'] ?? 0,
            $data['da'] ?? 0,
            $data['transport_allowance'] ?? 0,
            $data['medical_allowance'] ?? 0,
            $data['other_allowances'] ?? 0,
            $data['pf'] ?? 0,
            $data['professional_tax'] ?? 0,
            $data['tds'] ?? 0,
            $data['other_deductions'] ?? 0,
            $data['leave_days'] ?? 0,
            $leaveDeduction,
            $data['loan_emi'] ?? 0,
            $grossEarnings,
            $totalDeductions,
            $netSalary,
            $data['payment_mode'] ?? null,
            $data['status'] ?? 'pending',
            $data['status'] === 'paid' ? ($data['paid_date'] ?? date('Y-m-d')) : null,
            $data['remarks'] ?? null,
            $data['payroll_id']
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Payroll record updated successfully',
            'data' => ['net_salary' => $netSalary]
        ]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo) {
    $id = $_GET['id'] ?? null;

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Payroll ID is required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM payroll WHERE payroll_id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Payroll record deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function formatPayrollRecord($record) {
    return [
        'id' => $record['payroll_id'],
        'employeeId' => $record['employee_id'],
        'employeeName' => $record['employee_name'],
        'designation' => $record['designation'],
        'payPeriod' => $record['pay_period'],
        'basic' => (float) $record['basic_salary'],
        'hra' => (float) $record['hra'],
        'da' => (float) $record['da'],
        'transport' => (float) $record['transport_allowance'],
        'medical' => (float) $record['medical_allowance'],
        'otherAllowances' => (float) $record['other_allowances'],
        'pf' => (float) $record['pf'],
        'professionalTax' => (float) $record['professional_tax'],
        'tds' => (float) $record['tds'],
        'otherDeductions' => (float) $record['other_deductions'],
        'leaveDays' => (int) $record['leave_days'],
        'leaveDeduction' => (float) $record['leave_deduction'],
        'loanEmi' => (float) $record['loan_emi'],
        'grossEarnings' => (float) $record['gross_earnings'],
        'totalDeductions' => (float) $record['total_deductions'],
        'netSalary' => (float) $record['net_salary'],
        'paymentMode' => $record['payment_mode'],
        'status' => $record['status'],
        'paidDate' => $record['paid_date'],
        'remarks' => $record['remarks'],
        'createdAt' => $record['created_at'],
        'updatedAt' => $record['updated_at']
    ];
}
?>
