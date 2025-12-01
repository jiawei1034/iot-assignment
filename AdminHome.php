<?php
// admin_home.php
// Frontend mockup for Admin Home Page using hardcoded data based on the user's DB schema.
// - Uses Chart.js for charts
// - Date range selector: Day / Week / Month
// - Chart switcher between Alarm (LED / Buzzer by device), Motion, Shock
// - Right column: Notifications
// - Side nav: Overview, Logs

// -------------------------
// Hardcoded sample data (replace with DB queries later)
// -------------------------
$now = new DateTime('now');
$login_timestamp = $now->format('Y-m-d H:i:s');
$profile_name = 'Admin User';
$logo_text = 'IntruderSys';

// Devices sample (device_id, event_id, name, status)
$devices = [
    ['device_id'=>1, 'event_id'=>1, 'name'=>'LED Zone A', 'status'=>1],
    ['device_id'=>2, 'event_id'=>1, 'name'=>'Buzzer Zone A', 'status'=>1],
    ['device_id'=>3, 'event_id'=>2, 'name'=>'PIR Motion A', 'status'=>1],
    ['device_id'=>4, 'event_id'=>3, 'name'=>'Shock Sensor A', 'status'=>1],
];

// Alarm table sample (alarm_id, device_id, is_triggered, datetime)
$alarms = [
    ['alarm_id'=>1, 'device_id'=>1, 'is_triggered'=>1, 'datetime'=>'2025-11-28 09:12:00'],
    ['alarm_id'=>2, 'device_id'=>2, 'is_triggered'=>1, 'datetime'=>'2025-11-28 09:12:10'],
    ['alarm_id'=>3, 'device_id'=>1, 'is_triggered'=>0, 'datetime'=>'2025-11-29 10:00:00'],
    ['alarm_id'=>4, 'device_id'=>2, 'is_triggered'=>0, 'datetime'=>'2025-11-29 10:00:10'],
    ['alarm_id'=>5, 'device_id'=>1, 'is_triggered'=>1, 'datetime'=>'2025-11-30 11:22:00'],
];

// Motion sensor sample (motion_id, device_id, is_detected, datetime)
$motions = [
    ['motion_id'=>1, 'device_id'=>3, 'is_detected'=>0, 'datetime'=>'2025-11-28 08:00:00'],
    ['motion_id'=>2, 'device_id'=>3, 'is_detected'=>1, 'datetime'=>'2025-11-28 08:35:12'],
    ['motion_id'=>3, 'device_id'=>3, 'is_detected'=>0, 'datetime'=>'2025-11-29 14:00:00'],
    ['motion_id'=>4, 'device_id'=>3, 'is_detected'=>1, 'datetime'=>'2025-11-30 16:12:45'],
];

// Shock sensor sample (shock_id, device_id, is_detected, datetime)
$shocks = [
    ['shock_id'=>1, 'device_id'=>4, 'is_detected'=>0, 'datetime'=>'2025-11-28 07:23:00'],
    ['shock_id'=>2, 'device_id'=>4, 'is_detected'=>1, 'datetime'=>'2025-11-29 20:05:10'],
    ['shock_id'=>3, 'device_id'=>4, 'is_detected'=>0, 'datetime'=>'2025-11-30 09:15:00'],
];

// Notifications sample (notification_id, user_id, username, message, created_at)
$notifications = [
    ['notification_id'=>1, 'user_id'=>1, 'username'=>'system', 'message'=>'Alarm triggered at Zone A', 'created_at'=>'2025-11-28 09:12:12'],
    ['notification_id'=>2, 'user_id'=>2, 'username'=>'guard1', 'message'=>'Motion detected near gate', 'created_at'=>'2025-11-30 16:13:00'],
    ['notification_id'=>3, 'user_id'=>1, 'username'=>'system', 'message'=>'Device LED Zone A offline', 'created_at'=>'2025-11-29 10:05:00'],
];

// Summary boxes (counts). For demo we compute simple counts.
$summary = [
    'total_devices' => count($devices),
    'total_alarms' => count(array_filter($alarms, fn($a)=> $a['is_triggered']==1)),
    'motions_detected' => count(array_filter($motions, fn($m)=> $m['is_detected']==1)),
    'shocks_detected' => count(array_filter($shocks, fn($s)=> $s['is_detected']==1)),
];
$online = 0;
$offline = 0;
$faulty = 0;

foreach ($devices as $d) {
    if ($d['status'] == 1) $online++;
    if ($d['status'] == 0) $offline++;
    if ($d['status'] == 2) $faulty++; // if you use status=2 as faulty
}

$dailyEvents = [];

$mergeArrays = array_merge($alarms, $motions, $shocks);

foreach ($mergeArrays as $row) {
    $day = date("Y-m-d", strtotime($row['datetime']));
    if (!isset($dailyEvents[$day])) $dailyEvents[$day] = 0;

    // count only triggered/detected ones
    if (isset($row['is_triggered']) && $row['is_triggered'] == 1) $dailyEvents[$day]++;
    if (isset($row['is_detected']) && $row['is_detected'] == 1) $dailyEvents[$day]++;
}

ksort($dailyEvents);

// Make labels & values for charts
$eventLabels = array_keys($dailyEvents);
$eventValues = array_values($dailyEvents);


// Utility: generate time series labels for selected range
function generate_time_labels(DateTime $from, DateTime $to, $rangeType){
    $labels = [];
    $current = clone $from;
    switch($rangeType){
        case 'day':
            // hourly labels
            while($current <= $to){
                $labels[] = $current->format('H:00');
                $current->modify('+1 hour');
            }
            break;
        case 'week':
            // daily labels
            while($current <= $to){
                $labels[] = $current->format('Y-m-d');
                $current->modify('+1 day');
            }
            break;
        case 'month':
        default:
            // daily labels across month
            while($current <= $to){
                $labels[] = $current->format('Y-m-d');
                $current->modify('+1 day');
            }
            break;
    }
    return $labels;
}

// Prepare default range: last 7 days
$rangeType = $_GET['range'] ?? 'week'; // day | week | month
$end = new DateTime();
$start = (clone $end)->modify('-6 days');
if($rangeType === 'day'){
    $start = (clone $end)->modify('-23 hours');
}
if($rangeType === 'month'){
    $start = (clone $end)->modify('-29 days');
}
$labels = generate_time_labels($start, $end, $rangeType);

// Helper to map events to labels
function map_events_to_series($labels, $events, $fieldName, $datetimeField='datetime', $valueWhenTrue=1){
    // Initialize series with zeros
    $series = array_fill(0, count($labels), 0);
    // Create label -> index map
    $labelMap = [];
    foreach($labels as $i=>$label){ $labelMap[$label] = $i; }

    foreach($events as $e){
        $dt = new DateTime($e[$datetimeField]);
        // Format to match label granularity
        global $rangeType;
        if($rangeType === 'day'){
            $key = $dt->format('H:00');
        } else {
            $key = $dt->format('Y-m-d');
        }
        if(isset($labelMap[$key])){
            $series[$labelMap[$key]] = $e[$fieldName] ? $valueWhenTrue : 0;
        }
    }
    return $series;
}
$statusCounts = ["online" => 0, "offline" => 0, "faulty" => 0];

foreach ($devices as $d) {
    $statusCounts[$d["status"]]++;
}

// ---------------------------------------------
// DAILY EVENT COUNTS (ALARMS + MOTIONS + SHOCKS)
// ----------------------------------------------
$dailyEvents = [];

function addEventDay(&$arr, $date)
{
    $day = date("D", strtotime($date)); // Monday = Mon
    if (!isset($arr[$day])) $arr[$day] = 0;
    $arr[$day]++;
}

// Alarm events
foreach ($alarms as $a) {
    if ($a["is_triggered"] == 1) {
        addEventDay($dailyEvents, $a["datetime"]);
    }
}

// Motion events
foreach ($motions as $m) {
    if ($m["is_detected"] == 1) {
        addEventDay($dailyEvents, $m["datetime"]);
    }
}

// Shock events
foreach ($shocks as $s) {
    if ($s["is_detected"] == 1) {
        addEventDay($dailyEvents, $s["datetime"]);
    }
}
$days = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];

foreach ($days as $d) {
    if (!isset($dailyEvents[$d])) $dailyEvents[$d] = 0;
}

// Sorted by weekday
$sortedDailyEvents = [];
foreach ($days as $d) {
    $sortedDailyEvents[] = $dailyEvents[$d];
}
// Prepare datasets as JSON for Chart.js
$alarm_led_events = array_values(array_filter($alarms, fn($a)=> $a['device_id']==1));
$alarm_buzzer_events = array_values(array_filter($alarms, fn($a)=> $a['device_id']==2));
$alarms_led_series = map_events_to_series($labels, $alarm_led_events, 'is_triggered');
$alarms_buzzer_series = map_events_to_series($labels, $alarm_buzzer_events, 'is_triggered');

$motion_series = map_events_to_series($labels, $motions, 'is_detected');
$shock_series = map_events_to_series($labels, $shocks, 'is_detected');

// JSON encode for embedding
$labels_json = json_encode($labels);
$alarms_led_json = json_encode($alarms_led_series);
$alarms_buzzer_json = json_encode($alarms_buzzer_series);
$motion_json = json_encode($motion_series);
$shock_json = json_encode($shock_series);
$notifications_json = json_encode($notifications);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Home - Intruder System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>
    body{ background:#F8FBF8; }
    .sidebar{ width:180px; height:100vh; position:fixed; border-right: 1px solid #cacacaff;left:0; top:0; font-family: Arial, sans-serif;background:#FFFFFF; color:#333; padding-top:20px }
    .main {
    margin-left: 180px;   /* sidebar width */
    margin-right: 280px;  /* notification panel width */
    padding: 20px;
    padding-top: 25px;    /* height of topbar + spacing */
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
    padding: 0 20px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
    border-bottom: 1px solid #c6c6c6;
    z-index: 1003; /* above everything */
}

.chart-row {
    display: flex;
    width: 110%;
    gap: 20px;
    margin-top: 20px;
}

.chart-left {
    width: 200%;
}

.chart-right {
    width: 5%;
    display: flex;
    flex-direction: column;
    
}

.chart-card {
    background: #ffffff;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

    .notify-list{ max-height:380px; overflow:auto }
    .notification-panel {
    width: 280px;
    position: fixed;
    top: 64px;
    right: 0;
    bottom: 0;
    background: #fff;
    border-left: 1px solid #cdcdcdff;
    padding: 18px;
    overflow-y: auto;
}

.notif-header {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
}

.notif-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.notif-item {
    display: flex;
    align-items: center;
    background: #ffffff;
    padding: 10px 6px;
    border-radius: 10px;
    cursor: pointer;
    transition: 0.2s;
}

.notif-item:hover {
    background: #f5f5f5;
}

.notif-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: #eef2ff;
    color: #374151;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    margin-right: 12px;
    font-size: 15px;
}

.notif-text {
    display: flex;
    flex-direction: column;
}

.notif-title {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 2px;
}

.notif-time {
    font-size: 12px;
    color: #6b7280;
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
.search-wrap{display:flex;align-items:center;background:#F8FBF8;padding:8px 12px;border-radius:999px;box-shadow:inset 0 1px 0 rgba(255,255,255,0.6)}
.search-wrap input{border:0;background:transparent;outline:none;width:320px}  
.icon-btn{background:transparent;border:0;padding:8px;border-radius:8px;cursor:pointer}  
.summary-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.summary{background:#ffffff;padding:18px;border-radius:12px;box-shadow:0 6px 18px rgba(11,22,50,0.04)}
.summary small{color:#000000ff}
  </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-title">
        <span>System</span>
    </div>

    <ul class="nav-section">
        <li class="active"><img src="icons/overview.svg" class="icon"> Overview</li>
        <li><img src="icons/event.svg" class="icon"> Logs/Events</li>
    </ul>

</div>
<!-- TOPBAR -->
<div class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <div class="search-wrap">
          <i class="fa fa-search" style="margin-right:8px"></i>
          <input placeholder="Search" id="searchInput" />
        </div>
    </div>
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

  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h3 style="margin:0">Overview</h3>
        <div style="color:var(--muted);font-size:14px">Today</div>
      </div>

  <div class="row g-3 mb-3">
    <div class="summary-grid">
        <div class="summary">
          <small>Total Devices</small>
          <div style="font-size:22px;font-weight:700"><?= $summary['total_devices'] ?></div>
        </div>
        <div class="summary">
          <small>Alarms Triggered</small>
          <div style="font-size:22px;font-weight:700"><?= $summary['total_alarms'] ?></div>
        </div>
        <div class="summary">
          <small>Motion Detected</small>
          <div style="font-size:22px;font-weight:700"><?= $summary['motions_detected'] ?></div>
        </div>
        <div class="summary">
          <small>Shock Detected</small>
          <div style="font-size:22px;font-weight:700"><?= $summary['shocks_detected'] ?></div>
        </div>
      </div>
  </div>

<div class="dashboard-grid">
  <div class="row">
    <div class="col-lg-8">
      <div class="chart-row">
        <div class="chart-left">
      <div class="card mb-3">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title">Sensor Charts</h5>
            <div>
              <div class="btn-group btn-group-sm" role="group">
                <a class="btn btn-outline-secondary" href="?range=day">Day</a>
                <a class="btn btn-outline-secondary" href="?range=week">Week</a>
                <a class="btn btn-outline-secondary" href="?range=month">Month</a>
              </div>
              <div class="btn-group btn-group-sm ms-2" role="group" id="chartSwitcher">
                <button class="btn btn-primary" data-chart="alarms">Alarms</button>
                <button class="btn btn-outline-primary" data-chart="motion">Motion</button>
                <button class="btn btn-outline-primary" data-chart="shock">Shock</button>
              </div>
            </div>
          </div>

          <canvas id="sensorChart" height="140"></canvas>

        </div>
      </div>
      <!-- Table area: recent events -->
      <div class="card">
        <div class="card-body">
          <h5>Recent Events</h5>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead>
                <tr><th>Type</th><th>Device</th><th>Datetime</th><th>State</th></tr>
              </thead>
              <tbody>
                <?php foreach($alarms as $a):
                  $dev = array_values(array_filter($devices, fn($d)=> $d['device_id']==$a['device_id']))[0];
                ?>
                  <tr>
                    <td>Alarm</td>
                    <td><?=htmlspecialchars($dev['name'])?></td>
                    <td><?=htmlspecialchars($a['datetime'])?></td>
                    <td><?= $a['is_triggered'] ? 'TRIGGERED' : 'OK' ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach($motions as $m): ?>
                  <?php $dev = array_values(array_filter($devices, fn($d)=> $d['device_id']==$m['device_id']))[0]; ?>
                  <tr>
                    <td>Motion</td>
                    <td><?=htmlspecialchars($dev['name'])?></td>
                    <td><?=htmlspecialchars($m['datetime'])?></td>
                    <td><?= $m['is_detected'] ? 'DETECTED' : 'CLEAR' ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php foreach($shocks as $s): ?>
                  <?php $dev = array_values(array_filter($devices, fn($d)=> $d['device_id']==$s['device_id']))[0]; ?>
                  <tr>
                    <td>Shock</td>
                    <td><?=htmlspecialchars($dev['name'])?></td>
                    <td><?=htmlspecialchars($s['datetime'])?></td>
                    <td><?= $s['is_detected'] ? 'DETECTED' : 'CLEAR' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      </div>

      
      <div class="chart-right">
<div style="width: 300px;">
    
    <!-- DEVICE STATUS DONUT CARD -->
     
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
    <div style="
        background: #ffffff; 
        padding: 0px; 
        border-radius: 16px; 
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        margin-bottom: 20px;">
        
        <h3 style="font-size: 18px; margin-bottom: 10px;">Device Status</h3>

        <canvas id="deviceStatusChart" style="max-height: 220px;"></canvas>

        <div style="margin-top: 20px; display: flex; justify-content: space-around;">
            <div><strong>Online</strong><br><?= $online ?></div>
            <div><strong>Offline</strong><br><?= $offline ?></div>
            <div><strong>Faulty</strong><br><?= $faulty ?></div>
        </div>
    </div>
</div></div>
    <!-- DAILY EVENT SUMMARY BAR CARD -->
     <div class="card">
        <div class="card-body"></div>
    <div style="
        background: #ffffff; 
        padding-left: 20px;
        padding-right: 20px;
        border-radius: 16px; 
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        margin-bottom: 20px;">

        <h3 style="font-size: 18px; margin-bottom: 16px;">Daily Event Summary</h3>

        <canvas id="dailyEventBar" style="max-height: 260px;"></canvas>
    </div>
</div></div>
</div>
</div>
</div>

   <!-- Right Notification Panel -->
<div class="notification-panel">
    <div class="notif-header">Notifications</div>

    <div class="notif-list">
        <?php foreach($notifications as $n): 
            // Extract initials
            $initials = strtoupper(substr($n['username'], 0, 2));
        ?>
        <div class="notif-item">
            <div class="notif-icon"><?= $initials ?></div>
            <div class="notif-text">
                <div class="notif-title"><?= htmlspecialchars($n['message']) ?></div>
                <div class="notif-time"><?= htmlspecialchars($n['created_at']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<div style="width: 350px; margin-left: 20px;">

</div>
    </div>
  </div>

</div>
</div>
<script>

// Simple interactions
document.getElementById('refreshBtn').addEventListener('click', ()=> location.reload());

const labels = <?= $labels_json ?>;
const alarmsLed_raw = <?= $alarms_led_json ?>;
const alarmsBuzzer_raw = <?= $alarms_buzzer_json ?>;
const motionSeries_raw = <?= $motion_json ?>;
const shockSeries_raw = <?= $shock_json ?>;

// Transform 0 → 2 and 1 → 4
const alarmsLed = alarmsLed_raw.map(v => v == 1 ? 2.7 : 0.7);
const alarmsBuzzer = alarmsBuzzer_raw.map(v => v == 1 ? 3.2 : 1.2); 
const motionSeries = motionSeries_raw.map(v => v == 1 ? 3 : 1);
const shockSeries = shockSeries_raw.map(v => v == 1 ? 3 : 1);

// Chart setup
let currentChartType = 'alarms';
const ctx = document.getElementById('sensorChart').getContext('2d');
let sensorChart = null;

// Alarm chart
function createAlarmsChart() {

  // === GRADIENT FOR LED LINE ===
  const ledGradient = ctx.createLinearGradient(0, 0, 0, 300);
  ledGradient.addColorStop(0, '#FF5FA2'); // top fade
  ledGradient.addColorStop(1, '#ff5fa21d');    // bottom (touch x-axis)

  // === GRADIENT FOR BUZZER LINE ===
  const buzzerGradient = ctx.createLinearGradient(0, 0, 0, 300);
  buzzerGradient.addColorStop(0, '#FFB27F');
  buzzerGradient.addColorStop(1, '#ffb27f24');

  return new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [
        { 
          label: 'LED (device 1)', 
          data: alarmsLed, 
          tension: 0.4,
          fill: true,
          backgroundColor: ledGradient,
          borderColor: '#FF5FA2',
          borderWidth: 3,
          pointRadius: 6,
          pointHoverRadius: 8,
          pointBackgroundColor: '#ff5fa282',
          pointBorderColor: '#FF5FA2',
          pointBorderWidth: 3,
          pointStyle: 'circle'
        },
        { 
          label: 'Buzzer (device 2)', 
          data: alarmsBuzzer, 
          tension: 0.4,
          fill: true,
          backgroundColor: buzzerGradient,
          borderColor: '#FFB27F',
          borderWidth: 3,
          pointRadius: 7,
          pointHoverRadius: 9,
          pointBackgroundColor: '#ffb27f8d',
          pointBorderColor: '#FFB27F',
          pointBorderWidth: 3,
          pointStyle: 'circle'
        }
      ]
    },
    options: {
      plugins: {
        legend: {
          display: true
        }
      },
      scales: {
        y: {
          min: 0,
          max: 4,
          ticks: {
            stepSize: 1,
            callback: function(value) {
              if (value === 1) return 'Not Activated';
              if (value === 3) return 'Activated';
              return '';
            }
          }
        }
      }
    }
  });
}

// Motion chart
function createMotionChart() {

  // === GRADIENT FOR MOTION LINE ===
  const motionGradient = ctx.createLinearGradient(0, 0, 0, 300);
  motionGradient.addColorStop(0, '#4C9BFF');     // top fade
  motionGradient.addColorStop(1, '#4c9bff1f');   // bottom fade to x-axis

  return new Chart(ctx, {
    type: 'line',
    data: { 
      labels: labels, 
      datasets: [
        { 
          label: 'Motion detected', 
          data: motionSeries, 
          tension: 0.4,
          fill: true,
          backgroundColor: motionGradient,
          borderColor: '#4C9BFF',
          borderWidth: 3,
          pointRadius: 6,
          pointHoverRadius: 8,
          pointBackgroundColor: '#ffffff',
          pointBorderColor: '#4C9BFF',
          pointBorderWidth: 3,
          pointStyle: 'circle'
        }
      ] 
    },
    options: {
      plugins: {
        legend: { display: true }
      },
      scales: {
        y: {
          min: 0,
          max: 4,
          ticks: {
            stepSize: 1,
            callback: function(value) {
              if (value === 1) return 'Not Detected';
              if (value === 3) return 'Detected';
              return '';
            }
          }
        }
      }
    }
  });
}

// Shock chart
function createShockChart() {

  // === GRADIENT FOR SHOCK LINE ===
  const shockGradient = ctx.createLinearGradient(0, 0, 0, 300);
  shockGradient.addColorStop(0, '#FF6B6B');      // top fade
  shockGradient.addColorStop(1, '#ff6b6b20');    // fade to x-axis

  return new Chart(ctx, {
    type: 'line',
    data: { 
      labels: labels, 
      datasets: [
        { 
          label: 'Shock detected', 
          data: shockSeries, 
          tension: 0.4,
          fill: true,
          backgroundColor: shockGradient,
          borderColor: '#FF6B6B',
          borderWidth: 3,
          pointRadius: 6,
          pointHoverRadius: 8,
          pointBackgroundColor: '#ffffff',
          pointBorderColor: '#FF6B6B',
          pointBorderWidth: 3,
          pointStyle: 'circle'
        }
      ] 
    },
    options: {
      plugins: {
        legend: { display: true }
      },
      scales: {
        y: {
          min: 0,
          max: 4,
          ticks: {
            stepSize: 1,
            callback: function(value) {
              if (value === 1) return 'Not Detected';
              if (value === 3) return 'Detected';
              return '';
            }
          }
        }
      }
    }
  });
}

function renderChart(type){
  if(sensorChart) sensorChart.destroy();
  if(type === 'alarms') sensorChart = createAlarmsChart();
  else if(type === 'motion') sensorChart = createMotionChart();
  else if(type === 'shock') sensorChart = createShockChart();
}

// init
renderChart(currentChartType);

// chart switcher buttons
document.querySelectorAll('#chartSwitcher button').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    document.querySelectorAll('#chartSwitcher button').forEach(b=> b.classList.remove('btn-primary'));
    document.querySelectorAll('#chartSwitcher button').forEach(b=> b.classList.add('btn-outline-primary'));
    btn.classList.remove('btn-outline-primary');
    btn.classList.add('btn-primary');
    const c = btn.dataset.chart;
    renderChart(c);
  });
});

new Chart(document.getElementById("deviceStatusChart"), {
    type: 'doughnut',
    data: {
        labels: ["Online", "Offline", "Faulty"],
        datasets: [{
            data: [<?= $online ?>, <?= $offline ?>, <?= $faulty ?>],
            backgroundColor: ["#55ff77ff", "#d7ff55ff", "#fa613eff"],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        cutout: "65%",
        plugins: {
            legend: {
                display: true,
                position: "right",
                labels: {
                    boxWidth: 12,
                    padding: 15
                }
            }
        }
    }
});


new Chart(document.getElementById("dailyEventBar"), {
    type: "bar",
    data: {
        labels: <?= json_encode($eventLabels) ?>,
        datasets: [{
            label: "Events",
            data: <?= json_encode($eventValues) ?>,
            backgroundColor: ["#b97fffff", "#a36effff", "rgba(153, 62, 250, 1)"],
            borderRadius: 5,
            barThickness: 18
        }]
    },
    options: {
        indexAxis: "y",
        plugins: { legend: { display: false }},
        scales: { x: { beginAtZero: true }}
    }
});
</script>

</body>
</html>
