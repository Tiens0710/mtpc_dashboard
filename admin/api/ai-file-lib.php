<?php
/* Bounded document extraction and generated downloads; PHP 5.6 compatible. */
function mtpc_file_xml($xml) {
    if (!is_string($xml) || $xml === '') throw new Exception('Nội dung XML trống hoặc không đọc được.');
    if (stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) throw new Exception('XML chứa khai báo không được hỗ trợ.');
    $previous = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML($xml, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    if (!$ok) throw new Exception('Nội dung tài liệu bị hỏng.');
    return $doc;
}
function mtpc_file_zip_text($path, $ext) {
    if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) throw new Exception('Hosting cần bật ZipArchive và DOM để đọc Office. Bạn có thể xuất PDF rồi gửi lại.');
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new Exception('Không mở được tài liệu Office.');
    try {
        if ($zip->numFiles > 2000) throw new Exception('Tài liệu có quá nhiều thành phần.');
        $total = 0; $names = array();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i); $name = $stat['name'];
            $match = ($ext === 'docx' && $name === 'word/document.xml') ||
                ($ext === 'pptx' && preg_match('~^ppt/slides/slide[0-9]+\.xml$~', $name)) ||
                ($ext === 'xlsx' && ($name === 'xl/sharedStrings.xml' || preg_match('~^xl/worksheets/sheet[0-9]+\.xml$~', $name)));
            if ($match) { $total += $stat['size']; $names[] = $name; }
        }
        if (!$names || $total > 10 * 1024 * 1024) throw new Exception('Nội dung Office trống hoặc quá lớn. Hãy chia nhỏ file.');
        natsort($names); $shared = array(); $out = array();
        if ($ext === 'xlsx' && in_array('xl/sharedStrings.xml', $names, true)) {
            $doc = mtpc_file_xml($zip->getFromName('xl/sharedStrings.xml'));
            foreach ($doc->getElementsByTagName('si') as $node) $shared[] = $node->textContent;
        }
        foreach ($names as $name) {
            if ($name === 'xl/sharedStrings.xml') continue;
            $doc = mtpc_file_xml($zip->getFromName($name)); $xp = new DOMXPath($doc);
            $out[] = '[' . $name . ']';
            if ($ext === 'xlsx') {
                foreach ($xp->query('//*[local-name()="row"]') as $row) {
                    $cells = array();
                    foreach ($xp->query('./*[local-name()="c"]', $row) as $cell) {
                        $value = $xp->evaluate('string(./*[local-name()="v"])', $cell);
                        if ($cell->getAttribute('t') === 's') $value = isset($shared[(int)$value]) ? $shared[(int)$value] : '';
                        if ($cell->getAttribute('t') === 'inlineStr') $value = $cell->textContent;
                        $formula = $xp->evaluate('string(./*[local-name()="f"])', $cell);
                        $cells[] = $cell->getAttribute('r') . ': ' . $value . ($formula !== '' ? ' [formula: ' . $formula . ']' : '');
                    }
                    $out[] = implode(' | ', $cells);
                }
            } else {
                foreach ($xp->query('//*[local-name()="p"]') as $paragraph) $out[] = $paragraph->textContent;
            }
        }
        $text = implode("\n", $out);
        if (strlen($text) > 800000) throw new Exception('Quá nhiều nội dung để xử lý một lần. Hãy chia nhỏ file.');
        $zip->close();
        return $text;
    } catch (Exception $e) { $zip->close(); throw $e; }
}
function mtpc_file_part($path, $name) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($ext, array('docx', 'xlsx', 'pptx'), true)) return array('text' => mtpc_file_zip_text($path, $ext));
    $bytes = file_get_contents($path);
    if ($bytes === false || strlen($bytes) > 10 * 1024 * 1024) throw new Exception('File tối đa 10 MB cho xử lý AI.');
    if ($ext === 'pdf') {
        if (substr($bytes, 0, 5) !== '%PDF-') throw new Exception('File không phải PDF hợp lệ.');
        return array('inlineData' => array('mimeType'=>'application/pdf', 'data'=>base64_encode($bytes)));
    }
    if (in_array($ext, array('png','jpg','jpeg','webp'), true)) {
        $info = @getimagesize($path);
        if (!$info || !in_array($info['mime'], array('image/png','image/jpeg','image/webp'), true)) throw new Exception('Ảnh không hợp lệ.');
        return array('inlineData'=>array('mimeType'=>$info['mime'], 'data'=>base64_encode($bytes)));
    }
    if (!in_array($ext, array('txt','md','csv','html'), true)) throw new Exception('Chưa đọc được định dạng này. Hãy chuyển DOC/PPT/XLS cũ sang DOCX/PPTX/XLSX hoặc PDF.');
    if (strlen($bytes) > 800000 || strpos($bytes, "\0") !== false || !preg_match('//u', $bytes)) throw new Exception('Văn bản cần là UTF-8, tối đa 800 KB.');
    return array('text'=>$bytes);
}
function mtpc_file_artifact($content, $format) {
    if (!in_array($format, array('txt','md','csv','docx'), true)) throw new Exception('Định dạng đầu ra không được hỗ trợ.');
    if (!is_string($content) || trim($content) === '' || strlen($content) > 600000) throw new Exception('Nội dung file đầu ra rỗng hoặc quá lớn.');
    if ($format === 'csv') {
        $source = fopen('php://temp', 'r+'); $dest = fopen('php://temp', 'r+'); fwrite($source, $content); rewind($source);
        while (($row = fgetcsv($source)) !== false) {
            foreach ($row as &$cell) if (preg_match('/^[\s]*[=+@-]/u', (string)$cell)) $cell = "'" . $cell;
            unset($cell); fputcsv($dest, $row);
        }
        rewind($dest); $content = "\xEF\xBB\xBF" . stream_get_contents($dest); fclose($source); fclose($dest);
    }
    if ($format !== 'docx') return array('bytes'=>$content, 'mime'=>$format === 'csv' ? 'text/csv' : 'text/plain');
    if (!class_exists('ZipArchive')) throw new Exception('Hosting chưa bật ZipArchive để tạo Word. Hãy chọn TXT hoặc Markdown.');
    $temp = tempnam(sys_get_temp_dir(), 'mtpc-doc-');
    if ($temp === false) throw new Exception('Không tạo được file tạm.');
    $zip = new ZipArchive();
    try {
        if ($zip->open($temp, ZipArchive::OVERWRITE) !== true) throw new Exception('Không tạo được file Word.');
        $body = '';
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) $body .= '<w:p><w:r><w:t xml:space="preserve">' . htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</w:t></w:r></w:p>';
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>');
        if (!$zip->close()) throw new Exception('Không ghi được file Word.');
        $bytes = file_get_contents($temp); unlink($temp);
        if ($bytes === false) throw new Exception('Không đọc được file Word đã tạo.');
        return array('bytes'=>$bytes, 'mime'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    } catch (Exception $e) { if (is_file($temp)) @unlink($temp); throw $e; }
}
