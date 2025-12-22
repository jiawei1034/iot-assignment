<?php

// -------------------------
// DATABASE VERSION (ENABLE LATER)
// -------------------------
// session_start();
// require_once "db.php";
// if (!isset($_SESSION['username'])) {
//     header("Location: index.php");
//     exit;
// }
// date_default_timezone_set('Asia/Kuala_Lumpur');
// $profile_name = $_SESSION['username'];
// $now = new DateTime('now');
// $login_timestamp = $now->format('Y-m-d H:i:s');
// $logo_text = 'IntruderSys';

// $devices = $conn->query("SELECT device_id,event_id,name,status FROM device_list")->fetch_all(MYSQLI_ASSOC);
// $alarms = $conn->query("SELECT alarm_id,device_id,is_triggered,datetime FROM alarm ORDER BY datetime DESC")->fetch_all(MYSQLI_ASSOC);
// $motions = $conn->query("SELECT motion_id,device_id,is_detected,datetime FROM motion_sensor ORDER BY datetime DESC")->fetch_all(MYSQLI_ASSOC);
// $shocks = $conn->query("SELECT shock_id,device_id,is_detected,datetime FROM shock_sensor ORDER BY datetime DESC")->fetch_all(MYSQLI_ASSOC);
// $notifications = $conn->query("SELECT notification_id,user_id,username,message,created_at FROM notification ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

// -------------------------
// HARDCODED SAMPLE DATA (UI PREVIEW)
// -------------------------
// $now = new DateTime('now');
// $login_timestamp = $now->format('Y-m-d H:i:s');
// $profile_name = 'Admin User';
// $logo_text = 'IntruderSys';

// $devices = [
//   ['device_id'=>1,'event_id'=>1,'name'=>'LED Zone A','status'=>1],
//   ['device_id'=>2,'event_id'=>1,'name'=>'Buzzer Zone A','status'=>1],
//   ['device_id'=>3,'event_id'=>2,'name'=>'PIR Motion A','status'=>1],
//   ['device_id'=>4,'event_id'=>3,'name'=>'Shock Sensor A','status'=>1],
// ];

// $alarms = [
//   ['alarm_id'=>1,'device_id'=>1,'is_triggered'=>1,'datetime'=>'2025-11-28 09:12:00'],
//   ['alarm_id'=>2,'device_id'=>2,'is_triggered'=>1,'datetime'=>'2025-11-28 09:12:10'],
//   ['alarm_id'=>3,'device_id'=>1,'is_triggered'=>0,'datetime'=>'2025-11-29 10:00:00'],
// ];

// $motions = [
//   ['motion_id'=>1,'device_id'=>3,'is_detected'=>1,'datetime'=>'2025-11-30 16:12:45'],
// ];

// $shocks = [
//   ['shock_id'=>1,'device_id'=>4,'is_detected'=>0,'datetime'=>'2025-11-30 09:15:00'],
// ];

// $notifications = [
//   ['notification_id'=>1,'user_id'=>1,'username'=>'system','message'=>'Alarm triggered at Zone A','created_at'=>'2025-11-28 09:12:12'],
//   ['notification_id'=>2,'user_id'=>2,'username'=>'guard1','message'=>'Motion detected near gate','created_at'=>'2025-11-30 16:13:00'],
// ];

// $deviceMap = [];
// foreach ($devices as $d) $deviceMap[$d['device_id']] = $d['name'];

// $filter = $_GET['filter'] ?? 'all';
// ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>System Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body{ background:#F8FBF8; }
    .sidebar{ width:180px; height:100vh; position:fixed; border-right: 1px solid #cacacaff;left:0; top:0; font-family: Arial, sans-serif;background:#FFFFFF; color:#333; padding-top:20px }
    .main {
    margin-left: 180px;   /* sidebar width */
    margin-top: 60px;
    padding: 20px;
    padding-top: 25px;    /* height of topbar + spacing */
    font-family: Arial, sans-serif;
}
.topbar {
    position: fixed;
    top: 0;
    left: 180px; /* sidebar width */
    right: 0; /* notification panel width */
    height: 64px;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 0px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border-bottom: 1px solid #c6c6c6;
    z-index: 1003; /* above everything */
}
.sidebar-title {
    display: flex;
    align-items: center;
    gap: 20px;
    font-weight: 600;
    font-size: 18px;
    margin-bottom: 25px;
    margin-left: 50px;
}

.nav-section {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
}

.nav-section li {
    display: flex;
    align-items: center;
    padding: 8px 8px;
    margin: 10px;
    cursor: pointer;
    border-radius: 6px;
    font-size: 15px;
    gap: 10px;
    color: #000000ff;
}

.nav-section li:hover {
    background: #EAE5E3;
    font-weight: 600;
}

.nav-section li.active {
    background: #EAE5E3;
    color: #000000ff;
    font-weight: 600;
}

.icon {
    width: 18px;
    height: 18px;
}
.icon-btn{background:transparent;border:0;margin-left:980px;padding:8px;border-radius:8px;cursor:pointer}  
.filters button{border:0;padding:6px 14px;border-radius:20px;margin-right:6px;cursor:pointer}
.filters .active{background:#ee7241ff;;color:white}
table{width:100%;background:white;border-collapse:collapse;border-radius:12px;overflow:hidden}
th,td{padding:14px;text-align:left}
th{background:#f1f5f9}
tr:hover{background:#f8fafc}
.badge{padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600}
.ok{background:#dcfce7;color:#166534}
.alert{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<div class="wrapper">

<div class="sidebar">
    <div class="sidebar-title">
        <span>System</span>
    </div>

    <ul class="nav-section">
        <li><a href="AdminHome.php" style="text-decoration: none; color: black;">Overview</a></li>
        <li class="active">Logs/Events</li>
    </ul>
    <!-- Logout button -->
    <div style="position:absolute; bottom:20px; width:100%; text-align:center;">
      <a href="index.php" class="btn btn-danger w-75" style="background: #ee7241ff; border: none; color:white">Logout</a>
    </div>
</div>

<!-- TOPBAR -->
<div class="topbar">
    
    <div style="display:flex;align-items:center;gap:12px">
      <button class="icon-btn" id="refreshBtn" title="Refresh"><i class="fa fa-arrow-rotate-right"></i></button>
      <div style="text-align:right">
        <div style="font-size:12px;color:#6b7280">Last login</div>
        <div style="font-weight:600"><?=htmlspecialchars($login_timestamp)?></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <div style="width:40px;height:40px;border-radius:50%;background:#efefef;display:flex;align-items:center;justify-content:center"><img src="icons/account.svg" class="icon"></div>
        <div><?=htmlspecialchars($profile_name)?></div>
      </div>
    </div>
  </div>
<div class="main">
  <div class="filters" style="margin-bottom:15px">
    <?php foreach(['all'=>'All','alarm'=>'Alarm','motion'=>'Motion','shock'=>'Shock','notification'=>'Notification'] as $k=>$v): ?>
      <a href="?filter=<?=$k?>"><button class="<?=$filter==$k?'active':''?>"><?=$v?></button></a>
    <?php endforeach; ?>
  </div>

  <table>
    <thead>
      <tr><th>Datetime</th><th>Type</th><th>Device / User</th><th>Description</th><th>Status</th></tr>
    </thead>
    <tbody>

<?php if($filter==='all'||$filter==='alarm') foreach($alarms as $a): ?>
<tr>
<td><?=$a['datetime']?></td>
<td>Alarm</td>
<td><?=$deviceMap[$a['device_id']]?></td>
<td><?=$a['is_triggered']?'Alarm Activated':'Alarm Cleared'?></td>
<td><span class="badge <?=$a['is_triggered']?'alert':'ok'?>"><?=$a['is_triggered']?'Triggered':'Normal'?></span></td>
</tr>
<?php endforeach; ?>

<?php if($filter==='all'||$filter==='motion') foreach($motions as $m): ?>
<tr>
<td><?=$m['datetime']?></td>
<td>Motion</td>
<td><?=$deviceMap[$m['device_id']]?></td>
<td><?=$m['is_detected']?'Motion Detected':'No Motion'?></td>
<td><span class="badge <?=$m['is_detected']?'alert':'ok'?>"><?=$m['is_detected']?'Detected':'Clear'?></span></td>
</tr>
<?php endforeach; ?>

<?php if($filter==='all'||$filter==='shock') foreach($shocks as $s): ?>
<tr>
<td><?=$s['datetime']?></td>
<td>Shock</td>
<td><?=$deviceMap[$s['device_id']]?></td>
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
</div>
</div>
<script>
  document.getElementById('refreshBtn').addEventListener('click', ()=> location.reload());
</script>
</body>
</html>
