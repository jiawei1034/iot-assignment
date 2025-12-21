<?php
session_start();
require "config.php";

// ============================================
// AUTHENTICATION & AUTHORIZATION
// ============================================

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user role from database
$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($role);
$stmt->fetch();
$stmt->close();

// Define allowed roles
$allowed_roles = ['super_admin', 'admin', 'superadmin', 'administrator'];
$normalized_role = strtolower(trim($role ?? ''));

// Check if user has permission
if (!in_array($normalized_role, $allowed_roles)) {
    header('Location: AdminHome.php');
    exit();
}

// Get user info for display
$stmt = $conn->prepare("SELECT name FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($user_name);
$stmt->fetch();
$stmt->close();

$currentUserName = $user_name ?? 'Super Admin';
$parts = preg_split('/\s+/', trim($currentUserName));
$initials = '';
foreach ($parts as $p) {
    if ($p !== '') $initials .= strtoupper($p[0]);
}
$initials = substr($initials, 0, 2);

// ============================================
// API ENDPOINTS
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['api']) {
        case 'dashboard-stats':
            getDashboardStats();
            break;
            
        case 'recent-users':
            getRecentUsers();
            break;
            
        case 'failed-logins':
            getFailedLogins();
            break;
            
        case 'notifications':
            getNotifications();
            break;
            
        case 'users':
            getUsers();
            break;
            
        case 'devices':
            getDevices();
            break;
            
        case 'system-status':
            getSystemStatus();
            break;
            
        default:
            echo json_encode(['error' => 'Invalid API endpoint']);
    }
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['api']) {
        case 'add-user':
            addUser();
            break;
            
        case 'add-device':
            addDevice();
            break;
            
        case 'delete-user':
            deleteUser();
            break;
            
        case 'delete-device':
            deleteDevice();
            break;
            
        case 'update-user-status':
            updateUserStatus();
            break;
            
        case 'update-user-role':
            updateUserRole();
            break;
            
        case 'mark-notification-read':
            markNotificationRead();
            break;
            
        case 'mark-all-notifications-read':
            markAllNotificationsRead();
            break;
            
        case 'delete-notification':
            deleteNotification();
            break;
            
        case 'clear-all-notifications':
            clearAllNotifications();
            break;
            
        default:
            echo json_encode(['error' => 'Invalid API endpoint']);
    }
    exit;
}

// ============================================
// API FUNCTIONS
// ============================================

function getDashboardStats() {
    global $conn;
    
    $stats = [];
    
    // Total Users
    $result = $conn->query("SELECT COUNT(*) as total FROM users");
    $stats['totalUsers'] = $result->fetch_assoc()['total'];
    
    // Total Devices
    $result = $conn->query("SELECT COUNT(*) as total FROM device_list");
    $stats['totalDevices'] = $result->fetch_assoc()['total'];
    
    // Active Devices
    $result = $conn->query("SELECT COUNT(*) as total FROM device_list WHERE status = 'active'");
    $stats['activeDevices'] = $result->fetch_assoc()['total'];
    
    // Recent Logins (last 24 hours)
    $result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE is_successful = 1 AND attempt_time >= NOW() - INTERVAL 24 HOUR");
    $stats['recentLogins'] = $result->fetch_assoc()['total'];
    
    // Failed Logins (last 24 hours)
    $result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE is_successful = 0 AND attempt_time >= NOW() - INTERVAL 24 HOUR");
    $stats['failedLogins24h'] = $result->fetch_assoc()['total'];
    
    // All Notifications (since no is_read column exists)
    $result = $conn->query("SELECT COUNT(*) as total FROM notifications");
    $stats['unreadNotifications'] = $result->fetch_assoc()['total'];
    
    // Today's Events
    $result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE DATE(attempt_time) = CURDATE()");
    $stats['todayEvents'] = $result->fetch_assoc()['total'];
    
    echo json_encode($stats);
}

function getRecentUsers() {
    global $conn;
    
    $limit = $_GET['limit'] ?? 5;
    $stmt = $conn->prepare("
        SELECT user_id, name, email, role, is_locked 
        FROM users 
        ORDER BY user_id DESC 
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode($users);
}

function getFailedLogins() {
    global $conn;
    
    $limit = $_GET['limit'] ?? 10;
    $stmt = $conn->prepare("
        SELECT la.*, u.name as username, u.email,
               DATE_FORMAT(la.attempt_time, '%Y-%m-%d %H:%i:%s') as attempt_time
        FROM login_attempts la 
        LEFT JOIN users u ON la.user_id = u.user_id 
        WHERE la.is_successful = 0 
        ORDER BY la.attempt_time DESC 
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $attempts = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode($attempts);
}

function getNotifications() {
    global $conn;
    
    $limit = $_GET['limit'] ?? 10;
    $stmt = $conn->prepare("
        SELECT *, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at 
        FROM notifications 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode($notifications);
}

function getUsers() {
    global $conn;
    
    $search = $_GET['search'] ?? '';
    $limit = $_GET['limit'] ?? 0;
    
    $query = "
        SELECT user_id, name, email, role, is_locked
        FROM users
    ";
    
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $query .= " WHERE name LIKE ? OR email LIKE ?";
        $searchTerm = "%{$search}%";
        $params = [$searchTerm, $searchTerm];
        $types = "ss";
    }
    
    $query .= " ORDER BY user_id DESC";
    
    if ($limit > 0) {
        $query .= " LIMIT ?";
        $params[] = $limit;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode($users);
}

function getDevices() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT device_id, name, status 
        FROM device_list 
        ORDER BY device_id DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $devices = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode($devices);
}

function getSystemStatus() {
    $isOnline = false;
    
    // Check Apache
    $ch = curl_init('http://localhost/');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);
    
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode > 0 && $httpCode < 600) {
        $isOnline = true;
    }
    
    // Check MySQL
    if (function_exists('mysqli_connect')) {
        $conn = @mysqli_connect('localhost', 'root', '');
        if ($conn) {
            $isOnline = true;
            mysqli_close($conn);
        }
    }
    
    $status = $isOnline ? 'online' : 'offline';
    
    echo json_encode([
        'status' => $status,
        'timestamp' => date('Y-m-d H:i:s'),
        'isOnline' => $status === 'online'
    ]);
}

// ============================================
// POST HANDLER FUNCTIONS
// ============================================

function addUser() {
    global $conn;
    
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';
    
    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'User created successfully',
                'user_id' => $stmt->insert_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $conn->error]);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function addDevice() {
    global $conn;
    
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Device name is required']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("INSERT INTO device_list (name, status) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $status);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true, 
                'message' => 'Device created successfully',
                'device_id' => $stmt->insert_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create device']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function deleteUser() {
    global $conn;
    
    $user_id = $_POST['user_id'] ?? 0;
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        return;
    }
    
    // Don't allow deleting yourself
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    try {
        // Get user info for logging
        $stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function deleteDevice() {
    global $conn;
    
    $device_id = $_POST['device_id'] ?? 0;
    
    if ($device_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid device ID']);
        return;
    }
    
    try {
        // Get device info for logging
        $stmt = $conn->prepare("SELECT name FROM device_list WHERE device_id = ?");
        $stmt->bind_param("i", $device_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $device = $result->fetch_assoc();
        $stmt->close();
        
        // Delete the device
        $stmt = $conn->prepare("DELETE FROM device_list WHERE device_id = ?");
        $stmt->bind_param("i", $device_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Device deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete device']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function updateUserStatus() {
    global $conn;
    
    $user_id = $_POST['user_id'] ?? 0;
    $is_locked = $_POST['is_locked'] ?? 0;
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        return;
    }
    
    try {
        $locked_until = $is_locked ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : NULL;
        
        $stmt = $conn->prepare("UPDATE users SET is_locked = ?, locked_until = ? WHERE user_id = ?");
        $stmt->bind_param("isi", $is_locked, $locked_until, $user_id);
        
        if ($stmt->execute()) {
            $status = $is_locked ? 'locked' : 'unlocked';
            echo json_encode(['success' => true, 'message' => "User $status successfully"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function updateUserRole() {
    global $conn;
    
    $user_id = $_POST['user_id'] ?? 0;
    $role = $_POST['role'] ?? '';
    
    if ($user_id <= 0 || empty($role)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        return;
    }
    
    // Don't allow changing your own role
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot change your own role']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        $stmt->bind_param("si", $role, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'User role updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user role']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function markNotificationRead() {
    global $conn;
    
    $notification_id = $_POST['notification_id'] ?? 0;
    
    if ($notification_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        return;
    }
    
    try {
        // Since notifications table doesn't have is_read column, we'll delete it instead
        $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ?");
        $stmt->bind_param("i", $notification_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read and removed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function markAllNotificationsRead() {
    global $conn;
    
    try {
        $stmt = $conn->prepare("DELETE FROM notifications");
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'All notifications marked as read and removed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function deleteNotification() {
    global $conn;
    
    $notification_id = $_POST['notification_id'] ?? 0;
    
    if ($notification_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        return;
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ?");
        $stmt->bind_param("i", $notification_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Notification deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

function clearAllNotifications() {
    global $conn;
    
    try {
        $stmt = $conn->prepare("DELETE FROM notifications");
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to clear notifications']);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

// ============================================
// HTML/FRONTEND CONTINUES BELOW
// ============================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - IoT Management System</title>
    <style>
       * {
           margin: 0;
           padding: 0;
           box-sizing: border-box;
       }
    
       body {
           font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', sans-serif;
           background-color: #e8eaed;
           color: #202124;
           line-height: 1.5;
       }
    
       .container {
           display: flex;
           height: 100vh;
       }
    
       /* Sidebar Navigation */
       .sidebar {
           width: 250px;
           background: #f5f5f5;
           color: #202124;
           padding: 20px;
           overflow-y: auto;
           box-shadow: 1px 0 3px rgba(0, 0, 0, 0.08);
           border-right: 1px solid #dadce0;
       }
    
       .sidebar h2 {
           margin-bottom: 30px;
           text-align: center;
           font-size: 20px;
           font-weight: 700;
           color: #202124;
       }
    
       .sidebar ul {
           list-style: none;
       }
    
       .sidebar li {
           margin-bottom: 15px;
       }
    
       .sidebar a {
           color: #3c4043;
           text-decoration: none;
           display: block;
           padding: 10px 12px;
           border-radius: 6px;
           transition: all 0.2s ease;
           font-size: 14px;
           font-weight: 600;
       }
    
       .sidebar a:hover {
           background-color: #dadce0;
           color: #202124;
       }
    
       .sidebar a.active {
           background-color: #c5cad1;
           color: #202124;
           font-weight: 700;
       }
    
       /* Main Content Area */
       .main-content {
           flex: 1;
           display: flex;
           flex-direction: column;
           overflow: hidden;
       }
    
       /* Header */
       .header {
           background-color: #f5f5f5;
           padding: 16px 30px;
           box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
           border-bottom: 1px solid #dadce0;
           display: flex;
           justify-content: space-between;
           align-items: center;
       }
    
       .header h1 {
           font-size: 24px;
           color: #202124;
           font-weight: 700;
           letter-spacing: -0.5px;
       }
    
       .user-info {
           display: flex;
           align-items: center;
           gap: 20px;
       }
    
       .user-avatar {
           width: 40px;
           height: 40px;
           border-radius: 50%;
           background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
           color: white;
           display: flex;
           align-items: center;
           justify-content: center;
           font-weight: 600;
           font-size: 13px;
       }
    
       /* Content Area */
       .content {
           flex: 1;
           padding: 30px;
           overflow-y: auto;
       }
    
       /* Dashboard Stats */
       .stats-container {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
           gap: 20px;
           margin-bottom: 30px;
       }
    
       .stat-card {
           background: #ffffff;
           padding: 20px;
           border-radius: 8px;
           box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
           border: 1px solid #dadce0;
           transition: all 0.2s ease;
       }
    
       .stat-card:hover {
           box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
           border-color: #d1d5db;
       }
    
       .stat-card h3 {
           color: #5f6368;
           font-size: 12px;
           margin-bottom: 12px;
           text-transform: uppercase;
           letter-spacing: 0.5px;
           font-weight: 700;
       }
    
       .stat-card .number {
           font-size: 28px;
           font-weight: 800;
           color: #202124;
           letter-spacing: -0.5px;
       }
    
       .stat-card .change {
           color: #27ae60;
           font-size: 12px;
           margin-top: 10px;
       }
    
       /* Section */
       .section {
           display: none;
       }
    
       .section.active {
           display: block;
       }
    
       .section h2 {
           margin-bottom: 24px;
           color: #202124;
           font-size: 22px;
           font-weight: 700;
           letter-spacing: -0.5px;
       }
    
       /* Table Styles */
       .table-container {
           background: #ffffff;
           border-radius: 8px;
           box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
           border: 1px solid #dadce0;
           overflow: hidden;
           margin-bottom: 20px;
       }
    
       table {
           width: 100%;
           border-collapse: collapse;
       }
    
       table thead {
           background-color: #f8f9fa;
       }
    
       table th {
           padding: 12px 15px;
           text-align: left;
           font-weight: 700;
           color: #202124;
           border-bottom: 1px solid #dadce0;
           background-color: #f8f9fa;
           font-size: 13px;
       }
    
       table td {
           padding: 12px 15px;
           border-bottom: 1px solid #dadce0;
           font-size: 14px;
           color: #202124;
       }
    
       table tbody tr:hover {
           background-color: #f8f9fa;
       }
    
       /* Action Buttons */
       .action-btn {
           padding: 6px 12px;
           border: 1px solid #dadce0;
           border-radius: 6px;
           cursor: pointer;
           font-size: 12px;
           font-weight: 700;
           margin-right: 6px;
           transition: all 0.2s ease;
           background: white;
       }
    
       .btn-edit {
           color: #0b57d4;
           border-color: #bdc1c6;
       }
    
       .btn-edit:hover {
           background-color: #f0f7ff;
           border-color: #5f6368;
           color: #0842a4;
       }
    
       .btn-delete {
           color: #d33b27;
           border-color: #bdc1c6;
       }
    
       .btn-delete:hover {
           background-color: #ffe8e6;
           border-color: #5f6368;
           color: #a50e0e;
       }
    
       .btn-primary {
           background-color: #5f6368;
           color: #ffffff;
           padding: 8px 16px;
           border: none;
           border-radius: 6px;
           cursor: pointer;
           font-size: 13px;
           font-weight: 700;
           transition: all 0.2s ease;
           margin-bottom: 20px;
           box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
       }
    
       .btn-primary:hover {
           background-color: #3c4043;
           box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
       }
    
       .btn-primary:active {
           transform: scale(0.98);
       }
    
       /* Status Badge */
       .status-badge {
           padding: 5px 12px;
           border-radius: 20px;
           font-size: 12px;
           font-weight: 600;
       }
    
       .status-active {
           background-color: #d4edda;
           color: #155724;
       }
    
       .status-inactive {
           background-color: #f8d7da;
           color: #721c24;
       }
    
       /* Form Styles */
       .form-group {
           margin-bottom: 20px;
       }
    
       .form-group label {
           display: block;
           margin-bottom: 8px;
           font-weight: 700;
           color: #202124;
       }
    
       .form-group input,
       .form-group select,
       .form-group textarea {
           width: 100%;
           padding: 8px 12px;
           border: 1px solid #dadce0;
           border-radius: 6px;
           font-size: 14px;
           font-family: inherit;
           background-color: #fafbfc;
           transition: all 0.2s ease;
           color: #202124;
       }
    
       .form-group input:focus,
       .form-group select:focus,
       .form-group textarea:focus {
           outline: none;
           border-color: #5f6368;
           background-color: white;
           box-shadow: 0 0 0 3px rgba(95, 99, 104, 0.1);
       }
    
       /* Modal Styles */
       .modal {
           display: none;
           position: fixed;
           z-index: 1000;
           left: 0;
           top: 0;
           width: 100%;
           height: 100%;
           background-color: rgba(0, 0, 0, 0.4);
       }
    
       .modal.active {
           display: flex;
           align-items: center;
           justify-content: center;
       }
    
       .modal-content {
           background-color: #f5f5f5;
           padding: 30px;
           border-radius: 8px;
           box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
           max-width: 500px;
           width: 90%;
       }
    
       .modal-header {
           display: flex;
           justify-content: space-between;
           align-items: center;
           margin-bottom: 20px;
       }
    
       .modal-header h2 {
           color: #202124;
           font-size: 20px;
           font-weight: 700;
       }
    
       .close-btn {
           background: none;
           border: none;
           font-size: 28px;
           cursor: pointer;
           color: #999;
       }
    
       .close-btn:hover {
           color: #333;
       }
    
       /* Search bar & toggle switch styles */
       .search-input {
           font-size: 14px;
           padding: 8px 12px;
           border-radius: 6px;
           border: 1px solid #dadce0;
           width: 100%;
           background-color: #fafbfc;
           transition: all 0.2s ease;
           color: #202124;
       }
    
       .search-input:focus {
           outline: none;
           border-color: #5f6368;
           background-color: white;
           box-shadow: 0 0 0 3px rgba(95, 99, 104, 0.1);
       }
    
       .toggle-switch {
           position: relative;
           display: inline-block;
           width: 46px;
           height: 26px;
       }
    
       .toggle-switch input { display:none; }
    
       .toggle-switch .slider {
           position: absolute;
           cursor: pointer;
           top: 0;
           left: 0;
           right: 0;
           bottom: 0;
           background-color: #bdc1c6;
           transition: .2s;
           border-radius: 26px;
       }
    
       .toggle-switch .slider:before {
           position: absolute;
           content: "";
           height: 20px;
           width: 20px;
           left: 3px;
           bottom: 3px;
           background-color: white;
           transition: .2s;
           border-radius: 50%;
           box-shadow: 0 1px 3px rgba(0,0,0,0.2);
       }
    
       .toggle-switch input:checked + .slider {
           background-color: #4ade80;
       }
    
       .toggle-switch input:checked + .slider:before {
           transform: translateX(20px);
       }
    
       /* Notification Badge */
       .notification-badge {
           display: inline-block;
           background-color: #ef4444;
           color: white;
           font-size: 11px;
           font-weight: bold;
           border-radius: 50%;
           width: 18px;
           height: 18px;
           text-align: center;
           line-height: 18px;
           margin-left: 5px;
       }
    
       /* System Status Badge */
       .status-online {
           background-color: #d4edda;
           color: #155724;
           font-weight: 700;
       }
    
       .status-offline {
           background-color: #f8d7da;
           color: #721c24;
           font-weight: 700;
       }
    
       /* New styles for backend functionality */
       .loading {
           color: #6b7280;
           font-style: italic;
           text-align: center;
           padding: 20px;
       }
    
       .no-data {
           color: #9ca3af;
           text-align: center;
           padding: 20px;
           font-style: italic;
       }
    
       .error-message {
           color: #dc2626;
           text-align: center;
           padding: 10px;
           background: #fef2f2;
           border-radius: 6px;
           margin: 10px 0;
       }
    
       .success-message {
           color: #059669;
           text-align: center;
           padding: 10px;
           background: #f0fdf4;
           border-radius: 6px;
           margin: 10px 0;
       }
    
       .role-badge {
           padding: 4px 8px;
           border-radius: 12px;
           font-size: 11px;
           font-weight: 600;
           text-transform: uppercase;
       }
    
       .role-super_admin {
           background-color: #fef3c7;
           color: #92400e;
       }
    
       .role-admin {
           background-color: #dbeafe;
           color: #1e40af;
       }
    
       .role-user {
           background-color: #f3f4f6;
           color: #4b5563;
       }
    
       .locked-badge {
           background-color: #fee2e2;
           color: #991b1b;
           padding: 4px 8px;
           border-radius: 12px;
           font-size: 11px;
           font-weight: 600;
       }
    
       /* Toast message animation */
       @keyframes slideIn {
           from {
               transform: translateX(100%);
               opacity: 0;
           }
           to {
               transform: translateX(0);
               opacity: 1;
           }
       }
    
       /* Responsive */
       @media (max-width: 768px) {
           .container {
               flex-direction: column;
           }
        
            .sidebar {
                width: 100%;
                height: auto;
            }

            .sidebar ul {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }

            .sidebar li {
                margin-bottom: 0;
            }

            .stats-container {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                min-width: 600px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>IoT Dashboard</h2>
            <ul>
                <li><a href="#" class="nav-link active" data-section="dashboard">Dashboard</a></li>
                <li><a href="#" class="nav-link" data-section="users">User Management</a></li>
                <li><a href="#" class="nav-link" data-section="devices">Device Management</a></li>
                <li><a href="#" class="nav-link" data-section="failed-logins">Failed Logins 
                    <span id="failed-login-badge" class="notification-badge" style="display: none;">0</span>
                </a></li>
                <li><a href="#" class="nav-link" data-section="notifications">Notifications 
                    <span id="notification-badge" class="notification-badge" style="display: none;">0</span>
                </a></li>
                <li><a href="#" onclick="logout()">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1 id="page-title">Super Admin Dashboard</h1>
                <div class="user-info">
                    <span id="current-time"></span>
                    <div class="user-avatar"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content">
                <!-- Dashboard Section -->
                <div class="section active" id="dashboard">
                    <h2>Super Admin Dashboard</h2>

                    <div class="stats-container">
                        <div class="stat-card">
                            <h3>Total Users</h3>
                            <div class="number" id="stat-total-users">—</div>
                        </div>
                        <div class="stat-card">
                            <h3>Total Devices</h3>
                            <div class="number" id="stat-total-devices">—</div>
                        </div>
                        <div class="stat-card">
                            <h3>Active Devices</h3>
                            <div class="number" id="stat-active-devices">—</div>
                        </div>
                        <div class="stat-card">
                            <h3>Recent Logins</h3>
                            <div class="number" id="stat-recent-logins">—</div>
                        </div>
                        <div class="stat-card">
                            <h3>Failed Logins (24h)</h3>
                            <div class="number" id="stat-failed-logins">—</div>
                        </div>
                        <div class="stat-card">
                            <h3>System Status</h3>
                            <div class="number" id="stat-status" style="font-size: 18px; padding: 8px 12px; border-radius: 4px; display: inline-block;">—</div>
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; margin-bottom:18px; flex-wrap:wrap; align-items:center;">
                        <button class="btn-primary" onclick="openUserModal()" style="margin-bottom: 0;">+ Add New User</button>
                        <button class="btn-primary" onclick="openDeviceModal()" style="margin-bottom: 0;">+ Add New Device</button>
                        <button class="action-btn" onclick="refreshDashboard()">Refresh All</button>
                        <button class="action-btn" onclick="exportData()">Export Data</button>
                    </div>

                    <div class="table-container" style="margin-bottom:20px;">
                        <h3 style="padding: 16px 15px 0 15px; color: #202124; font-weight: 700;">Recent Users</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="recent-users-tbody">
                                <tr><td colspan="5" class="loading">Loading users...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-container">
                        <h3 style="padding:16px 15px 0 15px; color:#202124; font-weight:700;">Recent Failed Logins</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>IP Address</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="recent-failed-logins-tbody">
                                <tr><td colspan="4" class="loading">Loading failed logins...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- User Management Section -->
                <div class="section" id="users">
                    <h2>User Management</h2>

                    <div style="margin: 18px 0; display:flex; justify-content:space-between; align-items:flex-end; gap:12px; flex-wrap:wrap;">
                        <div class="search-bar" style="flex:1; min-width:240px;">
                            <input id="user-search" class="search-input" type="search" placeholder="Search users by name or email..." />
                        </div>
                        <div style="min-width:160px;">
                            <button class="btn-primary" onclick="searchUsers()" style="margin-bottom: 0;">Search</button>
                        </div>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="users-tbody">
                                <tr><td colspan="6" class="loading">Loading users...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Device Management Section -->
                <div class="section" id="devices">
                    <h2>Device Management</h2>
                    <button class="btn-primary" onclick="openDeviceModal()">+ Add New Device</button>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="devices-tbody">
                                <tr><td colspan="4" class="loading">Loading devices...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Failed Logins Section -->
                <div class="section" id="failed-logins">
                    <h2>Failed Login Attempts</h2>
                    
                    <div style="margin: 18px 0; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                        <div style="display:flex; gap:12px;">
                            <button class="btn-primary" onclick="clearOldFailedLogins()" style="margin-bottom: 0;">Clear Old (30+ days)</button>
                        </div>
                        <div>
                            <span style="font-weight:700; color:#5f6368;" id="failed-logins-count">0 attempts</span>
                        </div>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>IP Address</th>
                                    <th>Session ID</th>
                                </tr>
                            </thead>
                            <tbody id="failed-logins-tbody">
                                <tr><td colspan="4" class="loading">Loading failed login attempts...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Notifications Section -->
                <div class="section" id="notifications">
                    <h2>Notifications</h2>

                    <div style="margin: 18px 0; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                        <div style="display:flex; gap:12px;">
                            <button class="btn-primary" onclick="markAllNotificationsAsRead()" style="margin-bottom: 0;">Mark All as Read</button>
                            <button class="action-btn" onclick="clearAllNotifications()" style="color:#d33b27; margin-bottom: 0;">Clear All</button>
                        </div>
                        <div>
                            <span style="font-weight:700; color:#5f6368;" id="unread-count">0 Unread</span>
                        </div>
                    </div>

                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Time</th>
                                    <th>Message</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="notifications-tbody">
                                <tr><td colspan="5" class="loading">Loading notifications...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New User</h2>
                <button class="close-btn" onclick="closeUserModal()">×</button>
            </div>
            <form id="addUserForm" onsubmit="handleAddUser(event)">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required />
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required />
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required />
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 20px;">Create User</button>
            </form>
        </div>
    </div>

    <!-- Device Modal -->
    <div class="modal" id="deviceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Device</h2>
                <button class="close-btn" onclick="closeDeviceModal()">×</button>
            </div>
            <form id="addDeviceForm" onsubmit="handleAddDevice(event)">
                <div class="form-group">
                    <label>Device Name</label>
                    <input type="text" name="name" required />
                </div>
                <div class="form-group">
                    <label>Device Type</label>
                    <select name="type" required>
                        <option value="sensor">Sensor</option>
                        <option value="controller">Controller</option>
                        <option value="camera">Camera</option>
                        <option value="alarm">Alarm</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 20px;">Add Device</button>
            </form>
        </div>
    </div>

    <script>
        // ============================================
        // UTILITY FUNCTIONS
        // ============================================
        
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // ============================================
        // NAVIGATION
        // ============================================
        
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                
                document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
                const sectionId = link.dataset.section;
                document.getElementById(sectionId).classList.add('active');
                
                document.getElementById('page-title').textContent = 
                    link.textContent.replace(/[0-9]/g, '').trim();
                
                // Load data for the section
                loadSection(sectionId);
            });
        });

        // ============================================
        // TIME UPDATER
        // ============================================
        
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', { 
                year: 'numeric', 
                month: '2-digit', 
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }
        
        updateTime();
        setInterval(updateTime, 1000);

        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        
        function openUserModal() {
            document.getElementById('userModal').classList.add('active');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
            document.getElementById('addUserForm').reset();
        }

        function openDeviceModal() {
            document.getElementById('deviceModal').classList.add('active');
        }

        function closeDeviceModal() {
            document.getElementById('deviceModal').classList.remove('active');
            document.getElementById('addDeviceForm').reset();
        }

        // ============================================
        // API CALL FUNCTIONS
        // ============================================
        
        async function fetchData(endpoint, params = {}) {
            try {
                const queryString = new URLSearchParams(params).toString();
                const url = `?api=${endpoint}&${queryString}`;
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Check if the response contains an error
                if (data && data.error) {
                    console.error(`API error for ${endpoint}:`, data.error);
                    showMessage('error', `API Error: ${data.error}`);
                    return null;
                }
                
                return data;
            } catch (error) {
                console.error(`Error fetching ${endpoint}:`, error);
                showMessage('error', `Failed to load ${endpoint.replace('-', ' ')}`);
                return null;
            }
        }

        async function postData(endpoint, data) {
            try {
                const formData = new FormData();
                for (const key in data) {
                    if (data[key] !== undefined && data[key] !== null) {
                        formData.append(key, data[key]);
                    }
                }
                
                const response = await fetch(`?api=${endpoint}`, {
                    method: 'POST',
                    body: formData
                });
                
                return await response.json();
            } catch (error) {
                console.error(`Error posting to ${endpoint}:`, error);
                return { success: false, message: 'Network error' };
            }
        }

        // ============================================
        // DASHBOARD FUNCTIONS
        // ============================================
        
        async function loadDashboard() {
            try {
                // Load stats
                const stats = await fetchData('dashboard-stats');
                if (stats) {
                    document.getElementById('stat-total-users').textContent = stats.totalUsers || 0;
                    document.getElementById('stat-total-devices').textContent = stats.totalDevices || 0;
                    document.getElementById('stat-active-devices').textContent = stats.activeDevices || 0;
                    document.getElementById('stat-recent-logins').textContent = stats.recentLogins || 0;
                    document.getElementById('stat-failed-logins').textContent = stats.failedLogins24h || 0;
                    
                    // System status
                    const status = await fetchData('system-status');
                    if (status) {
                        const statusEl = document.getElementById('stat-status');
                        statusEl.textContent = status.status;
                        statusEl.className = status.isOnline ? 'status-online' : 'status-offline';
                    }
                    
                    // Update notification badges
                    updateNotificationBadges(stats);
                }
                
                // Load recent users
                const recentUsers = await fetchData('recent-users', { limit: 5 });
                renderRecentUsers(recentUsers);
                
                // Load recent failed logins
                const failedLogins = await fetchData('failed-logins', { limit: 5 });
                renderRecentFailedLogins(failedLogins);
                
            } catch (error) {
                console.error('Error loading dashboard:', error);
            }
        }

        function renderRecentUsers(users) {
            const tbody = document.getElementById('recent-users-tbody');
            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="no-data">No users found</td></tr>';
                return;
            }
            
            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>${user.user_id}</td>
                    <td>${escapeHtml(user.name || 'N/A')}</td>
                    <td>${escapeHtml(user.email || 'N/A')}</td>
                    <td><span class="role-badge role-${user.role || 'user'}">${escapeHtml(user.role || 'user')}</span></td>
                    <td>${user.is_locked ? '<span class="locked-badge">Locked</span>' : '<span class="status-badge status-active">Active</span>'}</td>
                </tr>
            `).join('');
        }

        function renderRecentFailedLogins(attempts) {
            const tbody = document.getElementById('recent-failed-logins-tbody');
            if (!attempts || attempts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="no-data">No failed login attempts</td></tr>';
                return;
            }
            
            tbody.innerHTML = attempts.map(attempt => `
                <tr>
                    <td>${formatDate(attempt.attempt_time)}</td>
                    <td>${escapeHtml(attempt.username || attempt.email || 'Unknown')}</td>
                    <td><code>${escapeHtml(attempt.ip_address || 'N/A')}</code></td>
                    <td><span class="status-badge status-inactive">Failed</span></td>
                </tr>
            `).join('');
        }

        // ============================================
        // USER MANAGEMENT FUNCTIONS
        // ============================================
        
        async function loadUsers() {
            try {
                const users = await fetchData('users');
                renderUsers(users);
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        function searchUsers() {
            const search = document.getElementById('user-search').value.trim();
            loadUsersWithSearch(search);
        }

        async function loadUsersWithSearch(search = '') {
            try {
                const users = await fetchData('users', { search });
                renderUsers(users);
            } catch (error) {
                console.error('Error searching users:', error);
            }
        }

        function renderUsers(users) {
            const tbody = document.getElementById('users-tbody');
            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="no-data">No users found</td></tr>';
                return;
            }
            
            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>${user.user_id}</td>
                    <td>${escapeHtml(user.name || 'N/A')}</td>
                    <td>${escapeHtml(user.email || 'N/A')}</td>
                    <td>
                        <select class="role-select" data-user-id="${user.user_id}" onchange="updateUserRole(${user.user_id}, this.value)">
                            <option value="user" ${user.role === 'user' ? 'selected' : ''}>User</option>
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                            <option value="super_admin" ${user.role === 'super_admin' ? 'selected' : ''}>Super Admin</option>
                        </select>
                    </td>
                    <td>
                        <label class="toggle-switch">
                            <input type="checkbox" data-user-id="${user.user_id}" 
                                   ${user.is_locked ? 'checked' : ''} 
                                   onchange="toggleUserLock(${user.user_id}, this.checked)">
                            <span class="slider"></span>
                        </label>
                    </td>
                    <td>
                        <button class="action-btn btn-edit" onclick="editUser(${user.user_id})">Edit</button>
                        <button class="action-btn btn-delete" onclick="deleteUser(${user.user_id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        async function handleAddUser(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            try {
                const result = await postData('add-user', Object.fromEntries(formData));
                if (result.success) {
                    showMessage('success', result.message);
                    closeUserModal();
                    loadDashboard();
                    loadUsers();
                } else {
                    showMessage('error', result.message);
                }
            } catch (error) {
                console.error('Error adding user:', error);
                showMessage('error', 'Failed to add user');
            }
        }

        async function toggleUserLock(userId, isLocked) {
            try {
                const result = await postData('update-user-status', {
                    user_id: userId,
                    is_locked: isLocked ? 1 : 0
                });
                
                if (result.success) {
                    showMessage('success', result.message);
                } else {
                    showMessage('error', result.message);
                }
            } catch (error) {
                console.error('Error toggling user lock:', error);
                showMessage('error', 'Failed to update user status');
            }
        }

        async function updateUserRole(userId, newRole) {
            try {
                const result = await postData('update-user-role', {
                    user_id: userId,
                    role: newRole
                });
                
                if (result.success) {
                    showMessage('success', result.message);
                } else {
                    showMessage('error', result.message);
                }
            } catch (error) {
                console.error('Error updating user role:', error);
                showMessage('error', 'Failed to update user role');
            }
        }

        async function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                try {
                    const result = await postData('delete-user', { user_id: userId });
                    if (result.success) {
                        showMessage('success', result.message);
                        loadDashboard();
                        loadUsers();
                    } else {
                        showMessage('error', result.message);
                    }
                } catch (error) {
                    console.error('Error deleting user:', error);
                    showMessage('error', 'Failed to delete user');
                }
            }
        }

        // ============================================
        // DEVICE MANAGEMENT FUNCTIONS
        // ============================================
        
        async function loadDevices() {
            try {
                const devices = await fetchData('devices');
                renderDevices(devices);
            } catch (error) {
                console.error('Error loading devices:', error);
            }
        }

        function renderDevices(devices) {
            const tbody = document.getElementById('devices-tbody');
            if (!devices || devices.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="no-data">No devices found</td></tr>';
                return;
            }
            
            tbody.innerHTML = devices.map(device => `
                <tr>
                    <td>${device.device_id}</td>
                    <td>${escapeHtml(device.name || 'N/A')}</td>
                    <td><span class="status-badge ${device.status === 'active' ? 'status-active' : 'status-inactive'}">${escapeHtml(device.status || 'unknown')}</span></td>
                    <td>
                        <button class="action-btn btn-edit" onclick="editDevice(${device.device_id})">Edit</button>
                        <button class="action-btn btn-delete" onclick="deleteDevice(${device.device_id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        async function handleAddDevice(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            
            try {
                const result = await postData('add-device', Object.fromEntries(formData));
                if (result.success) {
                    showMessage('success', result.message);
                    closeDeviceModal();
                    loadDashboard();
                    loadDevices();
                } else {
                    showMessage('error', result.message);
                }
            } catch (error) {
                console.error('Error adding device:', error);
                showMessage('error', 'Failed to add device');
            }
        }

        async function deleteDevice(deviceId) {
            if (confirm('Are you sure you want to delete this device?')) {
                try {
                    const result = await postData('delete-device', { device_id: deviceId });
                    if (result.success) {
                        showMessage('success', result.message);
                        loadDashboard();
                        loadDevices();
                    } else {
                        showMessage('error', result.message);
                    }
                } catch (error) {
                    console.error('Error deleting device:', error);
                    showMessage('error', 'Failed to delete device');
                }
            }
        }

        // ============================================
        // NOTIFICATION FUNCTIONS
        // ============================================
        
        async function loadNotifications() {
            try {
                const notifications = await fetchData('notifications');
                renderNotifications(notifications);
            } catch (error) {
                console.error('Error loading notifications:', error);
            }
        }

        function renderNotifications(notifications) {
            const tbody = document.getElementById('notifications-tbody');
            if (!notifications || notifications.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="no-data">No notifications found</td></tr>';
                document.getElementById('unread-count').textContent = '0 Unread';
                return;
            }
            
            // Since no is_read column exists, all notifications are considered "unread"
            const unreadCount = notifications.length;
            document.getElementById('unread-count').textContent = unreadCount + ' Unread';
            
            tbody.innerHTML = notifications.map(notif => `
                <tr>
                    <td>${notif.notification_id}</td>
                    <td>${formatDate(notif.created_at)}</td>
                    <td>${escapeHtml(notif.message || 'No message')}</td>
                    <td><span class="status-badge status-inactive">Unread</span></td>
                    <td>
                        <button class="action-btn btn-delete" onclick="deleteNotification(${notif.notification_id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        async function markNotificationAsRead(notificationId) {
            if (confirm('Mark this notification as read (it will be deleted)?')) {
                try {
                    const result = await postData('mark-notification-read', { notification_id: notificationId });
                    if (result.success) {
                        showMessage('success', result.message);
                        loadNotifications();
                        loadDashboard();
                    }
                } catch (error) {
                    console.error('Error marking notification as read:', error);
                }
            }
        }

        async function markAllNotificationsAsRead() {
            if (confirm('Mark all notifications as read (they will be deleted)?')) {
                try {
                    const result = await postData('mark-all-notifications-read', {});
                    if (result.success) {
                        showMessage('success', result.message);
                        loadNotifications();
                        loadDashboard();
                    }
                } catch (error) {
                    console.error('Error marking all notifications as read:', error);
                }
            }
        }

        async function deleteNotification(notificationId) {
            if (confirm('Delete this notification?')) {
                try {
                    const result = await postData('delete-notification', { notification_id: notificationId });
                    if (result.success) {
                        showMessage('success', result.message);
                        loadNotifications();
                        loadDashboard();
                    }
                } catch (error) {
                    console.error('Error deleting notification:', error);
                }
            }
        }

        async function clearAllNotifications() {
            if (confirm('Are you sure you want to clear all notifications?')) {
                try {
                    const result = await postData('clear-all-notifications', {});
                    if (result.success) {
                        showMessage('success', result.message);
                        loadNotifications();
                        loadDashboard();
                    }
                } catch (error) {
                    console.error('Error clearing notifications:', error);
                }
            }
        }

        // ============================================
        // HELPER FUNCTIONS
        // ============================================
        
        function showMessage(type, message) {
            // Remove existing messages
            const existing = document.querySelector('.message-container');
            if (existing) existing.remove();
            
            // Create message container
            const container = document.createElement('div');
            container.className = 'message-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                border-radius: 6px;
                color: white;
                font-weight: 500;
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            
            if (type === 'success') {
                container.style.backgroundColor = '#10b981';
            } else {
                container.style.backgroundColor = '#ef4444';
            }
            
            container.textContent = message;
            document.body.appendChild(container);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (container.parentNode) {
                    container.remove();
                }
            }, 5000);
        }

        function updateNotificationBadges(stats) {
            // Update failed login badge
            const failedLoginBadge = document.getElementById('failed-login-badge');
            if (failedLoginBadge && stats.failedLogins24h > 0) {
                failedLoginBadge.textContent = stats.failedLogins24h > 99 ? '99+' : stats.failedLogins24h;
                failedLoginBadge.style.display = 'inline-block';
            } else if (failedLoginBadge) {
                failedLoginBadge.style.display = 'none';
            }
            
            // Update notification badge
            const notificationBadge = document.getElementById('notification-badge');
            if (notificationBadge && stats.unreadNotifications > 0) {
                notificationBadge.textContent = stats.unreadNotifications > 99 ? '99+' : stats.unreadNotifications;
                notificationBadge.style.display = 'inline-block';
            } else if (notificationBadge) {
                notificationBadge.style.display = 'none';
            }
        }

        function loadSection(sectionId) {
            switch(sectionId) {
                case 'dashboard':
                    loadDashboard();
                    break;
                case 'users':
                    loadUsers();
                    break;
                case 'devices':
                    loadDevices();
                    break;
                case 'failed-logins':
                    loadFailedLogins();
                    break;
                case 'notifications':
                    loadNotifications();
                    break;
            }
        }

        async function loadFailedLogins() {
            try {
                const attempts = await fetchData('failed-logins', { limit: 50 });
                renderFailedLogins(attempts);
            } catch (error) {
                console.error('Error loading failed logins:', error);
            }
        }

        function renderFailedLogins(attempts) {
            const tbody = document.getElementById('failed-logins-tbody');
            if (!attempts || attempts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="no-data">No failed login attempts found</td></tr>';
                document.getElementById('failed-logins-count').textContent = '0 attempts';
                return;
            }
            
            tbody.innerHTML = attempts.map(attempt => `
                <tr>
                    <td>${formatDate(attempt.attempt_time)}</td>
                    <td>${escapeHtml(attempt.username || attempt.email || 'Unknown')}</td>
                    <td><code>${escapeHtml(attempt.ip_address || 'N/A')}</code></td>
                    <td><code>${escapeHtml(attempt.session_id || 'N/A')}</code></td>
                </tr>
            `).join('');
            
            document.getElementById('failed-logins-count').textContent = attempts.length + ' failed attempts';
        }

        function refreshDashboard() {
            loadDashboard();
            showMessage('success', 'Dashboard refreshed');
        }

        function exportData() {
            // Export users as JSON
            fetch('?api=users&limit=0')
                .then(res => res.json())
                .then(data => {
                    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'users-export-' + new Date().toISOString().split('T')[0] + '.json';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    showMessage('success', 'Data exported successfully');
                })
                .catch(err => {
                    console.error('Export error:', err);
                    showMessage('error', 'Failed to export data');
                });
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        }

        async function clearOldFailedLogins() {
            if (confirm('Clear failed login attempts older than 30 days?')) {
                try {
                    // Note: You'll need to add this endpoint to your PHP code
                    const result = await postData('clear-old-failed-logins', {});
                    if (result && result.success) {
                        showMessage('success', result.message);
                        loadFailedLogins();
                        loadDashboard();
                    } else {
                        showMessage('error', 'Feature not implemented yet');
                    }
                } catch (error) {
                    console.error('Error clearing old failed logins:', error);
                    showMessage('error', 'Failed to clear old failed logins');
                }
            }
        }

        // ============================================
        // INITIAL LOAD
        // ============================================
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initial load
            loadDashboard();
            
            // Auto-refresh dashboard every 30 seconds
            setInterval(loadDashboard, 30000);
            
            // Add search input listener
            const searchInput = document.getElementById('user-search');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(event) {
                    if (event.key === 'Enter') {
                        searchUsers();
                    }
                });
            }
        });
    </script>
</body>
</html>