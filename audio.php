<?php
/**
 * Скрипт записи аудио с микрофона через Termux API.
 */

header('Content-Type: application/json; charset=utf-8');

// Длительность записи из CLI-аргументов (по умолчанию 30 сек)
$duration = isset($argv[1]) ? (int)$argv[1] : 30;
if ($duration <= 0) {
    $duration = 30;
}

$time = time();
$audioPath = __DIR__ . '/audio_' . $time . '.m4a';
$termuxBin = '/data/data/com.termux/files/usr/bin/termux-microphone-record';

// Сброс зависших фоновых записей
shell_exec("{$termuxBin} -q 2>&1");
usleep(100000); // 0.1 сек задержка

// Запуск записи аудио
$command = "{$termuxBin} -f " . escapeshellarg($audioPath) . " -l {$duration} -e m4a 2>&1";
$output = shell_exec($command);

// Задержка на запись файла на диск
usleep(300000); // 0.3 сек

if (file_exists($audioPath) && filesize($audioPath) > 0) {
    echo json_encode([
        'success'    => true,
        'audio_path' => $audioPath
    ], JSON_UNESCAPED_UNICODE);
} else {
    if (file_exists($audioPath)) {
        @unlink($audioPath);
    }

    echo json_encode([
        'success' => false,
        'error'   => 'Не удалось записать аудио. Ошибка: ' . trim($output)
    ], JSON_UNESCAPED_UNICODE);
}