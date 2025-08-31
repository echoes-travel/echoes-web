<?php
require_once 'config.php';

// Set response header
header('Content-Type: application/json');

try {
    // Get employee ID from URL query parameter
    $employeeId = $_GET['id'] ?? null;
    
    if (!$employeeId) {
        throw new Exception('Employee ID is required');
    }
    
    // Read employee data
    $employees = [];
    if (file_exists(Config::EMPLOYEES_FILE)) {
        $employees = json_decode(file_get_contents(Config::EMPLOYEES_FILE), true) ?: [];
    }
    
    // Check if employee exists
    if (!isset($employees[$employeeId])) {
        throw new Exception('Employee not found');
    }
    
    $employee = $employees[$employeeId];
    
    // MAC address check (optional)
    if (Config::CHECK_MAC_ADDRESS) {
        $clientMAC = $_SERVER['HTTP_X_MAC_ADDRESS'] ?? getClientIP();
        if (isset($employee['mac_address']) && $employee['mac_address'] !== $clientMAC) {
            throw new Exception('Device not authorized');
        }
    }
    
    // Toggle status
    $currentStatus = $employee['status'] ?? 'checked_out';
    $newStatus = ($currentStatus === 'checked_in') ? 'checked_out' : 'checked_in';
    $action = ($newStatus === 'checked_in') ? 'CHECK_IN' : 'CHECK_OUT';
    
    // Update employee status
    $employees[$employeeId]['status'] = $newStatus;
    $employees[$employeeId]['last_update'] = date('Y-m-d H:i:s');
    
    // Save employee data
    file_put_contents(Config::EMPLOYEES_FILE, json_encode($employees, JSON_PRETTY_PRINT));
    
    // Create attendance record
    $record = [
        'employee_id' => $employeeId,
        'employee_name' => $employee['name'],
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s'),
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'ip_address' => getClientIP()
    ];
    
    // Save attendance record
    $attendance = [];
    if (file_exists(Config::ATTENDANCE_FILE)) {
        $attendance = json_decode(file_get_contents(Config::ATTENDANCE_FILE), true) ?: [];
    }
    $attendance[] = $record;
    file_put_contents(Config::ATTENDANCE_FILE, json_encode($attendance, JSON_PRETTY_PRINT));
    
    // Send to Google Sheets (optional)
    if (Config::GOOGLE_SHEETS_ID !== 'your_google_sheets_id_here') {
        sendToGoogleSheets($record);
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'message' => "Successfully {$action}",
        'employee' => [
            'id' => $employeeId,
            'name' => $employee['name'],
            'status' => $newStatus
        ],
        'time' => date('H:i:s'),
        'date' => date('Y-m-d')
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper function to get client IP
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($ipKeys as $key) {
        if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return 'unknown';
}

// Function to send to Google Sheets
function sendToGoogleSheets($record) {
    try {
        $values = [[$record['employee_id'], $record['employee_name'], $record['action'], 
                   $record['timestamp'], $record['date'], $record['time'], $record['ip_address']]];
        
        $url = "https://sheets.googleapis.com/v4/spreadsheets/" . Config::GOOGLE_SHEETS_ID . "/values/Sheet1:append";
        
        $data = json_encode(['range' => 'Sheet1', 'majorDimension' => 'ROWS', 'values' => $values]);
        
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . Config::GOOGLE_API_KEY
                ],
                'content' => $data
            ]
        ]);
        
        file_get_contents($url . '?valueInputOption=RAW', false, $context);
    } catch (Exception $e) {
        // Ignore Google Sheets errors - local storage still works
    }
}
?>