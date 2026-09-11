<?php
/**
 * Telegram-бот для Termux (статус батареи, снимки с передней и задней камер).
 */

http_response_code(200);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$env = parse_ini_file(__DIR__ . '/.env');
$token = $env['BOT_TOKEN'] ?? null;

if (!$token) {
    die("Ошибка: BOT_TOKEN не найден в .env");
}

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text   = trim($update['message']['text']);

    $phpBin          = '/data/data/com.termux/files/usr/bin/php';
    $cliPath         = escapeshellarg(__DIR__ . '/cli.php');
    $photoPathScript = escapeshellarg(__DIR__ . '/photo.php');

    // Команда /status
    if ($text === '/status') {
        $jsonResponse = shell_exec("{$phpBin} {$cliPath} 2>&1");

        if ($jsonResponse !== null && trim($jsonResponse) !== '') {
            $data = json_decode(trim($jsonResponse), true);

            if (isset($data['success']) && $data['success'] === true) {
                $replyText  = "🔋 *Статус аккумулятора:*\n\n";
                $replyText .= "Заряд батареи: " . $data['percentage'] . "%\n";
                $replyText .= "Статус: " . $data['status'] . "\n";
                $replyText .= "Температура: " . $data['temperature'] . "°C";
            } else {
                $errorInfo  = $data['error'] ?? "Вывод скрипта:\n`" . trim($jsonResponse) . "`";
                $replyText  = "⚠️ *Ошибка при получении данных:*\n" . $errorInfo;
            }
        } else {
            $replyText = "❌ Ошибка: `shell_exec` вернул пустой результат";
        }

        sendTelegramMessage($token, $chatId, $replyText);
    }

    // Команды для фото
    if ($text === '/photo_back' || $text === '/photo_front' || $text === '/photo') {
        // 0 — задняя камера, 1 — передняя
        $cameraId = ($text === '/photo_front') ? 1 : 0;
        $caption  = ($cameraId === 1) ? "📸 Снимок с передней камеры" : "📸 Снимок с задней камеры";

        processPhotoRequest($token, $chatId, $phpBin, $photoPathScript, $cameraId, $caption);
    }
}

/**
 * Обработка и отправка снимка
 */
function processPhotoRequest($token, $chatId, $phpBin, $scriptPath, $cameraId, $caption) {
    // Передаем ID камеры ($cameraId) в качестве аргумента для photo.php
    $jsonResponse = shell_exec("{$phpBin} {$scriptPath} {$cameraId} 2>&1");

    if ($jsonResponse !== null && trim($jsonResponse) !== '') {
        $data = json_decode(trim($jsonResponse), true);

        if (isset($data['success']) && $data['success'] === true && !empty($data['photo_path'])) {
            $file = $data['photo_path'];

            $sent = sendTelegramPhoto($token, $chatId, $file, $caption);

            if (!$sent) {
                sendTelegramMessage($token, $chatId, "⚠️ Не удалось отправить фото в Telegram.");
            }

            if (file_exists($file)) {
                unlink($file);
            }
        } else {
            $errorInfo = $data['error'] ?? "Неизвестная ошибка съёмки";
            sendTelegramMessage($token, $chatId, "⚠️ *Ошибка при съёмке:*\n" . $errorInfo);
        }
    } else {
        sendTelegramMessage($token, $chatId, "❌ Ошибка: `shell_exec` вернул пустой результат");
    }
}

function sendTelegramMessage($token, $chatId, $text) {
    $sendUrl = "https://api.telegram.org/bot{$token}/sendMessage";
    $params  = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'Markdown'
    ];

    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($params)
        ]
    ];

    return file_get_contents($sendUrl, false, stream_context_create($options));
}

function sendTelegramPhoto($token, $chatId, $filePath, $caption = '') {
    if (!file_exists($filePath)) {
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/sendPhoto";
    $ch = curl_init();

    $postFields = [
        'chat_id' => $chatId,
        'photo'   => new CURLFile($filePath),
        'caption' => $caption
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200);
}