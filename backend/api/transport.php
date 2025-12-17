<?php
/**
 * Transport API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for transport routes and assignments
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
        $action = $params['action'] ?? 'routes';

        switch ($action) {
            case 'routes':
                getRoutesList($db, $params);
                break;

            case 'single_route':
                getSingleRoute($db, $params);
                break;

            case 'stops':
                getRouteStops($db, $params);
                break;

            case 'assignments':
                getAssignmentsList($db, $params);
                break;

            case 'student_assignment':
                getStudentAssignment($db, $params);
                break;

            case 'stats':
                getTransportStats($db);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get routes list
 */
function getRoutesList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 10;
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($status)) {
        $where[] = "status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($search)) {
        $where[] = "(route_name LIKE :search OR route_no LIKE :search OR vehicle_no LIKE :search)";
        $bindings[':search'] = "%$search%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM transport_routes $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated records
    $offset = ($page - 1) * $perPage;
    $query = "
        SELECT * FROM transport_routes
        $whereClause
        ORDER BY route_no ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $routes = $stmt->fetchAll();

    // Get stop count for each route
    foreach ($routes as &$route) {
        $stopsStmt = $db->prepare("SELECT COUNT(*) as total FROM transport_stops WHERE route_id = :route_id");
        $stopsStmt->execute([':route_id' => $route['id']]);
        $route['stops_count'] = $stopsStmt->fetch()['total'];

        // Get assigned students count
        $studentsStmt = $db->prepare("
            SELECT COUNT(*) as total FROM transport_assignments
            WHERE route_id = :route_id AND status = 'Active'
        ");
        $studentsStmt->execute([':route_id' => $route['id']]);
        $route['students_count'] = $studentsStmt->fetch()['total'];
    }

    $response = [
        'routes' => $routes,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $perPage)
        ]
    ];

    sendResponse(true, 'Routes fetched successfully', $response);
}

/**
 * Get single route with stops
 */
function getSingleRoute($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Route ID is required');
    }

    $routeId = $params['id'];

    // Get route details
    $routeStmt = $db->prepare("SELECT * FROM transport_routes WHERE id = :id");
    $routeStmt->execute([':id' => $routeId]);
    $route = $routeStmt->fetch();

    if (!$route) {
        sendResponse(false, 'Route not found');
    }

    // Get stops
    $stopsStmt = $db->prepare("
        SELECT * FROM transport_stops
        WHERE route_id = :route_id
        ORDER BY stop_order ASC
    ");
    $stopsStmt->execute([':route_id' => $routeId]);
    $route['stops'] = $stopsStmt->fetchAll();

    sendResponse(true, 'Route fetched successfully', $route);
}

/**
 * Get route stops
 */
function getRouteStops($db, $params) {
    if (empty($params['route_id'])) {
        sendResponse(false, 'Route ID is required');
    }

    $routeId = $params['route_id'];

    $query = "
        SELECT * FROM transport_stops
        WHERE route_id = :route_id
        ORDER BY stop_order ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':route_id' => $routeId]);
    $stops = $stmt->fetchAll();

    sendResponse(true, 'Stops fetched successfully', ['stops' => $stops]);
}

/**
 * Get assignments list
 */
function getAssignmentsList($db, $params) {
    $routeId = $params['route_id'] ?? '';
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($routeId)) {
        $where[] = "transport_assignments.route_id = :route_id";
        $bindings[':route_id'] = $routeId;
    }

    if (!empty($status)) {
        $where[] = "transport_assignments.status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($search)) {
        $where[] = "students.name LIKE :search";
        $bindings[':search'] = "%$search%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "
        SELECT
            transport_assignments.*,
            students.name as student_name,
            students.class,
            students.section,
            students.roll_no,
            students.contact,
            transport_routes.route_name,
            transport_routes.route_no,
            transport_stops.stop_name
        FROM transport_assignments
        LEFT JOIN students ON transport_assignments.student_id = students.id
        LEFT JOIN transport_routes ON transport_assignments.route_id = transport_routes.id
        LEFT JOIN transport_stops ON transport_assignments.stop_id = transport_stops.id
        $whereClause
        ORDER BY transport_routes.route_no, students.name
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($bindings);
    $assignments = $stmt->fetchAll();

    sendResponse(true, 'Assignments fetched successfully', ['assignments' => $assignments]);
}

/**
 * Get student assignment
 */
function getStudentAssignment($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required');
    }

    $studentId = $params['student_id'];

    $query = "
        SELECT
            transport_assignments.*,
            transport_routes.route_name,
            transport_routes.route_no,
            transport_routes.vehicle_no,
            transport_routes.driver_name,
            transport_routes.driver_contact,
            transport_routes.fare,
            transport_stops.stop_name,
            transport_stops.pickup_time,
            transport_stops.drop_time
        FROM transport_assignments
        LEFT JOIN transport_routes ON transport_assignments.route_id = transport_routes.id
        LEFT JOIN transport_stops ON transport_assignments.stop_id = transport_stops.id
        WHERE transport_assignments.student_id = :student_id
        ORDER BY transport_assignments.start_date DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':student_id' => $studentId]);
    $assignments = $stmt->fetchAll();

    sendResponse(true, 'Student assignments fetched successfully', ['assignments' => $assignments]);
}

/**
 * Get transport statistics
 */
function getTransportStats($db) {
    // Total routes
    $routesStmt = $db->query("SELECT COUNT(*) as total FROM transport_routes");
    $totalRoutes = $routesStmt->fetch()['total'];

    // Active routes
    $activeRoutesStmt = $db->query("SELECT COUNT(*) as total FROM transport_routes WHERE status = 'Active'");
    $activeRoutes = $activeRoutesStmt->fetch()['total'];

    // Total students assigned
    $studentsStmt = $db->query("SELECT COUNT(*) as total FROM transport_assignments WHERE status = 'Active'");
    $totalStudents = $studentsStmt->fetch()['total'];

    // Total stops
    $stopsStmt = $db->query("SELECT COUNT(*) as total FROM transport_stops");
    $totalStops = $stopsStmt->fetch()['total'];

    // Total capacity
    $capacityStmt = $db->query("SELECT SUM(capacity) as total FROM transport_routes WHERE status = 'Active'");
    $totalCapacity = $capacityStmt->fetch()['total'] ?? 0;

    $stats = [
        'total_routes' => $totalRoutes,
        'active_routes' => $activeRoutes,
        'total_students' => $totalStudents,
        'total_stops' => $totalStops,
        'total_capacity' => $totalCapacity,
        'occupancy_rate' => $totalCapacity > 0 ? round(($totalStudents / $totalCapacity) * 100, 2) : 0
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Handle POST - Create route or assignment
 */
function handlePost($db, $data) {
    try {
        $action = $data['action'] ?? 'create_route';

        switch ($action) {
            case 'create_route':
                createRoute($db, $data);
                break;

            case 'add_stop':
                addStop($db, $data);
                break;

            case 'assign_student':
                assignStudent($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Create route
 */
function createRoute($db, $data) {
    // Validate required fields
    $required = ['route_name', 'route_no', 'vehicle_no', 'driver_name', 'driver_contact', 'capacity', 'fare'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Sanitize input
    $routeName = sanitizeInput($data['route_name']);
    $routeNo = sanitizeInput($data['route_no']);
    $vehicleNo = sanitizeInput($data['vehicle_no']);
    $driverName = sanitizeInput($data['driver_name']);
    $driverContact = sanitizeInput($data['driver_contact']);
    $capacity = (int)$data['capacity'];
    $fare = (float)$data['fare'];
    $status = isset($data['status']) ? sanitizeInput($data['status']) : 'Active';

    // Generate route ID
    $routeId = generateId('RT', 10);

    // Insert route
    $stmt = $db->prepare("
        INSERT INTO transport_routes (id, route_name, route_no, vehicle_no, driver_name, driver_contact, capacity, fare, status)
        VALUES (:id, :route_name, :route_no, :vehicle_no, :driver_name, :driver_contact, :capacity, :fare, :status)
    ");

    $stmt->execute([
        ':id' => $routeId,
        ':route_name' => $routeName,
        ':route_no' => $routeNo,
        ':vehicle_no' => $vehicleNo,
        ':driver_name' => $driverName,
        ':driver_contact' => $driverContact,
        ':capacity' => $capacity,
        ':fare' => $fare,
        ':status' => $status
    ]);

    // Log activity
    logActivity($db, getCurrentUserId(), 'Created transport route', 'Transport', [
        'route_id' => $routeId,
        'route_name' => $routeName
    ]);

    sendResponse(true, 'Route created successfully', ['id' => $routeId]);
}

/**
 * Add stop to route
 */
function addStop($db, $data) {
    // Validate required fields
    $required = ['route_id', 'stop_name', 'stop_order', 'pickup_time', 'drop_time'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Sanitize input
    $routeId = sanitizeInput($data['route_id']);
    $stopName = sanitizeInput($data['stop_name']);
    $stopOrder = (int)$data['stop_order'];
    $pickupTime = $data['pickup_time'];
    $dropTime = $data['drop_time'];

    // Insert stop
    $stmt = $db->prepare("
        INSERT INTO transport_stops (route_id, stop_name, stop_order, pickup_time, drop_time)
        VALUES (:route_id, :stop_name, :stop_order, :pickup_time, :drop_time)
    ");

    $stmt->execute([
        ':route_id' => $routeId,
        ':stop_name' => $stopName,
        ':stop_order' => $stopOrder,
        ':pickup_time' => $pickupTime,
        ':drop_time' => $dropTime
    ]);

    $stopId = $db->lastInsertId();

    // Log activity
    logActivity($db, getCurrentUserId(), 'Added transport stop', 'Transport', [
        'stop_id' => $stopId,
        'route_id' => $routeId,
        'stop_name' => $stopName
    ]);

    sendResponse(true, 'Stop added successfully', ['id' => $stopId]);
}

/**
 * Assign student to route
 */
function assignStudent($db, $data) {
    try {
        // Validate required fields
        $required = ['student_id', 'route_id', 'stop_id', 'start_date'];
        $errors = validateRequired($data, $required);

        if (!empty($errors)) {
            sendResponse(false, 'Validation failed', null, $errors);
        }

        // Sanitize input
        $studentId = sanitizeInput($data['student_id']);
        $routeId = sanitizeInput($data['route_id']);
        $stopId = (int)$data['stop_id'];
        $startDate = formatDateForDB($data['start_date']);
        $endDate = isset($data['end_date']) ? formatDateForDB($data['end_date']) : null;
        $status = isset($data['status']) ? sanitizeInput($data['status']) : 'Active';

        // Check if student already has active assignment
        $checkStmt = $db->prepare("
            SELECT id FROM transport_assignments
            WHERE student_id = :student_id AND status = 'Active'
        ");
        $checkStmt->execute([':student_id' => $studentId]);

        if ($checkStmt->fetch()) {
            sendResponse(false, 'Student already has an active transport assignment');
        }

        // Insert assignment
        $stmt = $db->prepare("
            INSERT INTO transport_assignments (student_id, route_id, stop_id, start_date, end_date, status)
            VALUES (:student_id, :route_id, :stop_id, :start_date, :end_date, :status)
        ");

        $stmt->execute([
            ':student_id' => $studentId,
            ':route_id' => $routeId,
            ':stop_id' => $stopId,
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':status' => $status
        ]);

        $assignmentId = $db->lastInsertId();

        // Log activity
        logActivity($db, getCurrentUserId(), 'Assigned student to transport', 'Transport', [
            'assignment_id' => $assignmentId,
            'student_id' => $studentId,
            'route_id' => $routeId
        ]);

        sendResponse(true, 'Student assigned successfully', ['id' => $assignmentId]);

    } catch (Exception $e) {
        sendResponse(false, 'Error assigning student', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle PUT - Update route or assignment
 */
function handlePut($db, $data) {
    try {
        $action = $data['action'] ?? 'update_route';

        switch ($action) {
            case 'update_route':
                updateRoute($db, $data);
                break;

            case 'update_stop':
                updateStop($db, $data);
                break;

            case 'update_assignment':
                updateAssignment($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Update route
 */
function updateRoute($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Route ID is required');
    }

    $routeId = sanitizeInput($data['id']);

    // Build update query
    $fields = [];
    $bindings = [':id' => $routeId];

    $updateableFields = ['route_name', 'route_no', 'vehicle_no', 'driver_name', 'driver_contact', 'capacity', 'fare', 'status'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            if (in_array($field, ['capacity'])) {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = (int)$data[$field];
            } elseif ($field === 'fare') {
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

    $query = "UPDATE transport_routes SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    logActivity($db, getCurrentUserId(), 'Updated transport route', 'Transport', ['route_id' => $routeId]);

    sendResponse(true, 'Route updated successfully', ['id' => $routeId]);
}

/**
 * Update stop
 */
function updateStop($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Stop ID is required');
    }

    $stopId = (int)$data['id'];

    $fields = [];
    $bindings = [':id' => $stopId];

    $updateableFields = ['stop_name', 'stop_order', 'pickup_time', 'drop_time'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            if ($field === 'stop_order') {
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

    $query = "UPDATE transport_stops SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    sendResponse(true, 'Stop updated successfully', ['id' => $stopId]);
}

/**
 * Update assignment
 */
function updateAssignment($db, $data) {
    if (empty($data['id'])) {
        sendResponse(false, 'Assignment ID is required');
    }

    $assignmentId = (int)$data['id'];

    $fields = [];
    $bindings = [':id' => $assignmentId];

    $updateableFields = ['route_id', 'stop_id', 'end_date', 'status'];

    foreach ($updateableFields as $field) {
        if (isset($data[$field])) {
            if (in_array($field, ['route_id'])) {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = sanitizeInput($data[$field]);
            } elseif ($field === 'stop_id') {
                $fields[] = "$field = :$field";
                $bindings[":$field"] = (int)$data[$field];
            } elseif ($field === 'end_date') {
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

    $query = "UPDATE transport_assignments SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute($bindings);

    logActivity($db, getCurrentUserId(), 'Updated transport assignment', 'Transport', ['assignment_id' => $assignmentId]);

    sendResponse(true, 'Assignment updated successfully', ['id' => $assignmentId]);
}

/**
 * Handle DELETE - Delete route, stop, or assignment
 */
function handleDelete($db, $params) {
    try {
        $action = $params['action'] ?? 'delete_route';

        switch ($action) {
            case 'delete_route':
                if (empty($params['id'])) {
                    sendResponse(false, 'Route ID is required');
                }

                $routeId = sanitizeInput($params['id']);

                // Check for active assignments
                $checkStmt = $db->prepare("
                    SELECT COUNT(*) as total FROM transport_assignments
                    WHERE route_id = :route_id AND status = 'Active'
                ");
                $checkStmt->execute([':route_id' => $routeId]);
                $activeAssignments = $checkStmt->fetch()['total'];

                if ($activeAssignments > 0) {
                    sendResponse(false, 'Cannot delete route with active student assignments');
                }

                $stmt = $db->prepare("DELETE FROM transport_routes WHERE id = :id");
                $stmt->execute([':id' => $routeId]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(false, 'Route not found');
                }

                logActivity($db, getCurrentUserId(), 'Deleted transport route', 'Transport', ['route_id' => $routeId]);
                sendResponse(true, 'Route deleted successfully', ['id' => $routeId]);
                break;

            case 'delete_stop':
                if (empty($params['id'])) {
                    sendResponse(false, 'Stop ID is required');
                }

                $stopId = (int)$params['id'];

                $stmt = $db->prepare("DELETE FROM transport_stops WHERE id = :id");
                $stmt->execute([':id' => $stopId]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(false, 'Stop not found');
                }

                sendResponse(true, 'Stop deleted successfully', ['id' => $stopId]);
                break;

            case 'delete_assignment':
                if (empty($params['id'])) {
                    sendResponse(false, 'Assignment ID is required');
                }

                $assignmentId = (int)$params['id'];

                $stmt = $db->prepare("DELETE FROM transport_assignments WHERE id = :id");
                $stmt->execute([':id' => $assignmentId]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(false, 'Assignment not found');
                }

                logActivity($db, getCurrentUserId(), 'Deleted transport assignment', 'Transport', ['assignment_id' => $assignmentId]);
                sendResponse(true, 'Assignment deleted successfully', ['id' => $assignmentId]);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error deleting record', null, ['error' => $e->getMessage()]);
    }
}
?>
