<?php
session_start();
include("../settings/connect_datebase.php");

$login = $_POST['login'];
$password = $_POST['password'];
$security_question = $_POST['security_question'] ?? 'Стандартный вопрос: Какой ваш любимый цвет?';
$security_answer = $_POST['security_answer'] ?? 'белый';

if (!function_exists('getClientIP')) {
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
}

$user_ip = getClientIP();

//не больше 3 регистраций в минуту
$query_rate_limit = $mysqli->query("
    SELECT COUNT(*) as request_count 
    FROM login_attempts 
    WHERE ip_address = '{$user_ip}' 
    AND attempt_time > DATE_SUB(NOW(), INTERVAL 60 SECOND)
");

if ($query_rate_limit && $row = $query_rate_limit->fetch_assoc()) {
    if ($row['request_count'] >= 3) {
        http_response_code(429);
        echo "-1";
        exit();
    }
}

// ищем пользователя
$query_user = $mysqli->query("SELECT * FROM `users` WHERE `login`='{$login}'");
$id = -1;

if($user_read = $query_user->fetch_row()) {
    echo $id;
} else {
    //регистрация с контрольным вопросом
    $mysqli->query("INSERT INTO `users`(`login`, `password`, `roll`, `security_question`, `security_answer`) 
                    VALUES ('{$login}', '{$password}', 0, '{$security_question}', '{$security_answer}')");
    
    $query_user = $mysqli->query("SELECT * FROM `users` WHERE `login`='{$login}' AND `password`= '{$password}';");
    $user_new = $query_user->fetch_row();
    $id = $user_new[0];
        
    if($id != -1) {
        $_SESSION['user'] = $id;
        // Записываем успешную регистрацию
        $mysqli->query("
            INSERT INTO login_attempts (ip_address, user_login, attempt_time, success) 
            VALUES ('{$user_ip}', '{$login}', NOW(), 1)
        ");
    }
    echo $id;
}
?>