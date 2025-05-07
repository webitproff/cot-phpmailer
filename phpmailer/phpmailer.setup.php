<?php
/* ====================
[BEGIN_COT_EXT]
Code=phpmailer
Name=PHPMailer
Description=Отправка почты через SMTP на основе релиза PHPMailer v.6.10.0 from May 2025
Version=3.0.0-6.10.0
Date=05.07.2025
Author=PHPMailer, Cotonti Team, webitproff
Copyright=&copy; 2023-2025 webitproff
Notes=<a href="https://github.com/webitproff" target="_blank"><strong>Extentions for Cotonti Siena CMF</strong></a>
Auth_guests=RW
Lock_guests=12345A
Auth_members=RW
Lock_members=
[END_COT_EXT]
 
[BEGIN_COT_EXT_CONFIG]
SMTPAuth=01:string::1:SMTP Auth
SMTPSecure=02:string::TLS:SMTPSecure
Host=03:string::ssl://smtp.beget.com:Host-Сервер исходящей почты
Port=04:string::465:Port
Username=05:string:::Username
Password=06:string:::Password
from_author=07:string:::email отправителя
from_name=08:string:::Имя отправителя
[END_COT_EXT_CONFIG]
==================== */


defined('COT_CODE') or die('Wrong URL');
