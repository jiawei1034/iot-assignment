<?php
session_start();
// Example: set $_SESSION['user_name'] when user logs in.
$currentUserName = $_SESSION['user_name'] ?? 'Super Admin';
// Build initials (max 2 chars)
$parts = preg_split('/\s+/', trim($currentUserName));
$initials = '';
foreach ($parts as $p) {
    if ($p !== '') $initials .= strtoupper($p[0]);
}
$initials = substr($initials, 0, 2);

// API endpoint for system status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['api']) && $_GET['api'] === 'system-status') {
    header('Content-Type: application/json');
    
    // Check if XAMPP services are running
    $isOnline = false;
    
    // Method 1: Check if Apache is running on localhost:80
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/');
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // If we get any valid HTTP response (not connection error), system is online
    if ($httpCode > 0 && $httpCode < 600) {
        $isOnline = true;
    }
    
    // Method 2: Check if MySQL is accessible (additional check)
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
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - IoT Management System</title>
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
            background-color: #4ade80; /* green when active */
        }

        .toggle-switch input:checked + .slider:before {
            transform: translateX(20px);
        }

        /* Theme Toggle Button - REMOVED */
        #theme-toggle {
            display: none !important;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>Iot Assignment</h2>
            <ul>
                <li><a href="#" class="nav-link active" data-section="dashboard">Dashboard</a></li>
                <li><a href="#" class="nav-link" data-section="users">User Management</a></li>
                <li><a href="#" class="nav-link" data-section="devices">Device Management</a></li>
                <li><a href="#" class="nav-link" data-section="logs">Activity Logs</a></li>
                <li><a href="#" class="nav-link" data-section="notifications">Notifications</a></li>
                <li><a href="#" onclick="logout()">Logout</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1 id="page-title">Dashboard</h1>
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
                                <div class="number" id="stat-users">—</div>
                                <div class="change" id="stat-users-change"></div>
                            </div>
                            <div class="stat-card">
                                <h3>Active Devices</h3>
                                <div class="number" id="stat-devices">—</div>
                            </div>
                            <div class="stat-card">
                                <h3>Recent Logins</h3>
                                <div class="number" id="stat-logins">—</div>
                            </div>
                            <div class="stat-card">
                                <h3>System Status</h3>
                                <div class="number" id="stat-status" style="font-size: 18px; padding: 8px 12px; border-radius: 4px; display: inline-block;">Online</div>
                            </div>
                        </div>

                        <div style="display:flex; gap:12px; margin-bottom:18px; flex-wrap:wrap; align-items:center;">
                            <button class="btn-primary" onclick="openUserModal()" style="margin-bottom: 0;">+ Add New User</button>
                            <button class="action-btn" onclick="fetchStats()">Refresh Stats</button>
                            <button class="action-btn" onclick="exportUsers()">Export Users</button>
                            <div style="margin-left:auto; display:flex; align-items:center; gap:12px;">
                                <span style="font-weight:700; color:#5f6368;">Maintenance</span>
                                <label class="toggle-switch" style="margin:0;">
                                    <input type="checkbox" id="maintenance-toggle" onchange="toggleMaintenanceMode(this.checked)">
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        <div class="table-container" style="margin-bottom:20px;">
                            <h3 style="padding: 16px 15px 0 15px; color: #202124; font-weight: 700;">Recent Accounts</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Contact (Email)</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="dashboard-users-tbody">
                                    <tr><td colspan="6">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="table-container">
                            <h3 style="padding:16px 15px 0 15px; color:#202124; font-weight:700;">Recent Activity</h3>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Resource</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="recent-activity-tbody">
                                    <tr>
                                        <td>2025-11-27 14:30:22</td>
                                        <td>Admin</td>
                                        <td>User Created</td>
                                        <td>User #1004</td>
                                        <td><span class="status-badge status-active">Success</span></td>
                                    </tr>
                                    <tr>
                                        <td>2025-11-27 14:25:15</td>
                                        <td>John Doe</td>
                                        <td>Device Connected</td>
                                        <td>DEV004</td>
                                        <td><span class="status-badge status-active">Success</span></td>
                                    </tr>
                                    <tr>
                                        <td>2025-11-27 14:20:08</td>
                                        <td>Jane Smith</td>
                                        <td>Settings Modified</td>
                                        <td>System Config</td>
                                        <td><span class="status-badge status-active">Success</span></td>
                                    </tr>
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
                            <button class="btn-primary" onclick="fetchUsers(document.getElementById('user-search').value.trim(), 'users-tbody')" style="margin-bottom: 0;">Refresh</button>
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
                                <tr><td colspan="6">Loading users...</td></tr>
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
                                    <th>Device ID</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Owner</th>
                                    <th>Status</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>DEV001</td>
                                    <td>Temperature Sensor</td>
                                    <td>Sensor</td>
                                    <td>John Doe</td>
                                    <td><span class="status-badge status-active">Online</span></td>
                                    <td>2 min ago</td>
                                    <td>
                                        <button class="action-btn btn-edit" onclick="editDevice('DEV001')">Edit</button>
                                        <button class="action-btn btn-delete" onclick="deleteDevice('DEV001')">Delete</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>DEV002</td>
                                    <td>Light Controller</td>
                                    <td>Controller</td>
                                    <td>Jane Smith</td>
                                    <td><span class="status-badge status-active">Online</span></td>
                                    <td>5 min ago</td>
                                    <td>
                                        <button class="action-btn btn-edit" onclick="editDevice('DEV002')">Edit</button>
                                        <button class="action-btn btn-delete" onclick="deleteDevice('DEV002')">Delete</button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>DEV003</td>
                                    <td>Security Camera</td>
                                    <td>Camera</td>
                                    <td>Bob Wilson</td>
                                    <td><span class="status-badge status-inactive">Offline</span></td>
                                    <td>1 hour ago</td>
                                    <td>
                                        <button class="action-btn btn-edit" onclick="editDevice('DEV003')">Edit</button>
                                        <button class="action-btn btn-delete" onclick="deleteDevice('DEV003')">Delete</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Activity Logs Section -->
                <div class="section" id="logs">
                    <h2>Activity Logs</h2>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Resource</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>2025-11-27 14:30:22</td>
                                    <td>Admin</td>
                                    <td>User Created</td>
                                    <td>User #1004</td>
                                    <td><span class="status-badge status-active">Success</span></td>
                                </tr>
                                <tr>
                                    <td>2025-11-27 14:25:15</td>
                                    <td>John Doe</td>
                                    <td>Device Connected</td>
                                    <td>DEV004</td>
                                    <td><span class="status-badge status-active">Success</span></td>
                                </tr>
                                <tr>
                                    <td>2025-11-27 14:20:08</td>
                                    <td>Jane Smith</td>
                                    <td>Settings Modified</td>
                                    <td>System Config</td>
                                    <td><span class="status-badge status-active">Success</span></td>
                                </tr>
                                <tr>
                                    <td>2025-11-27 14:15:42</td>
                                    <td>Admin</td>
                                    <td>User Deleted</td>
                                    <td>User #999</td>
                                    <td><span class="status-badge status-active">Success</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Notifications Section -->
                <div class="section" id="notifications">
                    <h2>Notifications</h2>

                    <div style="margin: 18px 0; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
                        <div style="display:flex; gap:12px;">
                            <button class="btn-primary" onclick="markAllAsRead()" style="margin-bottom: 0;">Mark All as Read</button>
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
                                    <th>Status</th>
                                    <th>Timestamp</th>
                                    <th>Type</th>
                                    <th>Message</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="notifications-tbody">
                                <tr>
                                    <td><span style="display:inline-block; width:12px; height:12px; border-radius:50%; background-color:#4ade80; margin-right:8px;"></span></td>
                                    <td>2025-12-02 10:30:22</td>
                                    <td><span class="status-badge" style="background-color:#e3f2fd; color:#1565c0;">System</span></td>
                                    <td>XAMPP system is now online</td>
                                    <td><button class="action-btn btn-delete" onclick="dismissNotification(this)">Dismiss</button></td>
                                </tr>
                                <tr>
                                    <td><span style="display:inline-block; width:12px; height:12px; border-radius:50%; background-color:#4ade80; margin-right:8px;"></span></td>
                                    <td>2025-12-02 09:15:45</td>
                                    <td><span class="status-badge" style="background-color:#f3e5f5; color:#6a1b9a;">Warning</span></td>
                                    <td>Database connection slow - consider optimization</td>
                                    <td><button class="action-btn btn-delete" onclick="dismissNotification(this)">Dismiss</button></td>
                                </tr>
                                <tr>
                                    <td><span style="display:inline-block; width:12px; height:12px; border-radius:50%; background-color:#fbbf24; margin-right:8px;"></span></td>
                                    <td>2025-12-02 08:45:10</td>
                                    <td><span class="status-badge" style="background-color:#fff3e0; color:#e65100;">Alert</span></td>
                                    <td>New user registration: john.smith@example.com</td>
                                    <td><button class="action-btn btn-delete" onclick="dismissNotification(this)">Dismiss</button></td>
                                </tr>
                                <tr>
                                    <td><span style="display:inline-block; width:12px; height:12px; border-radius:50%; background-color:#4ade80; margin-right:8px;"></span></td>
                                    <td>2025-12-02 07:20:33</td>
                                    <td><span class="status-badge" style="background-color:#e8f5e9; color:#2e7d32;">Info</span></td>
                                    <td>Device DEV005 successfully reconnected</td>
                                    <td><button class="action-btn btn-delete" onclick="dismissNotification(this)">Dismiss</button></td>
                                </tr>
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
            <form onsubmit="handleAddUser(event)">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" required />
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" required />
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select required>
                        <option>User</option>
                        <option>Admin</option>
                        <option>Moderator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" required />
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
            <form onsubmit="handleAddDevice(event)">
                <div class="form-group">
                    <label>Device Name</label>
                    <input type="text" required />
                </div>
                <div class="form-group">
                    <label>Device Type</label>
                    <select required>
                        <option>Sensor</option>
                        <option>Controller</option>
                        <option>Camera</option>
                        <option>Switch</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Owner</label>
                    <select required>
                        <option>John Doe</option>
                        <option>Jane Smith</option>
                        <option>Bob Wilson</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" required />
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 20px;">Add Device</button>
            </form>
        </div>
    </div>

    <script>
        // Navigation handling
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Remove active class from all links
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                
                // Hide all sections
                document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
                
                // Show selected section
                const sectionId = link.dataset.section;
                document.getElementById(sectionId).classList.add('active');
                
                // Update page title
                document.getElementById('page-title').textContent = 
                    link.textContent.charAt(0).toUpperCase() + link.textContent.slice(1);
            });
        });

        // Update current time
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

        // Modal functions
        function openUserModal() {
            document.getElementById('userModal').classList.add('active');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.remove('active');
        }

        function openDeviceModal() {
            document.getElementById('deviceModal').classList.add('active');
        }

        function closeDeviceModal() {
            document.getElementById('deviceModal').classList.remove('active');
        }

        // Form handlers
        function handleAddUser(event) {
            event.preventDefault();
            alert('User added successfully!');
            closeUserModal();
            document.querySelector('#userModal form').reset();
        }

        function handleAddDevice(event) {
            event.preventDefault();
            alert('Device added successfully!');
            closeDeviceModal();
            document.querySelector('#deviceModal form').reset();
        }

        // Action handlers
        function editUser(userId) {
            alert('Edit user: ' + userId);
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                alert('User deleted: ' + userId);
            }
        }

        function editDevice(deviceId) {
            alert('Edit device: ' + deviceId);
        }

        function deleteDevice(deviceId) {
            if (confirm('Are you sure you want to delete this device?')) {
                alert('Device deleted: ' + deviceId);
            }
        }

        // Logout function - redirects to server logout endpoint when confirmed
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '/logout.php';
            }
        }

        // Close modals when clicking outside
        window.addEventListener('click', (event) => {
            const userModal = document.getElementById('userModal');
            const deviceModal = document.getElementById('deviceModal');
            
            if (event.target === userModal) {
                closeUserModal();
            }
            if (event.target === deviceModal) {
                closeDeviceModal();
            }
        });

        // Utility: escape HTML
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"']/g, function (m) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
            });
        }

        // Fetch users and render into any tbody by id. Supports optional limit.
        async function fetchUsers(query = '', targetId = 'users-tbody', limit = null) {
            const tbody = document.getElementById(targetId);
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="6">Loading users...</td></tr>';
            try {
                let url = '/api/users?search=' + encodeURIComponent(query || '');
                if (limit) url += '&limit=' + encodeURIComponent(limit);
                const res = await fetch(url);
                if (!res.ok) throw new Error('Network response Error');
                const users = await res.json();
                renderUsers(users, targetId);
            } catch (err) {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="6">Error loading users</td></tr>';
            }
        }

        function renderUsers(users, targetId = 'users-tbody') {
            const tbody = document.getElementById(targetId);
            if (!tbody) return;
            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6">No users found</td></tr>';
                return;
            }
            tbody.innerHTML = users.map(u => {
                const activeAttr = u.active ? 'checked' : '';
                const name = escapeHtml(u.name || '');
                const email = escapeHtml(u.email || '');
                const role = escapeHtml(u.role || 'User');
                return `
                    <tr>
                        <td>${u.id}</td>
                        <td>${name}</td>
                        <td>${email}</td>
                        <td>${role}</td>
                        <td>
                            <label class="toggle-switch">
                                <input type="checkbox" data-user-id='${String(u.id)}' ${activeAttr} onchange="toggleAccountStatus(this.dataset.userId, this.checked)">
                                <span class="slider"></span>
                            </label>
                        </td>
                        <td>
                            <button class="action-btn btn-edit" onclick='editUser(${JSON.stringify(u.id)})'>Edit</button>
                            <button class="action-btn btn-delete" onclick='deleteUser(${JSON.stringify(u.id)})'>Delete</button>
                        </td>
                    </tr>`;
            }).join('');
        }

        async function toggleAccountStatus(userId, isActive) {
            const checkbox = document.querySelector('input[data-user-id="' + userId + '"]');
            if (checkbox) checkbox.disabled = true;
            try {
                const res = await fetch('/api/users/' + encodeURIComponent(userId) + '/status', {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ active: !!isActive })
                });
                if (!res.ok) throw new Error('Update failed');
            } catch (err) {
                alert('Failed to update user status.');
                if (checkbox) checkbox.checked = !isActive;
                console.error(err);
            } finally {
                if (checkbox) checkbox.disabled = false;
            }
        }

        // Fetch system stats (tries backend, falls back to placeholders)
        async function fetchStats(){
            try {
                const res = await fetch('/api/stats');
                if (!res.ok) throw new Error('No stats');
                const s = await res.json();
                document.getElementById('stat-users').textContent = s.totalUsers ?? '—';
                document.getElementById('stat-devices').textContent = s.activeDevices ?? '—';
                document.getElementById('stat-logins').textContent = s.recentLogins ?? '—';
            } catch (err) {
                console.warn('fetchStats failed, using fallback values', err);
                // fallback: try to get a user count from the API or show placeholders
                try {
                    const res = await fetch('/api/users?limit=1');
                    if (res.ok) {
                        const users = await res.json();
                        document.getElementById('stat-users').textContent = users.length || '—';
                    } else {
                        document.getElementById('stat-users').textContent = '—';
                    }
                } catch (e) {
                    document.getElementById('stat-users').textContent = '—';
                }
                document.getElementById('stat-devices').textContent = '—';
                document.getElementById('stat-logins').textContent = '—';
            }
            // Always update status display
            updateSystemStatusDisplay();
        }

        // Export users as JSON (front-end download)
        async function exportUsers(){
            try {
                const res = await fetch('/api/users?limit=0');
                if (!res.ok) throw new Error('Export failed');
                const data = await res.json();
                const blob = new Blob([JSON.stringify(data, null, 2)], {type:'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = 'users-export.json';
                document.body.appendChild(a); a.click(); a.remove();
                URL.revokeObjectURL(url);
            } catch (err) {
                alert('Failed to export users.');
                console.error(err);
            }
        }

        function toggleMaintenanceMode(enabled){
            localStorage.setItem('maintenanceMode', enabled ? '1' : '0');
            alert('Maintenance mode ' + (enabled ? 'enabled' : 'disabled'));
        }

        // Fetch and update system status from actual XAMPP state
        async function updateSystemStatusDisplay() {
            try {
                const res = await fetch('?api=system-status');
                if (!res.ok) throw new Error('Failed to fetch system status');
                const data = await res.json();
                const isOnline = data.isOnline;
                
                const statusEl = document.getElementById('stat-status');
                const displayText = isOnline ? 'Online' : 'Offline';
                const className = isOnline ? 'status-online' : 'status-offline';

                statusEl.textContent = displayText;
                statusEl.className = className;
                statusEl.style.fontSize = '18px';
                statusEl.style.padding = '8px 12px';
                statusEl.style.borderRadius = '4px';
                statusEl.style.display = 'inline-block';
                // Removed cursor: pointer to indicate it's not clickable
            } catch (err) {
                console.error('Error checking system status:', err);
                const statusEl = document.getElementById('stat-status');
                statusEl.textContent = 'Unknown';
                statusEl.className = 'status-offline';
            }
        }

        function debounce(fn, wait){
            let t;
            return function(...args){
                clearTimeout(t);
                t = setTimeout(()=> fn.apply(this, args), wait);
            };
        }

        // Notification management functions
        function dismissNotification(button) {
            const row = button.closest('tr');
            row.style.opacity = '0.5';
            row.style.textDecoration = 'line-through';
            setTimeout(() => {
                row.remove();
                updateUnreadCount();
            }, 300);
        }

        function markAllAsRead() {
            const rows = document.querySelectorAll('#notifications-tbody tr');
            rows.forEach(row => {
                const dot = row.querySelector('span[style*="border-radius"]');
                if (dot) {
                    dot.style.backgroundColor = '#cbd5e1'; // gray when read
                }
            });
            updateUnreadCount();
            alert('All notifications marked as read');
        }

        function clearAllNotifications() {
            if (confirm('Are you sure you want to clear all notifications?')) {
                const tbody = document.getElementById('notifications-tbody');
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:#9ca3af;">No notifications</td></tr>';
                updateUnreadCount();
                alert('All notifications cleared');
            }
        }

        function updateUnreadCount() {
            const rows = document.querySelectorAll('#notifications-tbody tr');
            let unreadCount = 0;
            rows.forEach(row => {
                const dot = row.querySelector('span[style*="border-radius"]');
                if (dot && (dot.style.backgroundColor === '#4ade80' || dot.style.backgroundColor === '#fbbf24')) {
                    unreadCount++;
                }
            });
            const countEl = document.getElementById('unread-count');
            if (countEl) {
                countEl.textContent = unreadCount + ' Unread';
            }
        }

        // Initial wiring
        document.addEventListener('DOMContentLoaded', function(){
            const search = document.getElementById('user-search');
            if (search) {
                search.addEventListener('input', debounce(function(e){
                    fetchUsers(e.target.value.trim(), 'users-tbody');
                }, 350));
            }
            // initial loads
            fetchStats();
            fetchUsers('', 'users-tbody'); // full management list
            fetchUsers('', 'dashboard-users-tbody', 5); // small recent list for dashboard
            // set maintenance toggle UI from saved state
            const m = localStorage.getItem('maintenanceMode') === '1';
            const mt = document.getElementById('maintenance-toggle'); if (mt) mt.checked = m;
            // set system status display (check actual XAMPP status)
            updateSystemStatusDisplay();
            // Refresh system status every 30 seconds
            setInterval(updateSystemStatusDisplay, 30000);
            // Update unread notifications count
            updateUnreadCount();
        });
    </script>
</body>
</html>