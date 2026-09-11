<?php
/**
 * Скрипт создания снимка с выбранной камеры Termux.
 */

header('Content-Type: application/json; charset=utf-8');

// Получаем ID камеры из CLI (0 - задняя, 1 - передняя)
$cameraId = isset($argv[1]) ? (int)$argv[1] : 0;

$photoPath = __DIR__ . '/photo_' . time() . '.jpg';
$termuxBin = '/data/data/com.termux/files/usr/bin/termux-camera-photo';

$command = "timeout 10 {$termuxBin} -c {$cameraId} " . escapeshellarg($photoPath) . " 2>&1";
$output = shell_exec($command);

if (file_exists($photoPath) && filesize($photoPath) > 0) {
    echo json_encode([
        'success'    => true,
        'photo_path' => $photoPath
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'error'   => 'Не удалось сделать снимок. Ошибка: ' . trim($output)
    ], JSON_UNESCAPED_UNICODE);
}