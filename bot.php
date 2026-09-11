<?php
/**
 * Telegram-бот для проверки статуса аккумулятора Termux.
 */

// 1. Мгновенно возвращаем 200 OK Telegram, чтобы избежать таймаутов
http_response_code(200);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$token = "517180739:AAEWhNTDdKMdjQe_mOPXmKaHBUpaMjoqrW4";

// 2. Чтение входящего сообщения от Telegram (Webhook)
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text   = trim($update['message']['text']);

    if ($text === '/status') {
        $phpBin = '/data/data/com.termux/files/usr/bin/php';
        $cliPath = escapeshellarg(__DIR__ . '/cli.php');

        // Выполняем cli.php напрямую в CLI с полным путем и захватом stderr (2>&1)
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

        // 3. Отправка ответа в Telegram
        $sendUrl = "https://api.telegram.org/bot{$token}/sendMessage";
        $params  = [
            'chat_id'    => $chatId,
            'text'       => $replyText,
            'parse_mode' => 'Markdown'
        ];

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($params)
            ]
        ];

        file_get_contents($sendUrl, false, stream_context_create($options));
    }

    if ($text === '/photo') {
        //TODO implement the logic here
    }
}
