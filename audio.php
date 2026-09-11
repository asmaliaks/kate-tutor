<?php
/**
 * Скрипт записи аудио с микрофона через Termux API (со синхронным ожиданием).
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

// 1. Принудительно останавливаем прошлые записи, если они повисли
shell_exec("{$termuxBin} -q 2>&1");
usleep(200000);

// 2. Стартуем запись (команда отрабатывает асинхронно)
$command = "{$termuxBin} -f " . escapeshellarg($audioPath) . " -l {$duration} -e m4a 2>&1";
shell_exec($command);

// 3. Ждем окончания записи в PHP (длительность + 1 сек на сохранение)
sleep($duration + 1);

// 4. Проверяем готовый файл
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
        'error'   => 'Файл аўдыё не сфарміраваўся ці пусты.'
    ], JSON_UNESCAPED_UNICODE);
}