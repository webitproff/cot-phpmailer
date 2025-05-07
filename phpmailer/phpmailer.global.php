<?php
// Открывает PHP-скрипт, который будет выполняться на сервере
/* ====================
[BEGIN_COT_EXT]
Hooks=global
Order=10
[END_COT_EXT]
==================== */
// Определяет конфигурацию плагина для Cotonti: подключается к хуку 'global' с приоритетом 10, чтобы перехватывать вызовы функций отправки писем

// Проверяет, что скрипт вызван в контексте Cotonti, предотвращая прямой доступ к файлу
defined('COT_CODE') or die('Wrong URL.');

// Импортирует класс PHPMailer для отправки писем через SMTP
use PHPMailer\PHPMailer\PHPMailer;
// Импортирует класс Exception для обработки ошибок PHPMailer
use PHPMailer\PHPMailer\Exception;
// Импортирует класс SMTP для настройки SMTP-соединения
use PHPMailer\PHPMailer\SMTP;

// Подключает языковой файл плагина 'phpmailer', содержащий переводы и сообщения
require_once cot_langfile('phpmailer', 'plug');

// Делает глобальную переменную $cfg (конфигурация Cotonti) доступной в скрипте
global $cfg;

// Задаёт путь к директории плагина 'phpmailer' на основе конфигурации Cotonti
$pluginDir = $cfg['plugins_dir'] . '/phpmailer';
// Задаёт путь к директории для логов плагина
$logDir = "$pluginDir/logs";
// Задаёт путь к основному файлу лога (phpmailer.log) для записи событий отправки
$log_file = "$logDir/phpmailer.log";
// Задаёт путь к файлу отладочного лога (phpmailer_debug.log) для записи SMTP-деталей
$debug_log_file = "$logDir/phpmailer_debug.log";

// Отключает запись в основной лог (phpmailer.log), чтобы не создавать лишних записей
$enable_main_logging = false; // Вкл/выкл запись в phpmailer.log
// Отключает запись в отладочный лог (phpmailer_debug.log), чтобы не логировать SMTP
$enable_debug_logging = false; // Вкл/выкл запись в phpmailer_debug.log

// Отключает изоляцию плагинов, позволяя отправлять письма из всех плагинов
$enable_plugin_isolation = false; // Вкл/выкл изоляцию плагинов
// Определяет список плагинов ('comments', 'contact'), для которых можно включить изоляцию
$isolated_plugins = ['comments', 'contact']; // Плагины для изоляции

// Проверяет, существует ли директория логов
if (!file_exists($logDir)) {
    // Создаёт директорию логов с правами 0755, если она отсутствует
    mkdir($logDir, 0755, true);
}

// Определяет функцию для записи сообщений в основной лог (phpmailer.log)
function log_phpmailer_file($message) {
    // Делает глобальные переменные $log_file и $enable_main_logging доступными в функции
    global $log_file, $enable_main_logging;
    // Формирует временную метку для лога в формате ГГГГ-ММ-ДД ЧЧ:ММ:СС
    $timestamp = date('Y-m-d H:i:s');
    // Проверяет, включено ли логирование и доступен ли файл лога для записи
    if ($enable_main_logging && (is_writable($log_file) || (!file_exists($log_file) && is_writable(dirname($log_file))))) {
        // Записывает сообщение в файл лога с временной меткой, добавляя его в конец файла
        file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
    // Если файл лога недоступен, но логирование включено, пишет в системный error_log
    } elseif ($enable_main_logging) {
        error_log("[$timestamp] phpmailer: $message");
    }
}

// Определяет функцию для записи отладочных сообщений PHPMailer в phpmailer_debug.log
function log_phpmailer_file_debug($message) {
    // Делает глобальные переменные $debug_log_file и $enable_debug_logging доступными
    global $debug_log_file, $enable_debug_logging;
    // Формирует временную метку для лога в формате ГГГГ-ММ-ДД ЧЧ:ММ:СС
    $timestamp = date('Y-m-d H:i:s');
    // Проверяет, включено ли отладочное логирование и доступен ли файл для записи
    if ($enable_debug_logging && (is_writable($debug_log_file) || (!file_exists($debug_log_file) && is_writable(dirname($debug_log_file))))) {
        // Записывает отладочное сообщение в файл с временной меткой, добавляя в конец
        file_put_contents($debug_log_file, "[$timestamp] $message\n", FILE_APPEND);
    // Если файл недоступен, но отладка включена, пишет в системный error_log
    } elseif ($enable_debug_logging) {
        error_log("[$timestamp] phpmailer_debug: $message");
    }
}

// Объявляет глобальный массив для отслеживания отправленных писем и предотвращения дублирования
global $cot_mail_processed;
// Проверяет, инициализирован ли массив $cot_mail_processed
if (!isset($cot_mail_processed)) {
    // Создаёт пустой массив для хранения информации об отправленных письмах
    $cot_mail_processed = [];
}

// Определяет основную функцию для перехвата и обработки вызовов cot_mail в Cotonti
function cot_mail_custom($fmail, $subject, $body, $headers, $customtemplate, $additional_parameters, $html) {
    // Делает глобальные переменные доступными в функции
    global $cfg, $cot_mail_processed, $env, $enable_plugin_isolation, $isolated_plugins;

    // Генерирует уникальный идентификатор запроса для отслеживания в логах
    $request_id = uniqid();

    // Создаёт массив для хранения контекста вызова (плагин, URI, параметры)
    $context = [];
    // Определяет текущий плагин из переменной окружения Cotonti ($env['ext']) или 'unknown', если не задан
    $context['plugin'] = isset($env['ext']) ? $env['ext'] : 'unknown';
    // Сохраняет URI запроса или 'unknown', если $_SERVER['REQUEST_URI'] недоступен
    $context['request_uri'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown';
    // Сохраняет GET-параметры как JSON или 'none', если их нет
    $context['get_params'] = !empty($_GET) ? json_encode($_GET) : 'none';
    // Сохраняет POST-параметры как JSON или 'none', если их нет
    $context['post_params'] = !empty($_POST) ? json_encode($_POST) : 'none';

    // Обрезает тело письма до 200 символов для компактного логирования
    $short_body = substr($body, 0, 200) . (strlen($body) > 200 ? '...' : '');
    // Преобразует $fmail в строку: для массива — JSON, для строки — без изменений
    $fmail_log = is_array($fmail) ? json_encode($fmail, JSON_UNESCAPED_UNICODE) : $fmail;
    // Логирует попытку отправки письма с деталями вызова
    log_phpmailer_file("Request ID: $request_id, Attempt to send email to: $fmail_log, Subject: $subject, HTML: " . ($html ? 'true' : 'false') . ", Plugin: {$context['plugin']}, Request URI: {$context['request_uri']}, GET: {$context['get_params']}, POST: {$context['post_params']}, Body (short): " . $short_body);

    // Проверяет, включена ли изоляция и является ли текущий плагин изолируемым
    if ($enable_plugin_isolation && in_array($context['plugin'], $isolated_plugins)) {
        // Логирует пропуск отправки из-за изоляции плагина
        log_phpmailer_file("Request ID: $request_id, Email to $fmail_log skipped due to plugin isolation (plugin: {$context['plugin']}).");
        // Возвращает true, имитируя успешную отправку, чтобы не ломать логику плагина
        return true;
    }

    // Создаёт массив для хранения валидных email-адресов получателей
    $recipients = [];
    // Проверяет, является ли $fmail массивом (например, от плагина contact)
    if (is_array($fmail)) {
        // Проверяет наличие ключа 'to' в массиве $fmail
        if (isset($fmail['to'])) {
            // Если 'to' — строка, добавляет её как получателя
            if (is_string($fmail['to'])) {
                $recipients[] = $fmail['to'];
            // Если 'to' — массив, фильтрует валидные email-адреса
            } elseif (is_array($fmail['to'])) {
                $recipients = array_filter($fmail['to'], function($email) {
                    // Проверяет, что элемент — строка и валидный email
                    return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
                });
            }
        // Если $fmail — простой массив email’ов, фильтрует валидные адреса
        } elseif (is_array($fmail)) {
            $recipients = array_filter($fmail, function($email) {
                // Проверяет, что элемент — строка и валидный email
                return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
            });
        }
    // Если $fmail — строка и валидный email, добавляет его в получатели
    } elseif (is_string($fmail) && filter_var($fmail, FILTER_VALIDATE_EMAIL)) {
        $recipients[] = $fmail;
    }

    // Проверяет, найдены ли валидные получатели
    if (empty($recipients)) {
        // Логирует отсутствие валидных получателей и завершает выполнение
        log_phpmailer_file("Request ID: $request_id, No valid recipients found in $fmail_log, aborting.");
        // Возвращает false, сигнализируя об ошибке
        return false;
    }

    // Инициализирует флаг успешной отправки для всех получателей
    $success = true;
    // Перебирает всех валидных получателей
    foreach ($recipients as $single_fmail) {
        // Создаёт уникальный ключ для письма на основе получателя, темы и тела
        $mail_key = md5($single_fmail . $subject . $body);
        // Создаёт ключ для получателя и темы для защиты от частых отправок
        $recipient_key = md5($single_fmail . $subject);

        // Проверяет, отправлялось ли письмо этому получателю с такой темой недавно
        if (isset($cot_mail_processed[$recipient_key])) {
            // Получает время последней отправки
            $last_sent = $cot_mail_processed[$recipient_key]['timestamp'];
            // Блокирует отправку, если прошло менее 60 секунд
            if (time() - $last_sent < 60) {
                // Логирует блокировку из-за недавней отправки
                log_phpmailer_file("Request ID: $request_id, Email to $single_fmail with recipient key $recipient_key blocked due to recent send.");
                // Помечает отправку как неуспешную, но продолжает цикл
                $success = false;
                continue;
            }
        }

        // Проверяет, обрабатывалось ли письмо с таким содержимым
        if (isset($cot_mail_processed[$mail_key])) {
            // Логирует пропуск из-за уже обработанного письма
            log_phpmailer_file("Request ID: $request_id, Email to $single_fmail with mail key $mail_key already processed, skipping.");
            // Помечает отправку как неуспешную, но продолжает цикл
            $success = false;
            continue;
        }

        // Отмечает письмо как обработанное, чтобы избежать дублирования
        $cot_mail_processed[$mail_key] = true;
        // Сохраняет время отправки для получателя и темы
        $cot_mail_processed[$recipient_key] = ['timestamp' => time()];

        // Вызывает вспомогательную функцию для отправки письма одному получателю
        $result = cot_mail_single($single_fmail, $subject, $body, $headers, $customtemplate, $additional_parameters, $html, $request_id);
        // Если отправка не удалась, помечает общий результат как неуспешный
        if (!$result) {
            $success = false;
        }
    }

    // Возвращает true, если хотя бы одно письмо отправлено успешно, или false при полной неудаче
    return $success;
}

// Определяет вспомогательную функцию для отправки одного письма через PHPMailer
function cot_mail_single($fmail, $subject, $body, $headers, $customtemplate, $additional_parameters, $html, $request_id) {
    // Делает глобальные переменные $cfg и $cot_mail_processed доступными
    global $cfg, $cot_mail_processed;

    // Проверяет, что email не пустой и является валидным
    if (empty($fmail) || !filter_var($fmail, FILTER_VALIDATE_EMAIL)) {
        // Логирует ошибку из-за некорректного email
        log_phpmailer_file("Request ID: $request_id, Invalid or empty email: $fmail, aborting.");
        // Возвращает false, сигнализируя об ошибке
        return false;
    }

    // Проверяет, используется ли стандартный шаблон письма
    if (!$customtemplate) {
        // Создаёт массив параметров для подстановки в шаблон тела письма
        $body_params = [
            'SITE_TITLE' => $cfg['maintitle'], // Название сайта
            'SITE_URL' => $cfg['mainurl'], // URL сайта
            'SITE_DESCRIPTION' => $cfg['subtitle'], // Описание сайта
            'ADMIN_EMAIL' => $cfg['adminemail'], // Email администратора
            'MAIL_SUBJECT' => $subject, // Тема письма
            'MAIL_BODY' => $body // Тело письма
        ];
        // Создаёт массив параметров для подстановки в шаблон темы
        $subject_params = [
            'SITE_TITLE' => $cfg['maintitle'], // Название сайта
            'SITE_DESCRIPTION' => $cfg['subtitle'], // Описание сайта
            'MAIL_SUBJECT' => $subject // Тема письма
        ];
        // Применяет шаблон темы письма из конфигурации Cotonti
        $subject = cot_title($cfg['subject_mail'], $subject_params, false);
        // Применяет шаблон тела письма, заменяя \r\n на \n для совместимости
        $body = cot_title(str_replace("\r\n", "\n", $cfg['body_mail']), $body_params, false);
    }

    // Кодирует тему письма в MIME-формате (UTF-8, Base64) для корректного отображения
    $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\n");

    // Подключает класс Exception для обработки ошибок PHPMailer
    require_once $cfg['plugins_dir'] . '/phpmailer/src/Exception.php';
    // Подключает основной класс PHPMailer
    require_once $cfg['plugins_dir'] . '/phpmailer/src/PHPMailer.php';
    // Подключает класс SMTP для работы с SMTP-сервером
    require_once $cfg['plugins_dir'] . '/phpmailer/src/SMTP.php';

    // Объявляет глобальную переменную $error для хранения сообщения об ошибке
    global $error;
    // Создаёт новый экземпляр PHPMailer
    $mail = new PHPMailer;
    // Устанавливает режим отправки через SMTP
    $mail->isSMTP();
    // Задаёт хост SMTP-сервера из конфигурации плагина
    $mail->Host = $cfg['plugin']['phpmailer']['Host'];
    // Задаёт порт SMTP-сервера
    $mail->Port = $cfg['plugin']['phpmailer']['Port'];
    // Включает или отключает SMTP-аутентификацию
    $mail->SMTPAuth = (bool)$cfg['plugin']['phpmailer']['SMTPAuth'];
    // Задаёт тип шифрования (например, 'ssl' или 'tls')
    $mail->SMTPSecure = $cfg['plugin']['phpmailer']['SMTPSecure'];
    // Задаёт имя пользователя для SMTP-аутентификации
    $mail->Username = $cfg['plugin']['phpmailer']['Username'];
    // Задаёт пароль для SMTP-аутентификации
    $mail->Password = $cfg['plugin']['phpmailer']['Password'];
    // Устанавливает кодировку писем в UTF-8
    $mail->CharSet = 'UTF-8';
    // Устанавливает отправителя письма из конфигурации плагина
    $mail->setFrom($cfg['plugin']['phpmailer']['from_author'], $cfg['plugin']['phpmailer']['from_name']);
    // Проверяет, задан ли адрес для ответа в конфигурации
    if (!empty($cfg['plugin']['phpmailer']['reply'])) {
        // Устанавливает адрес для ответа из конфигурации
        $mail->addReplyTo($cfg['plugin']['phpmailer']['reply'], $cfg['plugin']['phpmailer']['reply_name']);
    // Если адрес ответа не задан, использует адрес отправителя
    } else {
        $mail->addReplyTo($cfg['plugin']['phpmailer']['from_author'], $cfg['plugin']['phpmailer']['from_name']);
    }
    // Добавляет получателя письма
    $mail->addAddress($fmail);
    // Устанавливает, является ли письмо HTML (true/false)
    $mail->isHTML($html);
    // Задаёт тему письма
    $mail->Subject = $subject;
    // Задаёт тело письма
    $mail->Body = $body;

    // Отключает отладку SMTP, чтобы не записывать детали соединения
    $mail->SMTPDebug = 0;
    // Определяет функцию для обработки отладочных сообщений PHPMailer
    $mail->Debugoutput = function($str, $level) {
        // Вызывает функцию логирования для записи SMTP-деталей
        log_phpmailer_file_debug("SMTP Debug [$level]: $str");
    };

    // Пытается отправить письмо через PHPMailer
    if (!$mail->Send()) {
        // Сохраняет сообщение об ошибке отправки
        $error = 'Mail error: ' . $mail->ErrorInfo;
        // Логирует ошибку отправки
        log_phpmailer_file("Request ID: $request_id, Failed to send email to $fmail: $error");
        // Записывает ошибку в системный лог Cotonti
        cot_log($error, 'eml');
        // Возвращает false, сигнализируя об ошибке
        return false;
    // Если отправка успешна
    } else {
        // Сохраняет сообщение об успешной отправке
        $error = 'Message sent!';
        // Логирует успешную отправку
        log_phpmailer_file("Request ID: $request_id, Successfully sent email to $fmail");
        // Записывает успех в системный лог Cotonti
        cot_log($error, 'eml');
        // Возвращает true, сигнализируя об успехе
        return true;
    }
}