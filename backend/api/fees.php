<?php
/**
 * Fees API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for fee structures and payments
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Get database connection
try {
    $db = getDB();
} catch (Exception $e) {
    sendResponse(false, 'Database connection failed', null, ['database' => $e->getMessage()]);
}

// Route requests
switch ($method) {
    case 'GET':
        handleGet($db, $_GET);
        break;

    case 'POST':
        handlePost($db, $data);
        break;

    case 'PUT':
        handlePut($db, $data);
        break;

    case 'DELETE':
        handleDelete($db, $_GET);
        break;

    default:
        sendResponse(false, 'Method not allowed', null, ['method' => 'Unsupported HTTP method']);
}

/**
 * Handle GET requests
 */
function handleGet($db, $params) {
    try {
        $action = $params['action'] ?? 'list';

        switch ($action) {
            case 'list':
                getPaymentsList($db, $params);
                break;

            case 'structures':
                getFeeStructures($db, $params);
                break;

            case 'student_fees':
                getStudentFees($db, $params);
                break;

            case 'stats':
                getFeeStats($db, $params);
                break;

            case 'receipt':
                getReceipt($db, $params);
                break;

            case 'pending':
                getPendingFees($db, $params);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get payments list
 */
function getPaymentsList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 10;
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';
    $startDate = $params['start_date'] ?? '';
    $endDate = $params['end_date'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($status)) {
        $where[] = "fee_payments.status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($search)) {
        $where[] = "(students.name LIKE :search OR fee_payments.receipt_no LIKE :search)";
        $bindings[':search'] = "%$search%";
    }

    if (!empty($startDate)) {
        $where[] = "fee_payments.payment_date >= :start_date";
        $bindings[':start_date'] = $startDate;
    }

    if (!empty($endDate)) {
        $where[] = "fee_payments.payment_date <= :end_date";
        $bindings[':end_date'] = $endDate;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total
        FROM fee_payments
        LEFT JOIN students ON fee_payments.student_id = students.id
        $whereClause
    ";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated records
    $offset = ($page - 1) * $perPage;
    $query = "
        SELECT
            fee_payments.*,
            students.name as student_name,
            students.class,
            students.section,
            students.roll_no,
            fee_structures.fee_type
        FROM fee_payments
        LEFT JOIN students ON fee_payments.student_id = students.id
        LEFT JOIN fee_structures ON fee_payments.fee_structure_id = fee_structures.id
        $whereClause
        ORDER BY fee_payments.payment_date DESC, fee_payments.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $payments = $stmt->fetchAll();

    $response = [
        'payments' => $payments,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $perPage)
        ]
    ];

    sendResponse(true, 'Payments fetched successfully', $response);
}

/**
 * Get fee structures
 */
function getFeeStructures($db, $params) {
    $class = $params['class'] ?? '';
    $isActive = isset($params['is_active']) ? (bool)$params['is_active'] : null;

    $where = [];
    $bindings = [];

    if (!empty($class)) {
        $where[] = "class = :class";
        $bindings[':class'] = $class;
    }

    if ($isActive !== null) {
        $where[] = "is_active = :is_active";
        $bindings[':is_active'] = $isActive ? 1 : 0;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "
        SELECT * FROM fee_structures
        $whereClause
        ORDER BY class, fee_type
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $structures = $stmt->fetchAll();

    sendResponse(true, 'Fee structures fetched successfully', ['structures' => $structures]);
}

/**
 * Get student fees history and status
 */
function getStudentFees($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required');
    }

    $studentId = $params['student_id'];

    // Get payments history
    $paymentsQuery = "
        SELECT
            fee_payments.*,
            fee_structures.fee_type,
            fee_structures.frequency
        FROM fee_payments
        LEFT JOIN fee_structures ON fee_payments.fee_structure_id = fee_structures.id
        WHERE fee_payments.student_id = :student_id
        ORDER BY fee_payments.payment_date DESC
    ";

    $paymentsStmt = $db->prepare($paymentsQuery);
    $paymentsStmt->execute([':student_id' => $studentId]);
    $payments = $paymentsStmt->fetchAll();

    // Calculate totals
    $totalPaid = 0;
    $totalPending = 0;

    foreach ($payments as $payment) {
        if ($payment['status'] === 'Paid') {
            $totalPaid += $payment['amount_paid'];
        } elseif (in_array($payment['status'], ['Pending', 'Overdue'])) {
            $totalPending += $payment['amount_paid'];
        }
    }

    $response = [
        'student_id' => $studentId,
        'payments' => $payments,
        'total_paid' => $totalPaid,
        'total_pending' => $totalPending
    ];

    sendResponse(true, 'Student fees fetched successfully', $response);
}

/**
 * Get fee statistics
 */
function getFeeStats($db, $params) {
    $class = $params['class'] ?? '';
    $startDate = $params['start_date'] ?? date('Y-m-01');
    $endDate = $params['end_date'] ?? date('Y-m-t');

    $where = ["fee_payments.payment_date BETWEEN :start_date AND :end_date"];
    $bindings = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if (!empty($class)) {
        $where[] = "students.class = :class";
        $bindings[':class'] = $class;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    // Total collected
    $collectedQuery = "
        SELECT SUM(amount_paid) as total
        FROM fee_payments
        LEFT JOIN students ON fee_payments.student_id = students.id
        $whereClause AND fee_payments.status = 'Paid'
    ";
    $collectedStmt = $db->prepare($collectedQuery);
    $collectedStmt->execute($bindings);
    $totalCollected = $collectedStmt->fetch()['total'] ?? 0;

    // Total pending
    $pendingQuery = "
        SELECT SUM(amount_paid) as total
        FROM fee_payments
        LEFT JOIN students ON fee_payments.student_id = students.id
        $whereClause AND fee_payments.status IN ('Pending', 'Overdue')
    ";
    $pendingStmt = $db->prepare($pendingQuery);
    $pendingStmt->execute($bindings);
    $totalPending = $pendingStmt->fetch()['total'] ?? 0;

    // Total overdue
    $overdueQuery = "
        SELECT SUM(amount_paid) as total
        FROM fee_payments
        LEFT JOIN students ON fee_payments.student_id = students.id
        $whereClause AND fee_payments.status = 'Overdue'
    ";
    $overdueStmt = $db->prepare($overdueQuery);
    $overdueStmt->execute($bindings);
    $totalOverdue = $overdueStmt->fetch()['total'] ?? 0;

    // Payment count
    $countQuery = "
        SELECT COUNT(*) as total
        FROM fee_payments
        LEFT JOIN students ON fee_payments.student_id = students.id
        $whereClause AND fee_payments.status = 'Paid'
    ";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $paymentCount = $countStmt->fetch()['total'];

    $stats = [
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'total_collected' => $totalCollected,
        'total_pending' => $totalPending,
        'total_overdue' => $totalOverdue,
        'payment_count' => $paymentCount
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Get receipt details
 */
function getReceipt($db, $params) {
    if (empty($params['receipt_no'])) {
        sendResponse(false, 'Receipt number is required');
    }

    $receiptNo = $params['receipt_no'];

    $query = "
        SELECT
            fee_payments.*,
            students.name as student_name,
            students.class,
            students.section,
            students.roll_no,
            students.parent_name,
            fee_structures.fee_type,
            fee_structures.frequency
        FROM fee_payments
        LEFT JOIN students ON fee_payments.student_id = students.id
        LEFT JOIN fee_structures ON fee_payments.fee_structure_id = fee_structures.id
        WHERE fee_payments.receipt_no = :receipt_no
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':receipt_no' => $receiptNo]);
    $receipt = $stmt->fetch();

    if (!$receipt) {
        sendResponse(false, 'Receipt not found');
    }

    sendResponse(true, 'Receipt fetched successfully', $receipt);
}

/**
 * Get pending fees list
 */
function getPendingFees($db, $params) {
    $class = $params['class'] ?? '';
    $section = $params['section'] ?? '';

    $where = ["fee_payments.status IN ('Pending', 'Overdue')"];
    $bindings = [];

    if (!empty($class)) {
        $where[] = "students.class = :class";
        $bindings[':class'] = $class;
    }

    if (!empty($section)) {
        $where[] = "students.section = :section";
        $bindings[':section'] = $section;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $query = "
        SELECT
            fee_payments.*,
            students.name as student_name,
            students.class,
            students.section,
            students.roll_no,
            students.parent_name,
            students.contact,
            fee_structures.fee_type
        FROM fee_payments
        LEFT JOIN students ON fee_payments.student_id = students.id
        LEFT JOIN fee_structures ON fee_payments.fee_structure_id = fee_structures.id
        $whereClause
        ORDER BY fee_payments.payment_date ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $pendingFees = $stmt->fetchAll();

    sendResponse(true, 'Pending fees fetched successfully', ['pending_fees' => $pendingFees]);
}

/**
 * Handle POST - Create fee structure or record payment
 */
function handlePost($db, $data) {
    try {
        $action = $data['action'] ?? 'record_payment';

        switch ($action) {
            case 'create_structure':
                createFeeStructure($db, $data);
                break;

            case 'record_payment':
                recordPayment($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Create fee structure
 */
function createFeeStructure($db, $data) {
    // Validate required fields
    $required = ['class', 'fee_type', 'amount', 'frequency'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Sanitize input
    $class = sanitizeInput($data['class']);
    $feeType = sanitizeInput($data['fee_type']);
    $amount = (float)$data['amount'];
    $frequency = sanitizeInput($data['frequency']);
    $description = isset($data['description']) ? sanitizeInput($data['description']) : null;
    $isActive = isset($data['is_active']) ? (bool)$data['is_active'] : true;

    // Insert fee structure
    $stmt = $db->prepare("
        INSERT INTO fee_structures (class, fee_type, amount, frequency, description, is_active)
        VALUES (:class, :fee_type, :amount, :frequency, :description, :is_active)
    ");

    $stmt->execute([
        ':class' => $class,
        ':fee_type' => $feeType,
        ':amount' => $amount,
        ':frequency' => $frequency,
        ':description' => $description,
        ':is_active' => $isActive ? 1 : 0
    ]);

    $feeStructureId = $db->lastInsertId();

    // Log activity
    logActivity($db, getCurrentUserId(), 'Created fee structure', 'Fees', [
        'fee_structure_id' => $feeStructureId,
        'class' => $class,
        'fee_type' => $feeType
    ]);

    sendResponse(true, 'Fee structure created successfully', ['id' => $feeStructureId]);
}

/**
 * Record payment
 */
function recordPayment($db, $data) {
    try {
        // Validate required fields
        $required = ['student_id', 'fee_structure_id', 'amount_paid', 'payment_date', 'payment_mode'];
        $errors = validateRequired($data, $required);

        if (!empty($errors)) {
            sendResponse(false, 'Validation failed', null, $errors);
        }

        // Sanitize input
        $studentId = sanitizeInput($data['student_id']);
        $feeStructureId = (int)$data['fee_structure_id'];
        $amountPaid = (float)$data['amount_paid'];
        $paymentDate = formatDateForDB($data['payment_date']);
        $paymentMode = sanitizeInput($data['payment_mode']);
        $transactionId = isset($data['transaction_id']) ? sanitizeInput($data['transaction_id']) : null;
        $remarks = isset($data['remarks']) ? sanitizeInput($data['remarks']) : null;
        $collectedBy = getCurrentUserId();
        $status = isset($data['status']) ? sanitizeInput($data['status']) : 'Paid';

        // Generate receipt number
        $receiptNo = generateReceiptNo('RCP');

        // Insert payment
        $stmt = $db->prepare("
            INSERT INTO fee_payments (student_id, fee_structure_id, amount_paid, payment_date, payment_mode, transaction_id, receipt_no, remarks, collected_by, status)
            VALUES (:student_id, :fee_structure_id, :amount_paid, :payment_date, :payment_mode, :transaction_id, :receipt_no, :remarks, :collected_by, :status)
        ");

        $stmt->execute([
            ':student_id' => $studentId,
            ':fee_structure_id' => $feeStructureId,
            ':amount_paid' => $amountPaid,
            ':payment_date' => $paymentDate,
            ':payment_mode' => $paymentMode,
            ':transaction_id' => $transactionId,
            ':receipt_no' => $receiptNo,
            ':remarks' => $remarks,
            ':collected_by' => $collectedBy,
            ':status' => $status
        ]);

        $paymentId = $db->lastInsertId();

        // Log activity
        logActivity($db, $collectedBy, 'Recorded fee payment', 'Fees', [
            'payment_id' => $paymentId,
            'student_id' => $studentId,
            'amount' => $amountPaid,
            'receipt_no' => $receiptNo
        ]);

        sendResponse(true, 'Payment recorded successfully', [
            'id' => $paymentId,
            'receipt_no' => $receiptNo
        ]);

    } catch (Exception $e) {
        sendResponse(false, 'Error recording payment', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle PUT - Update fee structure or payment
 */
function handlePut($db, $data) {
    try {
        $action = $data['action'] ?? 'update_structure';

        switch ($action) {
            case 'update_structure':
                updateFeeStructure($db, $data);
                break;

            case 'update_payment':
                updatePayment($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Update fee structure
 */
function updateFeeStructure($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Fee structure ID is required');
    }

    $id = (int)$data['id'];

    // Build update query
    $fields = [];
    $bindings = [':id' => $id];

    $updateableFields = ['class', 'fee_type', 'amount', 'frequency', 'description', 'is_active'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            if ($field === 'amount') {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = (float)$data[$field];
            } elseif ($field === 'is_active') {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = (bool)$data[$field] ? 1 : 0;
            } else {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = sanitizeInput($data[$field]);
            }
        }
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE fee_structures SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    logActivity($db, getCurrentUserId(), 'Updated fee structure', 'Fees', ['fee_structure_id' => $id]);

    sendResponse(true, 'Fee structure updated successfully', ['id' => $id]);
}

/**
 * Update payment
 */
function updatePayment($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Payment ID is required');
    }

    $id = (int)$data['id'];

    // Build update query
    $fields = [];
    $bindings = [':id' => $id];

    $updateableFields = ['amount_paid', 'payment_date', 'payment_mode', 'transaction_id', 'remarks', 'status'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            if ($field === 'amount_paid') {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = (float)$data[$field];
            } elseif ($field === 'payment_date') {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = formatDateForDB($data[$field]);
            } else {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = sanitizeInput($data[$field]);
            }
        }
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE fee_payments SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    logActivity($db, getCurrentUserId(), 'Updated fee payment', 'Fees', ['payment_id' => $id]);

    sendResponse(true, 'Payment updated successfully', ['id' => $id]);
}

/**
 * Handle DELETE - Delete fee structure or payment
 */
function handleDelete($db, $params) {
    try {
        $action = $params['action'] ?? 'delete_structure';

        switch ($action) {
            case 'delete_structure':
                if (empty($params['id'])) {
                    sendResponse(false, 'Fee structure ID is required');
                }

                $id = (int)$params['id'];
                $stmt = $db->prepare("DELETE FROM fee_structures WHERE id = :id");
                $stmt->execute([':id' => $id]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(false, 'Fee structure not found');
                }

                logActivity($db, getCurrentUserId(), 'Deleted fee structure', 'Fees', ['fee_structure_id' => $id]);
                sendResponse(true, 'Fee structure deleted successfully', ['id' => $id]);
                break;

            case 'delete_payment':
                if (empty($params['id'])) {
                    sendResponse(false, 'Payment ID is required');
                }

                $id = (int)$params['id'];
                $stmt = $db->prepare("DELETE FROM fee_payments WHERE id = :id");
                $stmt->execute([':id' => $id]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(false, 'Payment not found');
                }

                logActivity($db, getCurrentUserId(), 'Deleted fee payment', 'Fees', ['payment_id' => $id]);
                sendResponse(true, 'Payment deleted successfully', ['id' => $id]);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error deleting record', null, ['error' => $e->getMessage()]);
    }
}
?>
