<?php
session_start();
include("../settings/connect_datebase.php");

// Проверяем авторизацию
if (!isset($_SESSION['user']) || $_SESSION['user'] == -1) {
    http_response_code(401);
    exit();
}

$IdUser = $_SESSION['user'];
$Message = $_POST["Message"];
$IdPost = $_POST["IdPost"];

//не больше 5 комментов в минуту
$query_rate_limit = $mysqli->query("
    SELECT COUNT(*) as comment_count 
    FROM (
        SELECT Id 
        FROM comments 
        WHERE IdUser = {$IdUser} 
        ORDER BY Id DESC 
        LIMIT 100
    ) as last_comments
");

if ($query_rate_limit && $row = $query_rate_limit->fetch_assoc()) {
    if ($row['comment_count'] >= 5) {
        //время комментов
        $query_time_check = $mysqli->query("
            SELECT COUNT(*) as recent_comments 
            FROM comments 
            WHERE IdUser = {$IdUser} 
            AND Id >= (
                SELECT IFNULL(MAX(Id), 0) - 4 
                FROM comments 
                WHERE IdUser = {$IdUser}
            )
        ");
        
        if ($query_time_check && $row_time = $query_time_check->fetch_assoc()) {
            if ($row_time['recent_comments'] >= 5) {
                http_response_code(429);
                exit();
            }
        }
    }
}

//спам
$query_spam_check = $mysqli->query("
    SELECT COUNT(*) as same_count 
    FROM comments 
    WHERE IdUser = {$IdUser} 
    AND Messages = '" . $mysqli->real_escape_string($Message) . "'
    AND Id >= (
        SELECT IFNULL(MAX(Id), 0) - 99 
        FROM comments 
        WHERE IdUser = {$IdUser}
    )
");

if ($query_spam_check && $row = $query_spam_check->fetch_assoc()) {
    if ($row['same_count'] >= 3) {
        http_response_code(400);
        exit();
    }
}

// Все проверки пройдены - добавляем комментарий
$mysqli->query("INSERT INTO `comments`(`IdUser`, `IdPost`, `Messages`) VALUES ({$IdUser}, {$IdPost}, '{$Message}');");

echo "OK";
?>