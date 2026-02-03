<?php
session_start();
include("../settings/connect_datebase.php");

$login = $_POST['login'];
$password = $_POST['password'];


function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$user_ip = getClientIP();

//частые запросы
$query_rate_limit = $mysqli->query("
    SELECT COUNT(*) as request_count 
    FROM login_attempts 
    WHERE ip_address = '{$user_ip}' 
    AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 SECOND)
");

if ($query_rate_limit && $row = $query_rate_limit->fetch_assoc()) {
    if ($row['request_count'] >= 5) {
        http_response_code(429);
        echo "";
        exit();
    }
}

//неудачные попытки
$query_user_lock = $mysqli->query("
    SELECT id, failed_attempts, locked_until 
    FROM users 
    WHERE login = '{$login}'
");

$user_data = null;
$user_exists = false;

if ($query_user_lock && $query_user_lock->num_rows > 0) {
    $user_exists = true;
    $user_data = $query_user_lock->fetch_assoc();
    
    if ($user_data['locked_until'] != null) {
        $lock_time = strtotime($user_data['locked_until']);
        $current_time = time();
        
        if ($lock_time > $current_time) {
            http_response_code(423); //блокнутый
            echo "";
            exit();
        } else {
            //разблокнутый
            $mysqli->query("
                UPDATE users 
                SET locked_until = NULL, failed_attempts = 0 
                WHERE id = {$user_data['id']}
            ");
            $user_data['locked_until'] = null;
            $user_data['failed_attempts'] = 0;
        }
    }
    
    if ($user_data['failed_attempts'] >= 5 && $user_data['locked_until'] == null) {
        $lock_until = date('Y-m-d H:i:s', time() + 900);
        $mysqli->query("
            UPDATE users 
            SET locked_until = '{$lock_until}' 
            WHERE id = {$user_data['id']}
        ");
        http_response_code(423);
        echo "";
        exit();
    }
}

$query_user = $mysqli->query("SELECT * FROM `users` WHERE `login`='{$login}' AND `password`= '{$password}';");

$id = -1;
$success = false;
while($user_read = $query_user->fetch_row()) {
    $id = $user_read[0];
    $success = true;
}

//попытка входа
$mysqli->query("
    INSERT INTO login_attempts (ip_address, user_login, attempt_time, success) 
    VALUES ('{$user_ip}', '{$login}', NOW(), " . ($success ? '1' : '0') . ")
");

if($success) {
    $mysqli->query("
        UPDATE users 
        SET failed_attempts = 0, locked_until = NULL, last_login_attempt = NOW() 
        WHERE id = {$id}
    ");
    $_SESSION['user'] = $id;
    echo md5(md5($id));
} else {
    if ($user_exists && $user_data !== null) {
        $new_attempts = $user_data['failed_attempts'] + 1;
        
        $mysqli->query("
            UPDATE users 
            SET failed_attempts = {$new_attempts}, last_login_attempt = NOW() 
            WHERE login = '{$login}'
        ");
        
        if ($new_attempts >= 5) {
            $lock_until = date('Y-m-d H:i:s', time() + 900);
            $mysqli->query("
                UPDATE users 
                SET locked_until = '{$lock_until}' 
                WHERE login = '{$login}'
            ");
        }
    }
    echo "";
}
?>