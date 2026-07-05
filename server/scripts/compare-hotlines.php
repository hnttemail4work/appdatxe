<?php

foreach (['/', '/gioi-thieu', '/login'] as $path) {
    $html = file_get_contents('http://127.0.0.1:8000' . $path);
    preg_match_all('/tel:([0-9+]+)/', (string) $html, $tel);
    preg_match_all('/Tổng đài:?\s*([^<\n]+)/u', (string) $html, $text);
    echo $path . PHP_EOL;
    echo '  tel: ' . implode(', ', array_unique($tel[1] ?? [])) . PHP_EOL;
    echo '  text: ' . implode(' | ', array_map('trim', array_unique($text[1] ?? []))) . PHP_EOL;
}
