<?php
// Simple configuration
class Config {
    // Google Sheets settings (optional)
    const GOOGLE_SHEETS_ID = 'your_google_sheets_id_here';
    const GOOGLE_API_KEY = 'your_google_api_key_here';
    
    // File paths
    const EMPLOYEES_FILE = 'data/employees.json';
    const ATTENDANCE_FILE = 'data/attendance.json';
    
    // Your server URL
    const SERVER_URL = 'https://your-domain.com';
    
    // Enable/disable MAC address checking
    const CHECK_MAC_ADDRESS = true; // Set to true if you want MAC verification
}

// Set timezone
date_default_timezone_set('Africa/Cairo');
?>