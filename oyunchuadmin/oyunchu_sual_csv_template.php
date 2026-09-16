<?php
require_once __DIR__ . '/oyunchu_sual_config.php';
requireAdmin();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sual_import_numune.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // Excel-in UTF-8-i düzgün oxuması üçün BOM
fputcsv($out, ['question_az', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'category', 'difficulty', 'points']);
fputcsv($out, ['Azərbaycanın paytaxtı hansıdır?', 'Bakı', 'Gəncə', 'Sumqayıt', 'Şəki', 'a', 'coğrafiya', 1, 10]);
fputcsv($out, ['Su hansı kimyəvi formuladır?', 'CO2', 'H2O', 'O2', 'NaCl', 'b', 'elm', 1, 10]);
fputcsv($out, ['Ən böyük planet hansıdır?', 'Yer', 'Mars', 'Yupiter', 'Venera', 'c', 'elm', 2, 10]);
fclose($out);
exit;
