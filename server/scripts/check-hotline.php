<?php
$html = file_get_contents('http://127.0.0.1:8000/gioi-thieu');
if (preg_match('/Tổng đài.*?<\/p>/s', $html, $m)) {
    echo strip_tags($m[0]) . PHP_EOL;
}
if (preg_match('/app-footer-text.*?<\/p>/s', $html, $m)) {
    echo 'footer: ' . strip_tags($m[0]) . PHP_EOL;
}
