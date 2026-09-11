<?php
/**
 * Скрипт получения статуса аккумулятора в Termux и отправки ответа в формате JSON.
 */

// Указываем браузеру и клиенту, что ответ передается в формате JSON
header('Content-Type: application/json; charset=utf-8');

// 1. Абсолютный путь к утилите Termux
$termuxBin = 'timeout 3 termux-battery-status /data/data/com.termux/files/usr/bin/termux-battery-status';
$command = "{$termuxBin} 2>&1";

// 2. Выполняем команду
$output = shell_exec($command);

// 3. Формируем и отдаем JSON-ответ
if ($output === null) {
    echo json_encode([
        'success' => false,
        'error'   => 'Не удалось выполнить команду shell_exec.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$batteryData = json_decode($output, true);

if (json_last_error() === JSON_ERROR_NONE) {
    // Успешно получили данные от termux-battery-status
    echo json_encode([
        'success'     => true,
        'percentage'  => $batteryData['percentage'] ?? 0,
        'status'      => $batteryData['status'] ?? 'неизвестно',
        'temperature' => $batteryData['temperature'] ?? 0
    ], JSON_UNESCAPED_UNICODE);
} else {
    // Если команда вернула ошибку вместо JSON
    echo json_encode([
        'success' => false,
        'error'   => 'Ошибка выполнения команды: ' . $output
    ], JSON_UNESCAPED_UNICODE);
}