<?php
/* Authenticated private student photo delivery. PHP 5.6 compatible. */
header('X-Content-Type-Options: nosniff');
function mtpc_students_response($status,$payload){http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
require __DIR__.'/_student_bootstrap.php';
mtpc_require_permission('students.read');
$id=isset($_GET['student_id'])?(int)$_GET['student_id']:0;
if($id<=0)mtpc_students_response(400,array('ok'=>false,'error'=>'Thiếu sinh viên.'));
$st=$pdo->prepare('SELECT photo_path FROM students WHERE id=:id LIMIT 1');$st->execute(array(':id'=>$id));$relative=(string)$st->fetchColumn();
if($relative===''||strpos($relative,'photos/')!==0)mtpc_students_response(404,array('ok'=>false,'error'=>'Sinh viên chưa có ảnh.'));
$root='/home/mtpc/private/student-files/';$path=$root.$relative;$real=realpath($path);$safeRoot=realpath($root);
if(!$real||!$safeRoot||strpos($real,$safeRoot.DIRECTORY_SEPARATOR)!==0||!is_file($real))mtpc_students_response(404,array('ok'=>false,'error'=>'Không tìm thấy ảnh.'));
$info=@getimagesize($real);if(!$info||!in_array($info['mime'],array('image/jpeg','image/png','image/webp'),true))mtpc_students_response(415,array('ok'=>false,'error'=>'Định dạng ảnh không hợp lệ.'));
header('Content-Type: '.$info['mime']);header('Content-Length: '.filesize($real));header('Cache-Control: private, max-age=300');readfile($real);
