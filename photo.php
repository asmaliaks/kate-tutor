<?php
/**
 * Скрипт создания снимка с выбранной камеры Termux (с прогревом сенсора).
 */

header('Content-Type: application/json; charset=utf-8');

// Получаем ID камеры из CLI (0 - задняя, 1 - передняя)
$cameraId = isset($argv[1]) ? (int)$argv[1] : 0;

$time = time();
$photoPath = __DIR__ . '/photo_' . $time . '.jpg';
$dummyPath = __DIR__ . '/dummy_' . $time . '.jpg';
$termuxBin = '/data/data/com.termux/files/usr/bin/termux-camera-photo';

// 1. Делаем 2 холостых снимка для адаптации автоэкспозиции и баланса белого
for ($i = 0; $i < 2; $i++) {
    shell_exec("timeout 5 {$termuxBin} -c {$cameraId} " . escapeshellarg($dummyPath) . " 2>&1");

    // Сразу удаляем временный холостой файл
    if (file_exists($dummyPath)) {
        @unlink($dummyPath);
    }

    usleep(200000); // Задержка 0.2 сек между кадрами
}

// 2. Делаем основной снимок с готовыми настройками
$command = "timeout 10 {$termuxBin} -c {$cameraId} " . escapeshellarg($photoPath) . " 2>&1";
$output = shell_exec($command);

// 3. Формируем ответ
if (file_exists($photoPath) && filesize($photoPath) > 0) {
    echo json_encode([
        'success'    => true,
        'photo_path' => $photoPath
    ], JSON_UNESCAPED_UNICODE);
} else {
    // Если создался поврежденный/пустой файл при ошибке — удаляем его
    if (file_exists($photoPath)) {
        @unlink($photoPath);
    }

    echo json_encode([
        'success' => false,
        'error'   => 'Не атрымалася зрабіць фота. Памылка: ' . trim($output)
    ], JSON_UNESCAPED_UNICODE);
}