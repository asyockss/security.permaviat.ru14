<?php
session_start();
if (isset($_SESSION['user'])) {
    if($_SESSION['user'] != -1) {
        include("./settings/connect_datebase.php");
        
        $user_query = $mysqli->query("SELECT * FROM `users` WHERE `id` = ".$_SESSION['user']);
        while($user_read = $user_query->fetch_row()) {
            if($user_read[3] == 0) header("Location: user.php");
            else if($user_read[3] == 1) header("Location: admin.php");
        }
    }
}
?>
<!DOCTYPE HTML>
<html>
    <head> 
        <script src="https://code.jquery.com/jquery-1.8.3.js"></script>
        <meta charset="utf-8">
        <title> Восстановление пароля </title>
        
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="top-menu">
            <a href=# class = "singin"><img src = "img/ic-login.png"/></a>
        
            <a href=#><img src = "img/logo1.png"/></a>
            <div class="name">
                <a href="index.php">
                    <div class="subname">Электронная приемная комиссия</div>
                    Пермского авиационного техникума им. А. Д. Швецова
                </a>
            </div>
        </div>
        <div class="space"> </div>
        <div class="main">
            <div class="content">
                <div class="input-error">
                    <img src="img/ic-close.png" class="close" onclick="DisableError()"/>
                    <img src = "img/ic-error.png"/>
                    Ошибка.
                    <div class="message">Указанный вами адрес электронной почты не существует в системе, проверьте правильность ввода данных.</div>
                </div>
            
                <div class="success" style="display: none;">
                    <img src = "img/ic_success.png">
                    <div class = "name">Успешно!</div>
                    <div class = "description">
                        Ваш пароль успешно сброшен. Запишите новый пароль:
                    </div>
                    <div class="new-password" style="font-weight: bold; font-size: 18px; margin: 15px 0; padding: 10px; background: #f0f0f0; border-radius: 5px; text-align: center;"></div>
                    <div style="color: red; font-size: 14px;">Сохраните этот пароль! Он больше не будет показан.</div>
                </div>
            
                <div class = "login" id="step1">
                    <div class="name">Восстановление пароля</div>
                
                    <div class = "sub-name">Почта (логин):</div>
                    <div style="font-size: 12px; margin-bottom: 10px;">Введите ваш логин для восстановления пароля.</div>
                    <input name="_login" type="text" placeholder="E-mail@mail.ru"/>
                    
                    <input type="button" class="button" value="Далее" onclick="CheckUser()" style="margin-top: 10px;"/>
                    <img src = "img/loading.gif" class="loading" style="margin-top: 10px;"/>
                </div>
                
                <div class = "login" id="step2" style="display: none;">
                    <div class="name">Ответьте на контрольный вопрос</div>
                
                    <div class = "sub-name" id="question_text">Контрольный вопрос:</div>
                    <div class="question" id="question_display" style="font-style: italic; margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-left: 3px solid #4CAF50;"></div>
                    
                    <div class = "sub-name">Ваш ответ:</div>
                    <div style="font-size: 12px; margin-bottom: 10px;">Введите ответ, который вы указали при регистрации.</div>
                    <input name="_security_answer" type="text" placeholder="Введите ответ"/>
                    
                    <div style="margin-top: 15px;">
                        <input type="button" class="button" value="Назад" onclick="GoBack()" style="margin-right: 10px;"/>
                        <input type="button" class="button" value="Сбросить пароль" onclick="ResetPassword()"/>
                    </div>
                    <img src = "img/loading.gif" class="loading" style="margin-top: 10px;"/>
                </div>
                
                <div class="footer">
                    © КГАПОУ "Авиатехникум", 2020
                    <a href=#>Конфиденциальность</a>
                    <a href=#>Условия</a>
                </div>
            </div>
        </div>
        
        <script>
			var errorWindow = document.getElementsByClassName("input-error")[0];
			var loading = document.getElementsByClassName("loading")[0];
			var currentLogin = '';

			errorWindow.style.display = "none";

			function DisableError() {
				errorWindow.style.display = "none";
			}

			function EnableError(message) {
				document.getElementsByClassName("message")[0].innerHTML = message;
				errorWindow.style.display = "block";
			}

			function CheckUser() {
				var _login = document.getElementsByName("_login")[0].value;
				
				if(_login == "") {
					alert("Введите логин.");
					return;
				}
				
				DisableError();
				
				loading.style.display = "block";
				currentLogin = _login;
				
				var data = new FormData();
				data.append("login", _login);
				
				// AJAX запрос
				$.ajax({
					url         : 'ajax/recovery.php',
					type        : 'POST',
					data        : data,
					cache       : false,
					dataType    : 'json',
					processData : false,
					contentType : false, 
					success: function (response) {
						loading.style.display = "none";
						
						if(response.success) {
							if(response.step == 1) {
								document.getElementById('question_display').innerHTML = response.question;
								document.getElementById('step1').style.display = "none";
								document.getElementById('step2').style.display = "block";
								DisableError();
							}
						} else {
							EnableError(response.message || "Пользователь не найден.");
						}
					},
					error: function(){
						loading.style.display = "none";
						EnableError("Системная ошибка!");
					}
				});
			}

			function ResetPassword() {
				var _security_answer = document.getElementsByName("_security_answer")[0].value;
				
				if(_security_answer == "") {
					alert("Введите ответ на контрольный вопрос.");
					return;
				}
				
				DisableError();
				
				loading.style.display = "block";
				
				var data = new FormData();
				data.append("login", currentLogin);
				data.append("security_answer", _security_answer);
				
				// AJAX запрос
				$.ajax({
					url         : 'ajax/recovery.php',
					type        : 'POST',
					data        : data,
					cache       : false,
					dataType    : 'json',
					processData : false,
					contentType : false, 
					success: function (response) {
						loading.style.display = "none";
						
						if(response.success && response.step == 2) {
							document.getElementsByClassName('success')[0].style.display = "block";
							document.querySelector('.new-password').innerHTML = response.password;
							document.getElementById('step2').style.display = "none";
							DisableError();
							
							navigator.clipboard.writeText(response.password).then(function() {
								console.log('Пароль скопирован в буфер обмена');
							});
						} else {
							EnableError(response.message || "Неправильный ответ.");
						}
					},
					error: function(){
						loading.style.display = "none";
						EnableError("Системная ошибка!");
					}
				});
			}

			function GoBack() {
				document.getElementById('step2').style.display = "none";
				document.getElementById('step1').style.display = "block";
				document.getElementsByName("_security_answer")[0].value = "";
				DisableError();
			}
        </script>
    </body>
</html>