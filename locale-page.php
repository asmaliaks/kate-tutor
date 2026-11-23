<?php
$pages = [
    'ru' => [
        'title' => 'Английский по сериалам',
        'description' => 'Изучайте английский по любимым сериалам: живые диалоги, современная лексика, разбор сцен и мини-курсы для уровней A2–B2.',
    ],
    'en' => [
        'title' => 'Learn English with TV Series',
        'description' => 'Learn practical English with TV series through real dialogue, modern vocabulary, scene analysis, and mini-courses for A2–B2 learners.',
    ],
    'pl' => [
        'title' => 'Angielski z seriali',
        'description' => 'Ucz się praktycznego angielskiego z seriali: naturalne dialogi, współczesne słownictwo, analiza scen i mini-kursy dla poziomów A2–B2.',
    ],
];

if (!isset($locale) || !isset($pages[$locale])) {
    http_response_code(404);
    exit;
}

$html = file_get_contents(__DIR__ . '/index.html');
if ($html === false) {
    http_response_code(500);
    exit('Unable to load the page.');
}

$escape = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$title = $escape($pages[$locale]['title']);
$description = $escape($pages[$locale]['description']);
$canonical = 'https://movieclub.edu.pl/' . $locale . '/';
$ogLocales = [
    'ru' => 'ru_RU',
    'en' => 'en_US',
    'pl' => 'pl_PL',
];
$ogLocale = $ogLocales[$locale];

$html = str_replace('<html lang="ru">', '<html lang="' . $locale . '">', $html);
$html = preg_replace('~<title>.*?</title>~s', '<title>' . $title . '</title>', $html, 1);
$html = preg_replace(
    '~<meta name="description" content="[^"]*">~',
    '<meta name="description" content="' . $description . '">',
    $html,
    1
);
$html = preg_replace(
    '~<meta property="og:title" content="[^"]*">~',
    '<meta property="og:title" content="' . $title . '">',
    $html,
    1
);
$html = preg_replace(
    '~<meta property="og:description" content="[^"]*">~',
    '<meta property="og:description" content="' . $description . '">',
    $html,
    1
);
$html = preg_replace(
    '~<meta property="og:url" content="[^"]*">~',
    '<meta property="og:url" content="' . $canonical . '">',
    $html,
    1
);
$html = preg_replace(
    '~<meta property="og:locale" content="[^"]*">~',
    '<meta property="og:locale" content="' . $ogLocale . '">',
    $html,
    1
);
$html = preg_replace(
    '~<link rel="canonical" href="[^"]*">~',
    '<link rel="canonical" href="' . $canonical . '">',
    $html,
    1
);

header('Content-Type: text/html; charset=UTF-8');
echo $html;
