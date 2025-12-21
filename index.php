<?php
session_start();
require "config.php";

$message = '';
$session_id = session_id();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username == '' || $password == '') {
        $message = "⚠ Please enter both username and password.";
    } else {
        // Get user from DB
        $sql = "SELECT * FROM users WHERE name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $message = "❌ User not found!";
        } else {
            $user_id = $user['user_id'];

            // Check if user is locked
            if ($user['is_locked'] == 1) {
                $locked_until = strtotime($user['locked_until']);
                if ($locked_until > time()) {
                    $remaining = $locked_until - time();
                    $minutes = floor($remaining / 60);
                    $seconds = $remaining % 60;
                    $message = "🚫 Account locked. Try again in {$minutes}m {$seconds}s.";
                    goto output;
                } else {
                    // Unlock automatically
                    $conn->query("UPDATE users SET is_locked = 0, locked_until = NULL WHERE user_id = $user_id");
                    $user['is_locked'] = 0;
                }
            }

            // Count failed attempts in last 15 minutes
            $sql = "SELECT COUNT(*) AS fail_count 
                    FROM login_attempts 
                    WHERE user_id = ? AND is_successful = 0 
                    AND attempt_time >= NOW() - INTERVAL 15 MINUTE";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            $failed_attempts = $data['fail_count'];

            // If next attempt is the 3rd failure, lock immediately
            if ($failed_attempts >= 2) {
                // Insert 3rd failed attempt
                $insert = "INSERT INTO login_attempts (user_id, session_id, is_successful) VALUES (?, ?, 0)";
                $stmt = $conn->prepare($insert);
                $stmt->bind_param("is", $user_id, $session_id);
                $stmt->execute();

                // Lock user
                $lock_time = date("Y-m-d H:i:s", strtotime("+15 minutes"));
                $conn->query("UPDATE users SET is_locked = 1, locked_until = '$lock_time' WHERE user_id = $user_id");

                // Notify once
                $notify = "INSERT INTO notifications (username, message) VALUES (?, '🚨 User locked due to 3 failed attempts')";
                $stmt = $conn->prepare($notify);
                $stmt->bind_param("s", $username);
                $stmt->execute();

                $message = "🚫 Account locked due to multiple failed attempts. Try again later (15 minutes).";
                goto output;
            }

            // Check password
            if (password_verify($password, $user['password'])) {
                // Record success
                $insert = "INSERT INTO login_attempts (user_id, session_id, is_successful) VALUES (?, ?, 1)";
                $stmt = $conn->prepare($insert);
                $stmt->bind_param("is", $user_id, $session_id);
                $stmt->execute();

                // Reset attempts
                $conn->query("DELETE FROM login_attempts WHERE user_id = $user_id");

                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $user['name'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                if ($user['role'] == 'super_admin') {
                    header("Location: SuperAdmin.php");
                } else {
                    header("Location: AdminHome.php");
                }
                exit;
            } else {
                // Record failed attempt (1st or 2nd)
                $insert = "INSERT INTO login_attempts (user_id, session_id, is_successful) VALUES (?, ?, 0)";
                $stmt = $conn->prepare($insert);
                $stmt->bind_param("is", $user_id, $session_id);
                $stmt->execute();

                $failed_attempts++;
                $message = "❌ Invalid login for <b>$username</b>. Attempt {$failed_attempts}/3";
            }
        }
    }
}

output:
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-container { margin-top: 100px; }
        .card { padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="container login-container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card">
                <h3 class="card-title text-center mb-4">Login</h3>

                <?php if ($message != ''): ?>
                    <div class="alert alert-warning"><?php echo $message; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn w-100" style="background-color: #28282B; color: #fff;">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Optional: JS countdown -->
<?php if (!empty($locked_until) && $locked_until > time()): ?>
<script>
let countdown = <?php echo $locked_until - time(); ?>;
const timer = setInterval(() => {
    if(countdown <= 0){
        clearInterval(timer);
        location.reload();
    } else {
        let minutes = Math.floor(countdown/60);
        let seconds = countdown % 60;
        document.querySelector('.alert').innerHTML = `🚫 Account locked. Try again in ${minutes}m ${seconds}s.`;
        countdown--;
    }
}, 1000);
</script>
<?php endif; ?>

</body>
</html>