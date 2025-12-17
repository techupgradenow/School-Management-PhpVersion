<?php
/**
 * Hostel API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for hostel blocks, rooms and allocations
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
        $action = $params['action'] ?? 'blocks';

        switch ($action) {
            case 'blocks':
                getBlocksList($db, $params);
                break;

            case 'single_block':
                getSingleBlock($db, $params);
                break;

            case 'rooms':
                getRoomsList($db, $params);
                break;

            case 'single_room':
                getSingleRoom($db, $params);
                break;

            case 'allocations':
                getAllocationsList($db, $params);
                break;

            case 'student_allocation':
                getStudentAllocation($db, $params);
                break;

            case 'stats':
                getHostelStats($db);
                break;

            case 'available_rooms':
                getAvailableRooms($db, $params);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get blocks list
 */
function getBlocksList($db, $params) {
    $blockType = $params['block_type'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($blockType)) {
        $where[] = "block_type = :block_type";
        $bindings[':block_type'] = $blockType;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "SELECT * FROM hostel_blocks $whereClause ORDER BY block_name ASC";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $blocks = $stmt->fetchAll();

    // Get room count and occupancy for each block
    foreach ($blocks as &$block) {
        $roomsStmt = $db->prepare("
            SELECT
                COUNT(*) as total_rooms,
                SUM(capacity) as total_capacity,
                SUM(occupied) as total_occupied
            FROM hostel_rooms
            WHERE block_id = :block_id
        ");
        $roomsStmt->execute([':block_id' => $block['id']]);
        $roomStats = $roomsStmt->fetch();

        $block['room_count'] = $roomStats['total_rooms'];
        $block['total_capacity'] = $roomStats['total_capacity'] ?? 0;
        $block['total_occupied'] = $roomStats['total_occupied'] ?? 0;
        $block['available'] = ($roomStats['total_capacity'] ?? 0) - ($roomStats['total_occupied'] ?? 0);
    }

    sendResponse(true, 'Blocks fetched successfully', ['blocks' => $blocks]);
}

/**
 * Get single block
 */
function getSingleBlock($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Block ID is required');
    }

    $stmt = $db->prepare("SELECT * FROM hostel_blocks WHERE id = :id");
    $stmt->execute([':id' => $params['id']]);
    $block = $stmt->fetch();

    if (!$block) {
        sendResponse(false, 'Block not found');
    }

    sendResponse(true, 'Block fetched successfully', $block);
}

/**
 * Get rooms list
 */
function getRoomsList($db, $params) {
    $blockId = $params['block_id'] ?? '';
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($blockId)) {
        $where[] = "block_id = :block_id";
        $bindings[':block_id'] = $blockId;
    }

    if (!empty($status)) {
        $where[] = "status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($search)) {
        $where[] = "room_no LIKE :search";
        $bindings[':search'] = "%$search%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "
        SELECT
            hostel_rooms.*,
            hostel_blocks.block_name,
            hostel_blocks.block_type
        FROM hostel_rooms
        LEFT JOIN hostel_blocks ON hostel_rooms.block_id = hostel_blocks.id
        $whereClause
        ORDER BY hostel_rooms.floor, hostel_rooms.room_no
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $rooms = $stmt->fetchAll();

    sendResponse(true, 'Rooms fetched successfully', ['rooms' => $rooms]);
}

/**
 * Get single room
 */
function getSingleRoom($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Room ID is required');
    }

    $roomStmt = $db->prepare("
        SELECT
            hostel_rooms.*,
            hostel_blocks.block_name,
            hostel_blocks.block_type
        FROM hostel_rooms
        LEFT JOIN hostel_blocks ON hostel_rooms.block_id = hostel_blocks.id
        WHERE hostel_rooms.id = :id
    ");
    $roomStmt->execute([':id' => $params['id']]);
    $room = $roomStmt->fetch();

    if (!$room) {
        sendResponse(false, 'Room not found');
    }

    // Get current occupants
    $occupantsStmt = $db->prepare("
        SELECT
            hostel_allocations.*,
            students.name as student_name,
            students.class,
            students.section,
            students.contact
        FROM hostel_allocations
        LEFT JOIN students ON hostel_allocations.student_id = students.id
        WHERE hostel_allocations.room_id = :room_id AND hostel_allocations.status = 'Active'
    ");
    $occupantsStmt->execute([':room_id' => $room['id']]);
    $room['occupants'] = $occupantsStmt->fetchAll();

    sendResponse(true, 'Room fetched successfully', $room);
}

/**
 * Get allocations list
 */
function getAllocationsList($db, $params) {
    $blockId = $params['block_id'] ?? '';
    $roomId = $params['room_id'] ?? '';
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($blockId)) {
        $where[] = "hostel_rooms.block_id = :block_id";
        $bindings[':block_id'] = $blockId;
    }

    if (!empty($roomId)) {
        $where[] = "hostel_allocations.room_id = :room_id";
        $bindings[':room_id'] = $roomId;
    }

    if (!empty($status)) {
        $where[] = "hostel_allocations.status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($search)) {
        $where[] = "students.name LIKE :search";
        $bindings[':search'] = "%$search%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "
        SELECT
            hostel_allocations.*,
            students.name as student_name,
            students.class,
            students.section,
            students.roll_no,
            students.contact,
            hostel_rooms.room_no,
            hostel_rooms.room_type,
            hostel_rooms.floor,
            hostel_blocks.block_name
        FROM hostel_allocations
        LEFT JOIN students ON hostel_allocations.student_id = students.id
        LEFT JOIN hostel_rooms ON hostel_allocations.room_id = hostel_rooms.id
        LEFT JOIN hostel_blocks ON hostel_rooms.block_id = hostel_blocks.id
        $whereClause
        ORDER BY hostel_allocations.allocation_date DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $allocations = $stmt->fetchAll();

    sendResponse(true, 'Allocations fetched successfully', ['allocations' => $allocations]);
}

/**
 * Get student allocation
 */
function getStudentAllocation($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required');
    }

    $studentId = $params['student_id'];

    $query = "
        SELECT
            hostel_allocations.*,
            hostel_rooms.room_no,
            hostel_rooms.room_type,
            hostel_rooms.floor,
            hostel_rooms.monthly_fee,
            hostel_blocks.block_name,
            hostel_blocks.block_type,
            hostel_blocks.warden_name,
            hostel_blocks.warden_contact
        FROM hostel_allocations
        LEFT JOIN hostel_rooms ON hostel_allocations.room_id = hostel_rooms.id
        LEFT JOIN hostel_blocks ON hostel_rooms.block_id = hostel_blocks.id
        WHERE hostel_allocations.student_id = :student_id
        ORDER BY hostel_allocations.allocation_date DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':student_id' => $studentId]);
    $allocations = $stmt->fetchAll();

    sendResponse(true, 'Student allocations fetched successfully', ['allocations' => $allocations]);
}

/**
 * Get hostel statistics
 */
function getHostelStats($db) {
    // Total blocks
    $blocksStmt = $db->query("SELECT COUNT(*) as total FROM hostel_blocks");
    $totalBlocks = $blocksStmt->fetch()['total'];

    // Total rooms
    $roomsStmt = $db->query("SELECT COUNT(*) as total FROM hostel_rooms");
    $totalRooms = $roomsStmt->fetch()['total'];

    // Total capacity
    $capacityStmt = $db->query("SELECT SUM(capacity) as total FROM hostel_rooms");
    $totalCapacity = $capacityStmt->fetch()['total'] ?? 0;

    // Total occupied
    $occupiedStmt = $db->query("SELECT SUM(occupied) as total FROM hostel_rooms");
    $totalOccupied = $occupiedStmt->fetch()['total'] ?? 0;

    // Available beds
    $available = $totalCapacity - $totalOccupied;

    // Active allocations
    $allocationsStmt = $db->query("SELECT COUNT(*) as total FROM hostel_allocations WHERE status = 'Active'");
    $activeAllocations = $allocationsStmt->fetch()['total'];

    $stats = [
        'total_blocks' => $totalBlocks,
        'total_rooms' => $totalRooms,
        'total_capacity' => $totalCapacity,
        'total_occupied' => $totalOccupied,
        'available_beds' => $available,
        'active_allocations' => $activeAllocations,
        'occupancy_rate' => $totalCapacity > 0 ? round(($totalOccupied / $totalCapacity) * 100, 2) : 0
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Get available rooms
 */
function getAvailableRooms($db, $params) {
    $blockId = $params['block_id'] ?? '';

    $where = ["status = 'Available'", "capacity > occupied"];
    $bindings = [];

    if (!empty($blockId)) {
        $where[] = "block_id = :block_id";
        $bindings[':block_id'] = $blockId;
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $query = "
        SELECT
            hostel_rooms.*,
            hostel_blocks.block_name,
            (hostel_rooms.capacity - hostel_rooms.occupied) as available_beds
        FROM hostel_rooms
        LEFT JOIN hostel_blocks ON hostel_rooms.block_id = hostel_blocks.id
        $whereClause
        ORDER BY hostel_rooms.floor, hostel_rooms.room_no
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $rooms = $stmt->fetchAll();

    sendResponse(true, 'Available rooms fetched successfully', ['rooms' => $rooms]);
}

/**
 * Handle POST - Create block, room or allocation
 */
function handlePost($db, $data) {
    try {
        $action = $data['action'] ?? 'create_block';

        switch ($action) {
            case 'create_block':
                createBlock($db, $data);
                break;

            case 'create_room':
                createRoom($db, $data);
                break;

            case 'allocate_room':
                allocateRoom($db, $data);
                break;

            case 'checkout':
                checkoutStudent($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Create hostel block
 */
function createBlock($db, $data) {
    // Validate required fields
    $required = ['block_name', 'block_type', 'total_rooms'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Sanitize input
    $blockName = sanitizeInput($data['block_name']);
    $blockType = sanitizeInput($data['block_type']);
    $totalRooms = (int)$data['total_rooms'];
    $wardenName = isset($data['warden_name']) ? sanitizeInput($data['warden_name']) : null;
    $wardenContact = isset($data['warden_contact']) ? sanitizeInput($data['warden_contact']) : null;

    // Generate block ID
    $blockId = generateId('BL', 10);

    // Insert block
    $stmt = $db->prepare("
        INSERT INTO hostel_blocks (id, block_name, block_type, total_rooms, warden_name, warden_contact)
        VALUES (:id, :block_name, :block_type, :total_rooms, :warden_name, :warden_contact)
    ");

    $stmt->execute([
        ':id' => $blockId,
        ':block_name' => $blockName,
        ':block_type' => $blockType,
        ':total_rooms' => $totalRooms,
        ':warden_name' => $wardenName,
        ':warden_contact' => $wardenContact
    ]);

    // Log activity
    logActivity($db, getCurrentUserId(), 'Created hostel block', 'Hostel', [
        'block_id' => $blockId,
        'block_name' => $blockName
    ]);

    sendResponse(true, 'Block created successfully', ['id' => $blockId]);
}

/**
 * Create room
 */
function createRoom($db, $data) {
    // Validate required fields
    $required = ['block_id', 'room_no', 'room_type', 'capacity', 'floor', 'monthly_fee'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Sanitize input
    $blockId = sanitizeInput($data['block_id']);
    $roomNo = sanitizeInput($data['room_no']);
    $roomType = sanitizeInput($data['room_type']);
    $capacity = (int)$data['capacity'];
    $floor = (int)$data['floor'];
    $monthlyFee = (float)$data['monthly_fee'];
    $status = isset($data['status']) ? sanitizeInput($data['status']) : 'Available';

    // Generate room ID
    $roomId = generateId('RM', 10);

    // Insert room
    $stmt = $db->prepare("
        INSERT INTO hostel_rooms (id, block_id, room_no, room_type, capacity, occupied, floor, monthly_fee, status)
        VALUES (:id, :block_id, :room_no, :room_type, :capacity, 0, :floor, :monthly_fee, :status)
    ");

    $stmt->execute([
        ':id' => $roomId,
        ':block_id' => $blockId,
        ':room_no' => $roomNo,
        ':room_type' => $roomType,
        ':capacity' => $capacity,
        ':floor' => $floor,
        ':monthly_fee' => $monthlyFee,
        ':status' => $status
    ]);

    // Log activity
    logActivity($db, getCurrentUserId(), 'Created hostel room', 'Hostel', [
        'room_id' => $roomId,
        'room_no' => $roomNo
    ]);

    sendResponse(true, 'Room created successfully', ['id' => $roomId]);
}

/**
 * Allocate room to student
 */
function allocateRoom($db, $data) {
    try {
        // Validate required fields
        $required = ['student_id', 'room_id', 'allocation_date'];
        $errors = validateRequired($data, $required);

        if (!empty($errors)) {
            sendResponse(false, 'Validation failed', null, $errors);
        }

        // Sanitize input
        $studentId = sanitizeInput($data['student_id']);
        $roomId = sanitizeInput($data['room_id']);
        $allocationDate = formatDateForDB($data['allocation_date']);
        $remarks = isset($data['remarks']) ? sanitizeInput($data['remarks']) : null;

        // Check if student already has active allocation
        $checkStmt = $db->prepare("
            SELECT id FROM hostel_allocations
            WHERE student_id = :student_id AND status = 'Active'
        ");
        $checkStmt->execute([':student_id' => $studentId]);

        if ($checkStmt->fetch()) {
            sendResponse(false, 'Student already has an active hostel allocation');
        }

        // Check room availability
        $roomStmt = $db->prepare("SELECT capacity, occupied FROM hostel_rooms WHERE id = :id");
        $roomStmt->execute([':id' => $roomId]);
        $room = $roomStmt->fetch();

        if (!$room) {
            sendResponse(false, 'Room not found');
        }

        if ($room['occupied'] >= $room['capacity']) {
            sendResponse(false, 'Room is fully occupied');
        }

        $db->beginTransaction();

        // Insert allocation
        $stmt = $db->prepare("
            INSERT INTO hostel_allocations (student_id, room_id, allocation_date, status, remarks)
            VALUES (:student_id, :room_id, :allocation_date, 'Active', :remarks)
        ");

        $stmt->execute([
            ':student_id' => $studentId,
            ':room_id' => $roomId,
            ':allocation_date' => $allocationDate,
            ':remarks' => $remarks
        ]);

        $allocationId = $db->lastInsertId();

        // Update room occupancy
        $updateStmt = $db->prepare("UPDATE hostel_rooms SET occupied = occupied + 1 WHERE id = :id");
        $updateStmt->execute([':id' => $roomId]);

        // Update room status if full
        if (($room['occupied'] + 1) >= $room['capacity']) {
            $statusStmt = $db->prepare("UPDATE hostel_rooms SET status = 'Full' WHERE id = :id");
            $statusStmt->execute([':id' => $roomId]);
        }

        $db->commit();

        // Log activity
        logActivity($db, getCurrentUserId(), 'Allocated hostel room', 'Hostel', [
            'allocation_id' => $allocationId,
            'student_id' => $studentId,
            'room_id' => $roomId
        ]);

        sendResponse(true, 'Room allocated successfully', ['id' => $allocationId]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        sendResponse(false, 'Error allocating room', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Checkout student from hostel
 */
function checkoutStudent($db, $data) {
    try {
        if (empty($data['allocation_id'])) {
            sendResponse(false, 'Allocation ID is required');
        }

        $allocationId = (int)$data['allocation_id'];
        $checkoutDate = isset($data['checkout_date']) ? formatDateForDB($data['checkout_date']) : date('Y-m-d');
        $remarks = isset($data['remarks']) ? sanitizeInput($data['remarks']) : null;

        // Get allocation details
        $allocStmt = $db->prepare("SELECT room_id, status FROM hostel_allocations WHERE id = :id");
        $allocStmt->execute([':id' => $allocationId]);
        $allocation = $allocStmt->fetch();

        if (!$allocation) {
            sendResponse(false, 'Allocation not found');
        }

        if ($allocation['status'] === 'Checked Out') {
            sendResponse(false, 'Student already checked out');
        }

        $db->beginTransaction();

        // Update allocation
        $updateAllocStmt = $db->prepare("
            UPDATE hostel_allocations
            SET checkout_date = :checkout_date, status = 'Checked Out', remarks = :remarks
            WHERE id = :id
        ");

        $updateAllocStmt->execute([
            ':checkout_date' => $checkoutDate,
            ':remarks' => $remarks,
            ':id' => $allocationId
        ]);

        // Update room occupancy
        $updateRoomStmt = $db->prepare("UPDATE hostel_rooms SET occupied = occupied - 1 WHERE id = :id");
        $updateRoomStmt->execute([':id' => $allocation['room_id']]);

        // Update room status to available if not full
        $statusStmt = $db->prepare("
            UPDATE hostel_rooms
            SET status = 'Available'
            WHERE id = :id AND occupied < capacity
        ");
        $statusStmt->execute([':id' => $allocation['room_id']]);

        $db->commit();

        // Log activity
        logActivity($db, getCurrentUserId(), 'Student checked out from hostel', 'Hostel', [
            'allocation_id' => $allocationId
        ]);

        sendResponse(true, 'Student checked out successfully', ['id' => $allocationId]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        sendResponse(false, 'Error checking out student', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle PUT - Update block, room or allocation
 */
function handlePut($db, $data) {
    try {
        $action = $data['action'] ?? 'update_block';

        switch ($action) {
            case 'update_block':
                updateBlock($db, $data);
                break;

            case 'update_room':
                updateRoom($db, $data);
                break;

            case 'update_allocation':
                updateAllocation($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Update block
 */
function updateBlock($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Block ID is required');
    }

    $blockId = sanitizeInput($data['id']);

    $fields = [];
    $bindings = [':id' => $blockId];

    $updateableFields = ['block_name', 'block_type', 'total_rooms', 'warden_name', 'warden_contact'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            if ($field === 'total_rooms') {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = (int)$data[$field];
            } else {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = sanitizeInput($data[$field]);
            }
        }
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE hostel_blocks SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    logActivity($db, getCurrentUserId(), 'Updated hostel block', 'Hostel', ['block_id' => $blockId]);

    sendResponse(true, 'Block updated successfully', ['id' => $blockId]);
}

/**
 * Update room
 */
function updateRoom($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Room ID is required');
    }

    $roomId = sanitizeInput($data['id']);

    $fields = [];
    $bindings = [':id' => $roomId];

    $updateableFields = ['room_no', 'room_type', 'capacity', 'floor', 'monthly_fee', 'status'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            if (in_array($field, ['capacity', 'floor'])) {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = (int)$data[$field];
            } elseif ($field === 'monthly_fee') {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = (float)$data[$field];
            } else {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = sanitizeInput($data[$field]);
            }
        }
    }

    if (empty($fields)) {
        sendResponse(false, 'No fields to update');
    }

    $query = "UPDATE hostel_rooms SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    logActivity($db, getCurrentUserId(), 'Updated hostel room', 'Hostel', ['room_id' => $roomId]);

    sendResponse(true, 'Room updated successfully', ['id' => $roomId]);
}

/**
 * Update allocation
 */
function updateAllocation($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Allocation ID is required');
    }

    $allocationId = (int)$data['id'];

    $fields = [];
    $bindings = [':id' => $allocationId];

    $updateableFields = ['checkout_date', 'status', 'remarks'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            if ($field === 'checkout_date') {
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

    $query = "UPDATE hostel_allocations SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    sendResponse(true, 'Allocation updated successfully', ['id' => $allocationId]);
}

/**
 * Handle DELETE - Delete block, room or allocation
 */
function handleDelete($db, $params) {
    try {
        $action = $params['action'] ?? 'delete_block';

        switch ($action) {
            case 'delete_block':
                if (empty($params['id'])) {
                    sendResponse(false, 'Block ID is required');
                }

                $blockId = sanitizeInput($params['id']);

                // Check for active allocations
                $checkStmt = $db->prepare("
                    SELECT COUNT(*) as total FROM hostel_allocations
                    INNER JOIN hostel_rooms ON hostel_allocations.room_id = hostel_rooms.id
                    WHERE hostel_rooms.block_id = :block_id AND hostel_allocations.status = 'Active'
                ");
                $checkStmt->execute([':block_id' => $blockId]);
                $activeAllocations = $checkStmt->fetch()['total'];

                if ($activeAllocations > 0) {
                    sendResponse(false, 'Cannot delete block with active student allocations');
                }

                $stmt = $db->prepare("DELETE FROM hostel_blocks WHERE id = :id");
                $stmt->execute([':id' => $blockId]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(false, 'Block not found');
                }

                logActivity($db, getCurrentUserId(), 'Deleted hostel block', 'Hostel', ['block_id' => $blockId]);
                sendResponse(true, 'Block deleted successfully', ['id' => $blockId]);
                break;

            case 'delete_room':
                if (empty($params['id'])) {
                    sendResponse(false, 'Room ID is required');
                }

                $roomId = sanitizeInput($params['id']);

                // Check for active allocations
                $checkStmt = $db->prepare("
                    SELECT COUNT(*) as total FROM hostel_allocations
                    WHERE room_id = :room_id AND status = 'Active'
                ");
                $checkStmt->execute([':room_id' => $roomId]);
                $activeAllocations = $checkStmt->fetch()['total'];

                if ($activeAllocations > 0) {
                    sendResponse(false, 'Cannot delete room with active student allocations');
                }

                $stmt = $db->prepare("DELETE FROM hostel_rooms WHERE id = :id");
                $stmt->execute([':id' => $roomId]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(false, 'Room not found');
                }

                logActivity($db, getCurrentUserId(), 'Deleted hostel room', 'Hostel', ['room_id' => $roomId]);
                sendResponse(true, 'Room deleted successfully', ['id' => $roomId]);
                break;

            case 'delete_allocation':
                if (empty($params['id'])) {
                    sendResponse(false, 'Allocation ID is required');
                }

                $allocationId = (int)$params['id'];

                $stmt = $db->prepare("DELETE FROM hostel_allocations WHERE id = :id");
                $stmt->execute([':id' => $allocationId]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(false, 'Allocation not found');
                }

                logActivity($db, getCurrentUserId(), 'Deleted hostel allocation', 'Hostel', ['allocation_id' => $allocationId]);
                sendResponse(true, 'Allocation deleted successfully', ['id' => $allocationId]);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error deleting record', null, ['error' => $e->getMessage()]);
    }
}
?>
