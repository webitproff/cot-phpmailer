# PHPMailer Plugin for Cotonti Siena

The PHPMailer plugin enhances email sending capabilities in Cotonti Siena by integrating the PHPMailer library (version 6.10.0). It replaces the default `cot_mail` function with a robust SMTP-based solution, adding advanced features like plugin isolation, duplicate prevention, and detailed logging. This plugin is designed for developers who need fine-grained control over email notifications in Cotonti, particularly for plugins like `comments` and `contact`.

## Features

### 1. SMTP Email Sending
- Uses PHPMailer 6.10.0 to send emails via SMTP, supporting modern email servers with authentication and encryption (SSL/TLS).
- Configurable SMTP settings (host, port, username, password, etc.) through the Cotonti admin panel.
- Does not require Composer; all dependencies are included.

### 2. Plugin Isolation
- Allows selective disabling of email sending for specific plugins (e.g., `comments`, `contact`).
- Controlled via the `$enable_plugin_isolation` setting and `$isolated_plugins` array.
- Useful for preventing unwanted notifications (e.g., disabling comment notifications for admins).
- Relies on Cotonti's `$env['ext']` to identify the calling plugin, with fallback to 'unknown' if not set.

### 3. Flexible Recipient Handling
- Supports multiple recipient formats:
  - Single email string (e.g., `user2025nick.demo@domain.ltd`).
  - Array of emails (e.g., `['user2025nick.demo@domain.ltd', 'admin2025.demo@domain.ltd']`).
  - Complex arrays from plugins like `contact` (e.g., `['to' => 'support.demo@domain.ltd', 'from' => ['user2025nick.demo@domain.ltd', 'test_user']]`).
- Validates email addresses using `filter_var(FILTER_VALIDATE_EMAIL)` to prevent errors.
- Ignores invalid or non-email entries, ensuring robust handling.

### 4. Duplicate Email Prevention
- Prevents duplicate emails using two mechanisms:
  - **Full message key** (`$mail_key`): Based on recipient, subject, and body (MD5 hash). Blocks identical emails.
  - **Recipient-subject key** (`$recipient_key`): Blocks emails to the same recipient with the same subject within 60 seconds.
- Uses a global `$cot_mail_processed` array to track sent emails during the request.

### 5. Comprehensive Logging
- Logs all email attempts to `plugins/phpmailer/logs/phpmailer.log` (if enabled):
  - Includes `Request ID`, recipient, subject, plugin, request URI, GET/POST params, and a shortened email body.
  - Logs skips due to plugin isolation or duplicate prevention.
- SMTP debug logging to `plugins/phpmailer/logs/phpmailer_debug.log` (if enabled):
  - Captures detailed SMTP interactions (e.g., connection, authentication).
- Logging is configurable via `$enable_main_logging` and `$enable_debug_logging`.

### 6. Template Support
- Applies Cotonti email templates (`$cfg['subject_mail']`, `$cfg['body_mail']`) if no custom template is specified.
- Substitutes variables like `SITE_TITLE`, `SITE_URL`, `MAIL_SUBJECT`, and `MAIL_BODY`.
- Ensures proper UTF-8 encoding for subjects using `mb_encode_mimeheader`.

### 7. Error Handling
- Gracefully handles invalid recipients, SMTP errors, and configuration issues.
- Logs errors to both the plugin log and Cotonti’s system log (`cot_log`).
- Returns appropriate boolean values (`true` for success or skipped emails, `false` for failures) to maintain compatibility with Cotonti plugins.

### 8. PHP 8.2 Compatibility
- Tested with PHP 8.2 and Cotonti Siena v0.9.26.
- Avoids strict type issues and handles array-to-string conversions safely.

## Installation

1. **Download the Plugin**
   - Clone or download this repository to your Cotonti installation’s `plugins/` directory.

2. **Directory Structure**
   - Ensure the following structure:
     ```
     plugins/phpmailer/
     ├── phpmailer.global.php
     ├── phpmailer.setup.php
     ├── src/
     │   ├── PHPMailer.php
     │   ├── SMTP.php
     │   ├── Exception.php
     ├── lang/
     │   ├── phpmailer.en.lang.php
     │   ├── phpmailer.ru.lang.php
     ├── logs/
     │   ├── phpmailer.log (created automatically)
     │   ├── phpmailer_debug.log (created automatically)
     ```

3. **Set Permissions**
   - Ensure the `plugins/phpmailer/logs/` directory is writable:
     ```bash
     chmod 0755 plugins/phpmailer/logs
     chmod 0644 plugins/phpmailer/logs/*.log
     ```
   - The web server user (e.g., `www-data`) must have write access.

4. **Activate the Plugin**
   - Go to the Cotonti admin panel (`Administration > Extensions`).
   - Find `phpmailer` and activate it.

## Configuration

1. **SMTP Settings**
   - In the Cotonti admin panel, go to `Administration > Extensions > PHPMailer > Configure`.
   - Set the following:
     - `Host`: SMTP server (e.g., `smtp.gmail.com`).
     - `Port`: SMTP port (e.g., `587` for TLS).
     - `SMTPAuth`: Enable/disable authentication (`true`/`false`).
     - `SMTPSecure`: Encryption type (`tls` or `ssl`).
     - `Username`: SMTP username (e.g., `sender.demo@domain.ltd`).
     - `Password`: SMTP password.
     - `from_author`: Sender email (e.g., `no-reply.demo@domain.ltd`).
     - `from_name`: Sender name (e.g., `Demo Site`).
     - `reply`: Reply-to email (optional, e.g., `reply.demo@domain.ltd`).
     - `reply_name`: Reply-to name (optional).

2. **Plugin Settings**
   - Edit `plugins/phpmailer/phpmailer.global.php` to configure:
     - `$enable_plugin_isolation`: Set to `true` to enable isolation for specified plugins.
     - `$isolated_plugins`: Array of plugins to isolate (e.g., `['comments', 'contact']`).
     - `$enable_main_logging`: Set to `true` to enable logging to `phpmailer.log`.
     - `$enable_debug_logging`: Set to `true` to enable SMTP debug logging.
     - `$mail->SMTPDebug`: Set to `1` or higher for verbose SMTP debug output (requires `$enable_debug_logging = true`).

   Example:
   ```php
   $enable_plugin_isolation = true; // Enable isolation
   $isolated_plugins = ['comments', 'contact']; // Isolate comments and contact plugins
   $enable_main_logging = true; // Enable main log
   $enable_debug_logging = true; // Enable debug log
   $mail->SMTPDebug = 1; // Basic SMTP debug output
   ```

## Usage

### Plugin Isolation
- To prevent emails from specific plugins:
  - Set `$enable_plugin_isolation = true`.
  - Add plugin names to `$isolated_plugins` (e.g., `['comments', 'contact']`).
- Emails from these plugins will be skipped, with a log entry:
  ```
  [2025-05-07 12:00:00] Request ID: 123abc, Email to support.demo@domain.ltd skipped due to plugin isolation (plugin: contact).
  ```

### Logging
- Enable logging for debugging:
  - Set `$enable_main_logging = true` to log email attempts and results.
  - Set `$enable_debug_logging = true` and `$mail->SMTPDebug = 1` for SMTP details.
- Logs are stored in `plugins/phpmailer/logs/`:
  - `phpmailer.log`: General email activity (attempts, successes, skips).
  - `phpmailer_debug.log`: SMTP debug output.
- Example log entry:
  ```
  [2025-05-07 12:00:00] Request ID: 123abc, Attempt to send email to: {"to":"support.demo@domain.ltd","from":["user2025nick.demo@domain.ltd","test_user"]}, Subject: Dropped, HTML: false, Plugin: contact, Request URI: /contact, GET: {"rwr":"contact","e":"contact"}, POST: {...}, Body (short): Demo Site - https://demo.domain.ltd
  [2025-05-07 12:00:00] Request ID: 123abc, Successfully sent email to support.demo@domain.ltd
  ```

### Duplicate Prevention
- Automatically blocks:
  - Identical emails (same recipient, subject, body).
  - Emails to the same recipient with the same subject within 60 seconds.
- Logged as:
  ```
  [2025-05-07 12:00:00] Request ID: 123abc, Email to support.demo@domain.ltd with recipient key 1f6e555fafe32cbfb7a311fbf9e83f3e blocked due to recent send.
  ```

### Handling Complex Recipients
- The plugin handles complex `$fmail` arrays from plugins like `contact`:
  ```php
  $fmail = ['to' => 'support.demo@domain.ltd', 'from' => ['user2025nick.demo@domain.ltd', 'test_user']];
  ```
- Extracts valid emails from `$fmail['to']`, ignores `$fmail['from']`.

## Troubleshooting

### Emails Not Sent
- **Check SMTP Settings**: Verify `Host`, `Port`, `Username`, `Password` in the admin panel.
- **Enable Logging**: Set `$enable_main_logging = true`, `$enable_debug_logging = true`, `$mail->SMTPDebug = 1`, and check `phpmailer.log` and `phpmailer_debug.log`.
- **Isolation**: Ensure `$enable_plugin_isolation = false` or the plugin is not in `$isolated_plugins`.

### Duplicate Emails
- If an admin receives multiple emails (e.g., for comments):
  - Check if multiple admins are in `COT_GROUP_SUPERADMINS` (normal behavior).
  - Enable `$enable_main_logging = true` and inspect `Request ID` and `Mail Key` in `phpmailer.log`.
  - Reduce the duplicate timeout (e.g., from 60 to 10 seconds):
    ```php
    if (time() - $last_sent < 10) { ... }
    ```

### Log Files Empty
- Verify permissions:
  ```bash
  ls -l plugins/phpmailer/logs
  ```
  Ensure 0644 for log files and 0755 for the directory.
- Check `$enable_main_logging` and `$enable_debug_logging` are `true`.
- Check `error_log` for redirected logs:
  ```bash
  tail -n 50 /var/log/apache2/error.log
  ```

### Plugin Not Detected
- If `$env['ext']` returns 'unknown' for a plugin, add a fallback filter based on email body:
  ```php
  if ($enable_plugin_isolation && (in_array($context['plugin'], $isolated_plugins) || strpos($body, 'оставил Комментарий к странице') !== false)) {
      log_phpmailer_file("Request ID: $request_id, Email to $fmail_log skipped due to plugin isolation or comment content.");
      return true;
  }
  ```

## Compatibility
- **Cotonti**: Siena v0.9.26 (tested; may work with other versions, but not guaranteed).
- **PHP**: 8.2 (tested; compatible with 7.x, but test before use).
- **PHPMailer**: Version 6.10.0 (included, no Composer required).

## Contributing
- Fork the repository, make changes, and submit a pull request.
- Report issues or suggest features via GitHub Issues.

## License
- MIT License (see `LICENSE` file).

## Credits
- Built for the [CleanCot](https://cleancot.previewit.work/) project.
- Based on PHPMailer 6.10.0 by [PHPMailer](https://github.com/PHPMailer/PHPMailer).
- Developed with love for the Cotonti CMF ❤️.


## See also:

1.  **[Userarticles](https://github.com/webitproff/cot-userarticles) for CMF Cotonti**
   The plugin for CMF Cotonti displays a list of users with the number of their articles and a detailed list of articles for each user.

2. **[Export to Excel via PhpSpreadsheet](https://github.com/webitproff/cot-excel_export) for CMF Cotonti**
   Exporting Articles to Excel from the Database in Cotonti via PhpSpreadsheet.Composer is not required for installation.
   
3. **[Import from Excel via PhpSpreadsheet](https://github.com/webitproff/cot-excelimport-PhpSpreadsheet_No-Composer) for CMF Cotonti**
  The plugin for importing articles from Excel for all Cotonti-based websites.Composer is not required for installation.
   
4. **[SeoArticle Plugin for Cotonti](https://github.com/webitproff/seoarticle)**
   The SeoArticle plugin enhances the SEO capabilities of the Cotonti CMF's Pages module by adding meta tags, Open Graph, Twitter Card, Schema.org structured data, keyword extraction, reading time estimation, and related articles functionality.

5. **[CleanCot Theme for Cotonti](https://github.com/webitproff/cot-CleanCot)**
   The CleanCot theme for CMF Cotonti. Modern Bootstrap theme on v.5.3.3 for Cotonti Siena v.0.9.26 without outdated (legacy) mode. Only relevant tags!
   
   


*********
# Русский
*********

# Плагин PHPMailer для Cotonti Siena

Плагин PHPMailer улучшает функционал отправки email в Cotonti Siena, интегрируя библиотеку PHPMailer (версия 6.10.0). Он заменяет стандартную функцию `cot_mail` на мощное решение на основе SMTP, добавляя продвинутые возможности, такие как изоляция плагинов, защита от дублирования писем и детальное логирование. Плагин создан для разработчиков, которым нужен точный контроль над email-уведомлениями в Cotonti, особенно для плагинов `comments` и `contact`.

## Возможности

### 1. Отправка писем через SMTP
- Использует PHPMailer 6.10.0 для отправки писем через SMTP, поддерживая современные почтовые серверы с аутентификацией и шифрованием (SSL/TLS).
- Настраиваемые параметры SMTP (хост, порт, имя пользователя, пароль и т.д.) через админ-панель Cotonti.
- Не требует Composer; все зависимости включены в плагин.

### 2. Изоляция плагинов
- Позволяет выборочно отключать отправку писем для определённых плагинов (например, `comments`, `contact`).
- Управляется через настройку `$enable_plugin_isolation` и массив `$isolated_plugins`.
- Полезно для предотвращения нежелательных уведомлений (например, отключение уведомлений о комментариях для админов).
- Использует переменную Cotonti `$env['ext']` для определения вызывающего плагина, с возвратом 'unknown', если плагин не определён.

### 3. Гибкая обработка получателей
- Поддерживает несколько форматов получателей:
  - Одиночный email (например, `user2025nick.demo@domain.ltd`).
  - Массив email’ов (например, `['user2025nick.demo@domain.ltd', 'admin2025.demo@domain.ltd']`).
  - Сложные массивы от плагинов, таких как `contact` (например, `['to' => 'support.demo@domain.ltd', 'from' => ['user2025nick.demo@domain.ltd', 'test_user']]`).
- Проверяет email-адреса с помощью `filter_var(FILTER_VALIDATE_EMAIL)` для исключения ошибок.
- Игнорирует некорректные или не-email элементы, обеспечивая надёжную работу.

### 4. Защита от дублирования писем
- Предотвращает отправку дублирующихся писем с помощью двух механизмов:
  - **Ключ полного сообщения** (`$mail_key`): Основан на получателе, теме и теле письма (хэш MD5). Блокирует одинаковые письма.
  - **Ключ получатель-тема** (`$recipient_key`): Блокирует письма одному получателю с той же темой в течение 60 секунд.
- Использует глобальный массив `$cot_mail_processed` для отслеживания отправленных писем в рамках запроса.

### 5. Детальное логирование
- Записывает все попытки отправки писем в `plugins/phpmailer/logs/phpmailer.log` (если включено):
  - Включает `Request ID`, получателя, тему, плагин, URI запроса, параметры GET/POST и укороченное тело письма.
  - Логирует пропуски из-за изоляции плагинов или дублирования.
- Отладочное логирование SMTP в `plugins/phpmailer/logs/phpmailer_debug.log` (если включено):
  - Фиксирует подробности SMTP-взаимодействия (например, подключение, аутентификация).
- Логирование настраивается через `$enable_main_logging` и `$enable_debug_logging`.

### 6. Поддержка шаблонов
- Применяет шаблоны писем Cotonti (`$cfg['subject_mail']`, `$cfg['body_mail']`), если не указан кастомный шаблон.
- Подставляет переменные, такие как `SITE_TITLE`, `SITE_URL`, `MAIL_SUBJECT`, `MAIL_BODY`.
- Обеспечивает корректное кодирование тем в UTF-8 с помощью `mb_encode_mimeheader`.

### 7. Обработка ошибок
- Корректно обрабатывает некорректных получателей, ошибки SMTP и проблемы с конфигурацией.
- Логирует ошибки в лог плагина и системный лог Cotonti (`cot_log`).
- Возвращает соответствующие булевы значения (`true` для успешной отправки или пропуска, `false` для ошибок), сохраняя совместимость с плагинами Cotonti.

### 8. Совместимость с PHP 8.2
- Протестирован с PHP 8.2 и Cotonti Siena v0.9.26.
- Избегает проблем со строгими типами и безопасно обрабатывает преобразование массивов в строки.

## Установка

1. **Скачивание плагина**
   - Клонируйте или скачайте репозиторий в директорию `plugins/` вашей установки Cotonti.

2. **Структура директорий**
   - Убедитесь, что структура следующая:
     ```
     plugins/phpmailer/
     ├── phpmailer.global.php
     ├── phpmailer.setup.php
     ├── src/
     │   ├── PHPMailer.php
     │   ├── SMTP.php
     │   ├── Exception.php
     ├── lang/
     │   ├── phpmailer.en.lang.php
     │   ├── phpmailer.ru.lang.php
     ├── logs/
     │   ├── phpmailer.log (создаётся автоматически)
     │   ├── phpmailer_debug.log (создаётся автоматически)
     ```

3. **Установка прав доступа**
   - Убедитесь, что директория `plugins/phpmailer/logs/` доступна для записи:
     ```bash
     chmod 0755 plugins/phpmailer/logs
     chmod 0644 plugins/phpmailer/logs/*.log
     ```
   - Пользователь веб-сервера (например, `www-data`) должен иметь права на запись.

4. **Активация плагина**
   - Зайдите в админ-панель Cotonti (`Администрирование > Расширения`).
   - Найдите `phpmailer` и активируйте его.

## Настройка

1. **Настройки SMTP**
   - В админ-панели Cotonti перейдите в `Администрирование > Расширения > PHPMailer > Настроить`.
   - Установите следующие параметры:
     - `Host`: SMTP-сервер (например, `smtp.gmail.com`).
     - `Port`: Порт SMTP (например, `587` для TLS).
     - `SMTPAuth`: Включить/отключить аутентификацию (`true`/`false`).
     - `SMTPSecure`: Тип шифрования (`tls` или `ssl`).
     - `Username`: Имя пользователя SMTP (например, `sender.demo@domain.ltd`).
     - `Password`: Пароль SMTP.
     - `from_author`: Email отправителя (например, `no-reply.demo@domain.ltd`).
     - `from_name`: Имя отправителя (например, `Демо Сайт`).
     - `reply`: Email для ответа (опционально, например, `reply.demo@domain.ltd`).
     - `reply_name`: Имя для ответа (опционально).

2. **Настройки плагина**
   - Отредактируйте `plugins/phpmailer/phpmailer.global.php` для настройки:
     - `$enable_plugin_isolation`: Установите `true` для включения изоляции указанных плагинов.
     - `$isolated_plugins`: Массив плагинов для изоляции (например, `['comments', 'contact']`).
     - `$enable_main_logging`: Установите `true` для включения лога в `phpmailer.log`.
     - `$enable_debug_logging`: Установите `true` для включения отладочного лога SMTP.
     - `$mail->SMTPDebug`: Установите `1` или выше для подробного вывода отладки SMTP (требует `$enable_debug_logging = true`).

   Пример:
   ```php
   $enable_plugin_isolation = true; // Включить изоляцию
   $isolated_plugins = ['comments', 'contact']; // Изолировать плагины comments и contact
   $enable_main_logging = true; // Включить основной лог
   $enable_debug_logging = true; // Включить отладочный лог
   $mail->SMTPDebug = 1; // Базовый вывод отладки SMTP
   ```

## Использование

### Изоляция плагинов
- Чтобы предотвратить отправку писем из определённых плагинов:
  - Установите `$enable_plugin_isolation = true`.
  - Добавьте имена плагинов в `$isolated_plugins` (например, `['comments', 'contact']`).
- Письма из этих плагинов будут пропущены, с записью в лог:
  ```
  [2025-05-07 12:00:00] Request ID: 123abc, Email to support.demo@domain.ltd skipped due to plugin isolation (plugin: contact).
  ```

### Логирование
- Включите логирование для отладки:
  - Установите `$enable_main_logging = true` для записи попыток и результатов отправки.
  - Установите `$enable_debug_logging = true` и `$mail->SMTPDebug = 1` для деталей SMTP.
- Логи сохраняются в `plugins/phpmailer/logs/`:
  - `phpmailer.log`: Общая активность email (попытки, успехи, пропуски).
  - `phpmailer_debug.log`: Детали отладки SMTP.
- Пример записи в логе:
  ```
  [2025-05-07 12:00:00] Request ID: 123abc, Attempt to send email to: {"to":"support.demo@domain.ltd","from":["user2025nick.demo@domain.ltd","test_user"]}, Subject: Dropped, HTML: false, Plugin: contact, Request URI: /contact, GET: {"rwr":"contact","e":"contact"}, POST: {...}, Body (short): Демо Сайт - https://demo.domain.ltd
  [2025-05-07 12:00:00] Request ID: 123abc, Successfully sent email to support.demo@domain.ltd
  ```

### Защита от дублирования
- Автоматически блокирует:
  - Одинаковые письма (тот же получатель, тема, тело).
  - Письма одному получателю с той же темой в течение 60 секунд.
- Логируется как:
  ```
  [2025-05-07 12:00:00] Request ID: 123abc, Email to support.demo@domain.ltd with recipient key 1f6e555fafe32cbfb7a311fbf9e83f3e blocked due to recent send.
  ```

### Обработка сложных получателей
- Плагин обрабатывает сложные массивы `$fmail` от плагинов, таких как `contact`:
  ```php
  $fmail = ['to' => 'support.demo@domain.ltd', 'from' => ['user2025nick.demo@domain.ltd', 'test_user']];
  ```
- Извлекает валидные email’ы из `$fmail['to']`, игнорируя `$fmail['from']`.

## Устранение неполадок

### Письма не отправляются
- **Проверьте настройки SMTP**: Убедитесь, что `Host`, `Port`, `Username`, `Password` корректны в админ-панели.
- **Включите логирование**: Установите `$enable_main_logging = true`, `$enable_debug_logging = true`, `$mail->SMTPDebug = 1` и проверьте `phpmailer.log` и `phpmailer_debug.log`.
- **Изоляция**: Убедитесь, что `$enable_plugin_isolation = false` или плагин не в `$isolated_plugins`.

### Дублирование писем
- Если админ получает несколько писем (например, для комментариев):
  - Проверьте, есть ли несколько админов в `COT_GROUP_SUPERADMINS` (нормальное поведение).
  - Включите `$enable_main_logging = true` и проверьте `Request ID` и `Mail Key` в `phpmailer.log`.
  - Уменьшите таймаут дублирования (например, с 60 до 10 секунд):
    ```php
    if (time() - $last_sent < 10) { ... }
    ```

### Пустые файлы логов
- Проверьте права доступа:
  ```bash
  ls -l plugins/phpmailer/logs
  ```
  Убедитесь, что файлы логов имеют права 0644, а директория — 0755.
- Проверьте, что `$enable_main_logging` и `$enable_debug_logging` установлены в `true`.
- Проверьте `error_log` на перенаправленные логи:
  ```bash
  tail -n 50 /var/log/apache2/error.log
  ```

### Плагин не определяется
- Если `$env['ext']` возвращает 'unknown' для плагина, добавьте дополнительный фильтр по содержимому письма:
  ```php
  if ($enable_plugin_isolation && (in_array($context['plugin'], $isolated_plugins) || strpos($body, 'оставил Комментарий к странице') !== false)) {
      log_phpmailer_file("Request ID: $request_id, Email to $fmail_log skipped due to plugin isolation or comment content.");
      return true;
  }
  ```

## Совместимость
- **Cotonti**: Siena v0.9.26 (протестировано; может работать с другими версиями, но без гарантии).
- **PHP**: 8.2 (протестировано; совместимо с 7.x, но рекомендуется протестировать).
- **PHPMailer**: Версия 6.10.0 (включена, Composer не требуется).

## Вклад в разработку
- Форкните репозиторий, внесите изменения и отправьте pull request.
- Сообщайте о проблемах или предлагайте новые функции через GitHub Issues.

## Лицензия
- Лицензия MIT (см. файл `LICENSE`).

## Благодарности
- Создан для проекта [CleanCot](https://cleancot.previewit.work/).
- Основан на PHPMailer 6.10.0 от [PHPMailer](https://github.com/PHPMailer/PHPMailer).
- Разработан с любовью к движку Cotonti ❤️.



