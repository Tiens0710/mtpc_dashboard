<?php
require __DIR__ . '/../admin/api/ai-file-lib.php';
function check($condition, $message) { if (!$condition) throw new Exception($message); }
function rejects($callback) { try { $callback(); } catch (Exception $e) { return; } throw new Exception('Expected rejection'); }
$temp = tempnam(sys_get_temp_dir(), 'mtpc-test-');
try {
    file_put_contents($temp, 'Tiếng Việt <script> không chạy');
    check(mtpc_file_part($temp, 'test.txt')['text'] === 'Tiếng Việt <script> không chạy', 'UTF8 extraction');
    rejects(function () use ($temp) { mtpc_file_part($temp, 'test.exe'); });
    rejects(function () use ($temp) { mtpc_file_part($temp, 'test.pdf'); });
    rejects(function () { mtpc_file_xml('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>'); });
    rejects(function () { mtpc_file_xml(false); });
    rejects(function () { mtpc_file_artifact('', 'txt'); });
    rejects(function () { mtpc_file_artifact('text', 'html'); });
    $csv = mtpc_file_artifact("name,value\nAlice,=1+1\nBob,+CMD\n", 'csv');
    check(strpos($csv['bytes'], "'=1+1") !== false, 'CSV formula escaping');
    if (!class_exists('ZipArchive')) throw new Exception('Run with PHP ZIP extension enabled');
    $word = mtpc_file_artifact("Tiếng Việt & <test>\nDòng hai", 'docx');
    file_put_contents($temp, $word['bytes']);
    check(strpos(mtpc_file_part($temp, 'result.docx')['text'], "Tiếng Việt & <test>\nDòng hai") !== false, 'DOCX round trip');
    $zip = new ZipArchive(); $zip->open($temp, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/sharedStrings.xml', '<sst><si><t>Xin chào</t></si></sst>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData><row><c r="A1" t="s"><v>0</v></c><c r="B1"><v>42</v></c></row></sheetData></worksheet>');
    $zip->close();
    check(strpos(mtpc_file_part($temp, 'test.xlsx')['text'], 'A1: Xin chào | B1: 42') !== false, 'XLSX extraction');
    $zip->open($temp, ZipArchive::OVERWRITE);
    $zip->addFromString('ppt/slides/slide1.xml', '<slide><p><r><t>Slide one</t></r></p></slide>'); $zip->close();
    check(strpos(mtpc_file_part($temp, 'test.pptx')['text'], 'Slide one') !== false, 'PPTX extraction');
    $zip->open($temp, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', str_repeat('x', 11 * 1024 * 1024)); $zip->close();
    rejects(function () use ($temp) { mtpc_file_part($temp, 'large.docx'); });
    echo "PASS: extraction, Word output, CSV safety, invalid files, XML entities, ZIP size limit\n";
} finally { unlink($temp); }
