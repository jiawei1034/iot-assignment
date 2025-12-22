<?php
// =======================
// SESSION
// =======================
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

// =======================
// DATABASE CONNECTION
// =======================
$servername  = "18.143.120.77";
$db_username = "admin";
$db_password = "P@ssword";
$db_name     = "intruderSystem";
$port        = 3306;

$conn = new mysqli($servername, $db_username, $db_password, $db_name, $port);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// =======================
// TIMEZONE & USER INFO
// =======================
date_default_timezone_set('Asia/Kuala_Lumpur');

$profile_name = $_SESSION['username'];
$now = new DateTime('now');
$login_timestamp = $now->format('Y-m-d H:i:s');
$logo_text = 'IntruderSys';

// =======================
// FETCH DATA
// =======================
$devices = $conn->query("SELECT device_id,event_id,name,status FROM device_list")
                ->fetch_all(MYSQLI_ASSOC);

$alarms = $conn->query("SELECT alarm_id,device_id,is_triggered,date_time 
                        FROM alarm ORDER BY date_time DESC")
               ->fetch_all(MYSQLI_ASSOC);

$motions = $conn->query("SELECT motion_id,device_id,is_detected,date_time 
                         FROM motion_sensor ORDER BY date_time DESC")
                ->fetch_all(MYSQLI_ASSOC);

$shocks = $conn->query("SELECT shock_id,device_id,is_detected,date_time 
                        FROM shock_sensor ORDER BY date_time DESC")
               ->fetch_all(MYSQLI_ASSOC);

$notifications = $conn->query("SELECT notification_id,user_id,username,message,created_at 
                               FROM notification 
                               ORDER BY created_at DESC LIMIT 20")
                      ->fetch_all(MYSQLI_ASSOC);

// =======================
// DEVICE MAP
// =======================
$deviceMap = [];
foreach ($devices as $d) {
    $deviceMap[$d['device_id']] = $d['name'];
}

// =======================
// FILTER
// =======================
$filter = $_GET['filter'] ?? 'all';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<style>
body{ background:#F8FBF8; }
.sidebar{ width:180px;height:100vh;position:fixed;border-right:1px solid #cacaca;background:#fff;padding-top:20px }
.main{ margin-left:180px;margin-top:60px;padding:25px;font-family:Arial }
.topbar{ position:fixed;top:0;left:180px;right:0;height:64px;background:#fff;
         display:flex;align-items:center;justify-content:flex-end;
         box-shadow:0 1px 4px rgba(0,0,0,.06);border-bottom:1px solid #c6c6c6;z-index:1003 }
.sidebar-title{ font-size:18px;font-weight:600;margin-left:50px;margin-bottom:25px }
.nav-section{ list-style:none;padding:0 }
.nav-section li{ padding:8px;margin:10px;border-radius:6px }
.nav-section li:hover,.nav-section li.active{ background:#EAE5E3;font-weight:600 }
.icon-btn{ background:transparent;border:0;padding:8px;cursor:pointer }
.filters button{ border:0;padding:6px 14px;border-radius:20px;margin-right:6px }
.filters .active{ background:#ee7241ff;color:white }
table{ width:100%;background:white;border-radius:12px;overflow:hidden }
th,td{ padding:14px }
th{ background:#f1f5f9 }
tr:hover{ background:#f8fafc }
.badge{ padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600 }
.ok{ background:#dcfce7;color:#166534 }
.alert{ background:#fee2e2;color:#991b1b }
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="sidebar-title">System</div>
    <ul class="nav-section">
        <li><a href="AdminHome.php" style="text-decoration:none;color:black">Overview</a></li>
        <li class="active">Logs / Events</li>
    </ul>
    <div style="position:absolute;bottom:20px;width:100%;text-align:center">
        <a href="index.php" class="btn btn-danger w-75" style="background:#ee7241ff;border:none">Logout</a>
    </div>
</div>

<!-- TOPBAR -->
<div class="topbar">
    <button class="icon-btn" id="refreshBtn"><i class="fa fa-rotate-right"></i></button>
    <div style="text-align:right;margin-right:20px">
        <div style="font-size:12px;color:#6b7280">Last login</div>
        <div style="font-weight:600"><?=htmlspecialchars($login_timestamp)?></div>
    </div>
    <div style="display:flex;align-items:center;gap:8px;margin-right:20px">
        <img src="icons/account.svg" width="32">
        <div><?=htmlspecialchars($profile_name)?></div>
    </div>
</div>

<!-- MAIN -->
<div class="main">
    <div class="filters mb-3">
        <?php foreach(['all'=>'All','alarm'=>'Alarm','motion'=>'Motion','shock'=>'Shock','notification'=>'Notification'] as $k=>$v): ?>
            <a href="?filter=<?=$k?>">
                <button class="<?=$filter==$k?'active':''?>"><?=$v?></button>
            </a>
        <?php endforeach; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Datetime</th>
                <th>Type</th>
                <th>Device / User</th>
                <th>Description</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>

<?php if($filter==='all'||$filter==='alarm') foreach($alarms as $a): ?>
<tr>
<td><?=$a['datetime']?></td>
<td>Alarm</td>
<td><?=$deviceMap[$a['device_id']] ?? 'Unknown'?></td>
<td><?=$a['is_triggered']?'Alarm Activated':'Alarm Cleared'?></td>
<td><span class="badge <?=$a['is_triggered']?'alert':'ok'?>"><?=$a['is_triggered']?'Triggered':'Normal'?></span></td>
</tr>
<?php endforeach; ?>

<?php if($filter==='all'||$filter==='motion') foreach($motions as $m): ?>
<tr>
<td><?=$m['datetime']?></td>
<td>Motion</td>
<td><?=$deviceMap[$m['device_id']] ?? 'Unknown'?></td>
<td><?=$m['is_detected']?'Motion Detected':'No Motion'?></td>
<td><span class="badge <?=$m['is_detected']?'alert':'ok'?>"><?=$m['is_detected']?'Detected':'Clear'?></span></td>
</tr>
<?php endforeach; ?>

<?php if($filter==='all'||$filter==='shock') foreach($shocks as $s): ?>
<tr>
<td><?=$s['datetime']?></td>
<td>Shock</td>
<td><?=$deviceMap[$s['device_id']] ?? 'Unknown'?></td>
<td><?=$s['is_detected']?'Shock Detected':'No Shock'?></td>
<td><span class="badge <?=$s['is_detected']?'alert':'ok'?>"><?=$s['is_detected']?'Detected':'Clear'?></span></td>
</tr>
<?php endforeach; ?>

<?php if($filter==='all'||$filter==='notification') foreach($notifications as $n): ?>
<tr>
<td><?=$n['created_at']?></td>
<td>Notification</td>
<td><?=$n['username']?></td>
<td><?=$n['message']?></td>
<td><span class="badge ok">Info</span></td>
</tr>
<?php endforeach; ?>

        </tbody>
    </table>
</div>

<script>
document.getElementById('refreshBtn').addEventListener('click', () => location.reload());
</script>

</body>
</html>
