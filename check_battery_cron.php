<?php
$token = "517180739:AAEWhNTDdKMdjQe_mOPXmKaHBUpaMjoqrW4";
$chatId = "88740047";
$threshold = 30;
$maxLevel = 100;

$termuxBin = '/data/data/com.termux/files/usr/bin/termux-battery-status';
$command = "{$termuxBin} 2>&1";

$output = shell_exec($command);
var_dump($output);
if ($output) {
    $data = json_decode($output, true);

    if (isset($data['percentage'])) {
        $level = (int) $data['percentage'];
        $status = $data['status'] ?? '';
        $plugged = $data['plugged'] ?? '';

        // Проверяем: уровень ниже 30% И устройство не заряжается
        if ($level < $threshold && $status !== 'CHARGING' && $plugged === 'UNPLUGGED') {
            $message = "⚠️⚠️⚠️⚠️ Нізкі ўзровень батарэі сервака: {$level}%. ⚠️⚠️⚠️⚠️⚠️⚠️⚠️ Тэрмінова паключы зараднае! ⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️";

            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $params = [
                'chat_id' => $chatId,
                'text'    => $message
            ];

            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($params)
                ]
            ];

            @file_get_contents($url, false, stream_context_create($options));
        }
        if ($level >= $maxLevel && $status === 'CHARGING' && $plugged === 'PLUGGED_AC') {
            $message = "⚠️⚠️⚠️⚠️ Узровень зарада: {$level}%. ⚠️⚠️⚠️⚠️⚠️⚠️⚠️ Тэрмінова адключы зараднае! ⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️⚠️";

            $url = "https://api.telegram.org/bot{$token}/sendMessage";
            $params = [
                'chat_id' => $chatId,
                'text'    => $message
            ];

            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($params)
                ]
            ];

            @file_get_contents($url, false, stream_context_create($options));
        }
    }
}