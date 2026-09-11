<?php
/**
 * Telegram-бот для Termux (статус батареи, снимки с камер, запись аудио).
 */

set_time_limit(300); // Лимит времени выполнения до 5 минут для длительных записей

// 1. Чтение конфигурации из .env
$env = parse_ini_file(__DIR__ . '/.env');
$token = $env['BOT_TOKEN'] ?? null;

if (!$token) {
    file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " - Ошибка: BOT_TOKEN не найден в .env\n", FILE_APPEND);
    exit;
}

// 2. Чтение входящего Webhook-запроса от Telegram
$input = file_get_contents('php://input');

// 3. Отправка ответа Telegram, чтобы завершить веб-соединение
http_response_code(200);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$update = json_decode($input, true);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text   = trim($update['message']['text']);

    $phpBin          = '/data/data/com.termux/files/usr/bin/php';
    $cliPath         = escapeshellarg(__DIR__ . '/cli.php');
    $photoPathScript = escapeshellarg(__DIR__ . '/photo.php');
    $audioPathScript = escapeshellarg(__DIR__ . '/audio.php');

    // --- Команда /status ---
    if ($text === '/status') {
        putenv('PATH=/data/data/com.termux/files/usr/bin');
        putenv('PREFIX=/data/data/com.termux/files/usr');
        putenv('HOME=/data/data/com.termux/files/home');
        putenv('LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib');

        $jsonResponse = shell_exec("{$phpBin} {$cliPath} 2>&1");

        if ($jsonResponse !== null && trim($jsonResponse) !== '') {
            $data = json_decode(trim($jsonResponse), true);

            if (isset($data['success']) &&$data['success'] === true) {
                $replyText  = "🔋 *Статус аккумулятара:*\n\n";
                $replyText .= "Зарад батарэі: " .$data['percentage'] . "%\n";
                $replyText .= "Статус: " .$data['status'] . "\n";
                $replyText .= "Тэмпература: " .$data['temperature'] . "°C";
            } else {
                $errorInfo  =$data['error'] ?? "Вывад скрыпта:\n`" . trim($jsonResponse) . "`";
                $replyText  = "⚠️ *Памылка пры атрыманні дадзеных:*\n" .$errorInfo;
            }
        } else {
            $replyText = "❌ Памылка: `shell_exec` вярнуў пусты рэзультат";
        }

        sendTelegramMessage($token,$chatId, $replyText);
    }

    // --- Команды для снимков с камер ---
    if ($text === '/photo_back' || $text === '/photo_front' || $text === '/photo') {
        $cameraId = ($text === '/photo_front') ? 1 : 0;
        $caption  = ($cameraId === 1) ? "📸 Здымак з пярэдней камеры" : "📸 Здымак з задняй камеры";
        sendTelegramMessage($token, $chatId, "Пачынаю $caption, цярпенне......");
        processPhotoRequest($token, $chatId, $phpBin, $photoPathScript, $cameraId, $caption);
    }

    // --- Команды для записи аудио (/vrecord, /vrecord_30, /vrecord_90, /vrecord_180) ---
    if (preg_match('/^\/vrecord(?:_(\d+))?$/', $text, $matches)) {
        $duration = isset($matches[1]) ? (int)$matches[1] : 30;

        if (!in_array($duration, [30, 90, 180])) {
            $duration = 30;
        }

        $caption = "🎙 *Галасавое паведамленне* ({$duration} сек.)";
        sendTelegramMessage($token, $chatId, "🎙 Пачынаю запіс аўдыё ({$duration} сек)...");

        processAudioRequest($token, $chatId, $phpBin, $audioPathScript, $duration, $caption);
    }

// --- Команды управления фонариком (/light_on, /light_off) ---
    if ($text === '/light_on' || $text === '/light_off') {
        $state = ($text === '/light_on') ? 'on' : 'off';

        $env  = 'export PATH=/data/data/com.termux/files/usr/bin:$PATH; ';
        $env .= 'export PREFIX=/data/data/com.termux/files/usr; ';
        $env .= 'export HOME=/data/data/com.termux/files/home; ';
        $env .= 'export LD_LIBRARY_PATH=/data/data/com.termux/files/usr/lib; ';

        $output = shell_exec($env . 'termux-torch ' . $state . ' 2>&1');

        $replyText = ($state === 'on') ? "💡 Ліхтарых уключаны" : "🔦 Ліхтарык выключаны";

        if ($output !== null && trim($output) !== '') {
            $replyText .= "\n⚠️ Адказ уціліты: `" . trim($output) . "`";
        }

        sendTelegramMessage($token, $chatId, $replyText);
    }

    if ($text === '/init_menu') {
        $cmds = [
            ['command' => 'status', 'description' => 'Battery status'],
            ['command' => 'photo', 'description' => 'Take photo'],
            ['command' => 'photo_back', 'description' => 'Back camera photo'],
            ['command' => 'photo_front', 'description' => 'Front camera photo'],
            ['command' => 'vrecord_30', 'description' => 'Record audio 30 sec'],
            ['command' => 'vrecord_90', 'description' => 'Record audio 90 sec'],
            ['command' => 'vrecord_180', 'description' => 'Record audio 180 sec'],
            ['command' => 'light_on', 'description' => 'Turn on flashlight'],
            ['command' => 'light_off', 'description' => 'Turn off flashlight']
        ];

        $scope = json_encode(['type' => 'all_private_chats']);

        file_get_contents("https://api.telegram.org/bot$token/deleteMyCommands");
        file_get_contents("https://api.telegram.org/bot$token/deleteMyCommands?scope=" . urlencode($scope));

        $res = file_get_contents("https://api.telegram.org/bot$token/setMyCommands?commands=" . urlencode(json_encode($cmds)) . "&scope=" . urlencode($scope));

        sendTelegramMessage($token,$chatId, "Адказ API: " . $res);
    }
}

/**
 * Обработка и отправка снимка
 */
function processPhotoRequest($token, $chatId, $phpBin, $scriptPath, $cameraId, $caption) {
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

/**
 * Обработка и отправка голосового сообщения
 */
function processAudioRequest($token, $chatId, $phpBin, $scriptPath, $duration, $caption) {
    $jsonResponse = shell_exec("{$phpBin} {$scriptPath} {$duration} 2>&1");

    if ($jsonResponse !== null && trim($jsonResponse) !== '') {
        $data = json_decode(trim($jsonResponse), true);

        if (isset($data['success']) && $data['success'] === true && !empty($data['audio_path'])) {
            $file = $data['audio_path'];

            $sent = sendTelegramVoice($token, $chatId, $file, $caption);

            if (!$sent) {
                sendTelegramMessage($token, $chatId, "⚠️ Не удалось отправить аудио в Telegram.");
            }

            if (file_exists($file)) {
                unlink($file);
            }
        } else {
            $errorInfo = $data['error'] ?? "Неизвестная ошибка записи";
            sendTelegramMessage($token, $chatId, "⚠️ *Ошибка записи:* " . $errorInfo);
        }
    } else {
        sendTelegramMessage($token, $chatId, "❌ Ошибка: `shell_exec` вернул пустой результат");
    }
}

/**
 * Отправка текстовых сообщений
 */
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

/**
 * Отправка изображений
 */
function sendTelegramPhoto($token, $chatId, $filePath, $caption = '') {
    if (!file_exists($filePath)) {
        return false;
    }

    $url = "https://api.telegram.org/bot{$token}/sendPhoto";
    $ch = curl_init();

    $postFields = [
        'chat_id'    => $chatId,
        'photo'      => new CURLFile($filePath),
        'caption'    => $caption,
        'parse_mode' => 'Markdown'
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode === 200);
}

/**
 * Отправка голосовых сообщений
 */
function sendTelegramVoice($token, $chatId, $filePath, $caption = '') {
    if (!file_exists($filePath)) {
        return false;
    }

    $ch = curl_init();

    $url = "https://api.telegram.org/bot{$token}/sendAudio";
    $postFields = [
        'chat_id'    => $chatId,
        'audio'      => new CURLFile($filePath, 'audio/m4a', 'audio.m4a'),
        'caption'    => $caption,
        'parse_mode' => 'Markdown'
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . " - Voice error: " . $result . "\n", FILE_APPEND);
    }

    return ($httpCode === 200);
}