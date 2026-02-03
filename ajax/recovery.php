<?php
session_start();
include("../settings/connect_datebase.php");

$login = $_POST['login'];
$security_answer = $_POST['security_answer'] ?? '';

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

//не большк 3 восстановлений в минуту
$query_rate_limit = $mysqli->query("
    SELECT COUNT(*) as request_count 
    FROM login_attempts 
    WHERE ip_address = '{$user_ip}' 
    AND attempt_time > DATE_SUB(NOW(), INTERVAL 60 SECOND)
    AND user_login LIKE 'recovery_%'
");

if ($query_rate_limit && $row = $query_rate_limit->fetch_assoc()) {
    if ($row['request_count'] >= 3) {
        http_response_code(429);
        echo "-1";
        exit();
    }
}

//попытка восстановления
$mysqli->query("
    INSERT INTO login_attempts (ip_address, user_login, attempt_time, success) 
    VALUES ('{$user_ip}', 'recovery_{$login}', NOW(), 0)
");

// Ищем пользователя
$query_user = $mysqli->query("SELECT * FROM `users` WHERE `login`='{$login}'");

$id = -1;
$user_data = null;

if($user_read = $query_user->fetch_assoc()) {
    $id = $user_read['id'];
    $user_data = $user_read;
}

function PasswordGeneration() {
    $chars = "qazxswedcvfrtgbnhyujmkiolp1234567890QAZXSWEDCVFRTGBNHYUJMKIOLP";
    $max = 10;
    $size = StrLen($chars) - 1;
    $password = "";
    
    while($max--) {
        $password .= $chars[rand(0, $size)];
    }
    
    return $password;
}

if($id != -1) {
    if (empty($security_answer)) {
        echo json_encode([
            'success' => true,
            'step' => 1,
            'question' => $user_data['security_question'] ?? 'Стандартный вопрос: Какой ваш любимый цвет?'
        ]);
        exit();
    } else {
        $correct_answer = strtolower(trim($user_data['security_answer'] ?? ''));
        $user_answer = strtolower(trim($security_answer));
        
        if ($correct_answer === $user_answer) {
            $password = PasswordGeneration();
            
            $query_password = $mysqli->query("SELECT * FROM `users` WHERE `password`= '" . md5($password) . "';");
            while($password_read = $query_password->fetch_row()) {
                $password = PasswordGeneration();
            }
            
            // Обновляем пароль
            $mysqli->query("UPDATE `users` SET `password`='" . md5($password) . "' WHERE `login` = '{$login}'");
            
            $mysqli->query("
                UPDATE login_attempts 
                SET success = 1 
                WHERE ip_address = '{$user_ip}' 
                AND user_login = 'recovery_{$login}' 
                ORDER BY id DESC LIMIT 1
            ");
            
            echo json_encode([
                'success' => true,
                'step' => 2,
                'message' => 'Новый пароль: ' . $password,
                'password' => $password
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Неправильный ответ на контрольный вопрос'
            ]);
        }
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Пользователь не найден'
    ]);
}
?>