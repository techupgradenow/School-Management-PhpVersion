<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/db.php';
require_once '../helpers/functions.php';

// WhatsApp API Configuration
// Option 1: Twilio WhatsApp API (Recommended)
define('TWILIO_SID', 'YOUR_TWILIO_ACCOUNT_SID');
define('TWILIO_TOKEN', 'YOUR_TWILIO_AUTH_TOKEN');
define('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+14155238886'); // Twilio Sandbox number

// Option 2: Alternative - Use direct WhatsApp Business API or third-party service
define('USE_TWILIO', true); // Set to false if using alternative service

/**
 * Send WhatsApp message via Twilio API
 */
function sendWhatsAppViaTwilio($toNumber, $message) {
    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_SID . '/Messages.json';

    $data = [
        'From' => TWILIO_WHATSAPP_NUMBER,
        'To' => 'whatsapp:' . $toNumber,
        'Body' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_SID . ':' . TWILIO_TOKEN);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'success' => $httpCode === 201,
        'response' => json_decode($response, true),
        'http_code' => $httpCode
    ];
}

/**
 * Send WhatsApp message via alternative service (Gupshup, Aisensy, etc.)
 */
function sendWhatsAppViaAlternative($toNumber, $message) {
    // Example for Gupshup API
    $apiKey = 'YOUR_GUPSHUP_API_KEY';
    $url = 'https://api.gupshup.io/sm/api/v1/msg';

    $data = [
        'channel' => 'whatsapp',
        'source' => 'YOUR_WHATSAPP_NUMBER',
        'destination' => $toNumber,
        'message' => json_encode(['type' => 'text', 'text' => $message]),
        'src.name' => 'EduManage Pro'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'apikey: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    return [
        'success' => isset($result['status']) && $result['status'] === 'submitted',
        'response' => $result,
        'http_code' => $httpCode
    ];
}

/**
 * Main function to send WhatsApp message
 */
function sendWhatsAppMessage($toNumber, $message) {
    // Validate phone number format
    if (!preg_match('/^\+[1-9]\d{1,14}$/', $toNumber)) {
        return [
            'success' => false,
            'error' => 'Invalid phone number format. Must be in format: +[country code][number]'
        ];
    }

    try {
        if (USE_TWILIO) {
            return sendWhatsAppViaTwilio($toNumber, $message);
        } else {
            return sendWhatsAppViaAlternative($toNumber, $message);
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get students by attendance status and filters
 */
function getStudentsByFilters($pdo, $filters) {
    $class = $filters['class'] ?? '';
    $section = $filters['section'] ?? '';
    $date = $filters['date'] ?? date('Y-m-d');
    $targetType = $filters['target'] ?? 'absent';

    $query = "
        SELECT
            s.id,
            s.name,
            s.roll_number,
            s.class,
            s.section,
            s.whatsapp_number,
            s.parent_name,
            COALESCE(a.status, 'unmarked') as status,
            a.attendance_date
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = :date
        WHERE s.class = :class AND s.section = :section
    ";

    // Filter by attendance status
    if ($targetType === 'absent') {
        $query .= " AND (a.status = 'absent' OR a.status IS NULL)";
    } elseif ($targetType === 'present') {
        $query .= " AND a.status = 'present'";
    } elseif ($targetType === 'late') {
        $query .= " AND a.status = 'late'";
    }
    // 'all' means no additional filter

    $query .= " ORDER BY s.roll_number";

    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'date' => $date,
        'class' => $class,
        'section' => $section
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Personalize message with student data
 */
function personalizeMessage($template, $student, $date) {
    $statusMap = [
        'present' => 'PRESENT',
        'absent' => 'ABSENT',
        'late' => 'LATE',
        'unmarked' => 'NOT MARKED'
    ];

    $replacements = [
        '{STUDENT_NAME}' => $student['name'],
        '{STATUS}' => $statusMap[$student['status']] ?? 'UNMARKED',
        '{DATE}' => date('d-m-Y', strtotime($date)),
        '{ROLL_NO}' => $student['roll_number'],
        '{CLASS}' => $student['class'],
        '{SECTION}' => $student['section'],
        '{PARENT_NAME}' => $student['parent_name'] ?? 'Parent'
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

// Handle API requests
try {
    $pdo = getDBConnection();

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'send_notifications':
            // Get request data
            $requestData = json_decode(file_get_contents('php://input'), true);
            if (!$requestData) {
                $requestData = $_POST;
            }

            $class = $requestData['class'] ?? '';
            $section = $requestData['section'] ?? '';
            $date = $requestData['date'] ?? date('Y-m-d');
            $targetType = $requestData['target'] ?? 'absent';
            $messageTemplate = $requestData['message'] ?? 'Dear {PARENT_NAME}, your child {STUDENT_NAME} was marked {STATUS} on {DATE}. - EduManage Pro';
            $enableWhatsApp = $requestData['enable_whatsapp'] ?? false;

            // Validate required fields
            if (empty($class) || empty($section)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Class and section are required'
                ]);
                exit;
            }

            // Get students based on filters
            $students = getStudentsByFilters($pdo, [
                'class' => $class,
                'section' => $section,
                'date' => $date,
                'target' => $targetType
            ]);

            $results = [
                'total' => count($students),
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'errors' => []
            ];

            if ($enableWhatsApp) {
                foreach ($students as $student) {
                    // Skip if no WhatsApp number
                    if (empty($student['whatsapp_number'])) {
                        $results['skipped']++;
                        continue;
                    }

                    // Personalize message
                    $personalizedMessage = personalizeMessage($messageTemplate, $student, $date);

                    // Send WhatsApp message
                    $result = sendWhatsAppMessage($student['whatsapp_number'], $personalizedMessage);

                    if ($result['success']) {
                        $results['sent']++;

                        // Log notification in database
                        $logStmt = $pdo->prepare("
                            INSERT INTO notification_logs
                            (student_id, type, recipient, message, status, sent_at)
                            VALUES (?, 'whatsapp', ?, ?, 'sent', NOW())
                        ");
                        $logStmt->execute([
                            $student['id'],
                            $student['whatsapp_number'],
                            $personalizedMessage
                        ]);
                    } else {
                        $results['failed']++;
                        $results['errors'][] = [
                            'student' => $student['name'],
                            'number' => $student['whatsapp_number'],
                            'error' => $result['error'] ?? 'Unknown error'
                        ];
                    }

                    // Rate limiting: sleep for 100ms between messages
                    usleep(100000);
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "Sent: {$results['sent']}, Failed: {$results['failed']}, Skipped: {$results['skipped']}",
                'results' => $results
            ]);
            break;

        case 'test_whatsapp':
            // Test WhatsApp API connection
            $testNumber = $_POST['test_number'] ?? '';

            if (empty($testNumber)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Test number required'
                ]);
                exit;
            }

            $testMessage = "🔔 Test message from EduManage Pro\n\nYour WhatsApp integration is working correctly!\n\nDate: " . date('d-m-Y H:i:s');

            $result = sendWhatsAppMessage($testNumber, $testMessage);

            echo json_encode([
                'success' => $result['success'],
                'message' => $result['success'] ? 'Test message sent successfully!' : 'Failed to send test message',
                'details' => $result
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
