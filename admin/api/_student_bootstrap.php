<?php
/* Shared DB, role and audit bootstrap. PHP 5.6 compatible. */
$mtpcConfigPath='/home/mtpc/private/db-config.php';
if(!is_file($mtpcConfigPath))mtpc_students_response(500,array('ok'=>false,'error'=>'Chưa tìm thấy db-config.php.'));
require $mtpcConfigPath;
if(!isset($MTPC_DB_HOST,$MTPC_DB_NAME,$MTPC_DB_USER,$MTPC_DB_PASS))mtpc_students_response(500,array('ok'=>false,'error'=>'Cấu hình database chưa đủ.'));
try{$pdo=new PDO('mysql:host='.$MTPC_DB_HOST.';dbname='.$MTPC_DB_NAME.';charset=utf8mb4',$MTPC_DB_USER,$MTPC_DB_PASS,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC));}catch(Exception $e){mtpc_students_response(500,array('ok'=>false,'error'=>'Không thể kết nối database.'));}
$mtpcUsername=isset($_SERVER['REMOTE_USER'])?trim((string)$_SERVER['REMOTE_USER']):'';
if($mtpcUsername==='')mtpc_students_response(401,array('ok'=>false,'error'=>'Bạn chưa đăng nhập Directory Privacy.'));
try{
  $count=(int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
  if($count===0){$s=$pdo->prepare("INSERT INTO admin_users(username,full_name,role,status) VALUES(:u,:n,'admin','active')");$s->execute(array(':u'=>$mtpcUsername,':n'=>$mtpcUsername));}
  $s=$pdo->prepare("SELECT id,username,full_name,role,status FROM admin_users WHERE username=:u LIMIT 1");$s->execute(array(':u'=>$mtpcUsername));$mtpcActor=$s->fetch();
}catch(Exception $e){mtpc_students_response(503,array('ok'=>false,'error'=>'Chưa chạy database/student_management_v2.sql.'));}
if(!$mtpcActor||$mtpcActor['status']!=='active')mtpc_students_response(403,array('ok'=>false,'error'=>'Tài khoản chưa được cấp quyền hoặc đã bị khóa.'));
function mtpc_has_permission($permission){global $mtpcActor;$map=array('admin'=>array('*'),'training'=>array('students.read','students.write','academic.read','academic.write','attendance.read','attendance.write','finance.read','audit.read','moodle.read','moodle.write'),'teacher'=>array('students.read','academic.read','attendance.read','attendance.write','moodle.read'));$allowed=isset($map[$mtpcActor['role']])?$map[$mtpcActor['role']]:array();return in_array('*',$allowed,true)||in_array($permission,$allowed,true);}
function mtpc_require_permission($permission){if(!mtpc_has_permission($permission))mtpc_students_response(403,array('ok'=>false,'error'=>'Vai trò hiện tại không có quyền thực hiện thao tác này.'));}
function mtpc_audit($action,$entityType,$entityId,$before,$after){global $pdo,$mtpcActor;try{$s=$pdo->prepare('INSERT INTO system_audit_logs(actor_username,actor_role,action,entity_type,entity_id,before_data,after_data,ip_address) VALUES(:u,:r,:a,:t,:i,:b,:n,:ip)');$s->execute(array(':u'=>$mtpcActor['username'],':r'=>$mtpcActor['role'],':a'=>$action,':t'=>$entityType,':i'=>(string)$entityId,':b'=>$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),':n'=>$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),':ip'=>isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:''));}catch(Exception $e){}}
function mtpc_mask($value,$visible){$v=(string)$value;if($v==='')return'';$n=strlen($v);return$n<=$visible?str_repeat('*',$n):str_repeat('*',$n-$visible).substr($v,-$visible);}
