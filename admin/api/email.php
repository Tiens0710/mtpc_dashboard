<?php
/* MTPC Admin email gateway. PHP 5.6 compatible. */
namespace MTPCAdminEmail;
use Exception;
use DateTime;
use DateTimeZone;

ini_set('display_errors','0');
register_shutdown_function(function(){
  $e=error_get_last();
  if(!$e||!in_array($e['type'],array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR),true))return;
  error_log('[MTPC_EMAIL_FATAL] '.$e['message'].' in '.$e['file'].':'.$e['line']);
  if(!headers_sent()){
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode(array('ok'=>false,'error'=>'PHP gặp lỗi khi xử lý hộp thư. Hãy kiểm tra error_log trên cPanel.','code'=>'EMAIL_PHP_FATAL'),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
});
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
date_default_timezone_set('Asia/Ho_Chi_Minh');

function out($status,$data){http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
function input(){ $x=json_decode(file_get_contents('php://input'),true);return is_array($x)?$x:array(); }
function cut($s,$n){return function_exists('mb_substr')?mb_substr($s,0,$n,'UTF-8'):substr($s,0,$n);}
function has_text($s,$q){$q=trim((string)$q);if($q==='')return true;return function_exists('mb_stripos')?mb_stripos((string)$s,$q,0,'UTF-8')!==false:stripos((string)$s,$q)!==false;}
function clean_line($s){return trim(preg_replace('/[\r\n]+/',' ',(string)$s));}
function drafts_path(){return '/home/mtpc/private/mtpc-admin/email-drafts.json';}
function load_drafts(){$p=drafts_path();if(!is_file($p))return array();$x=json_decode(@file_get_contents($p),true);return is_array($x)?$x:array();}
function save_drafts($x){$p=drafts_path();$d=dirname($p);if(!is_dir($d)&&!@mkdir($d,0750,true))return false;$t=$p.'.tmp';if(@file_put_contents($t,json_encode(array_values($x),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)===false)return false;@chmod($t,0600);if(!@rename($t,$p)){@unlink($t);return false;}@chmod($p,0600);return true;}
function smtp_read($s){$r='';while(($l=fgets($s,1024))!==false){$r.=$l;if(strlen($l)<4||$l[3]===' ')break;}return $r;}
function smtp_cmd($s,$c,$codes){if($c!==null)fwrite($s,$c."\r\n");$r=smtp_read($s);$n=(int)substr($r,0,3);if(!in_array($n,$codes,true))throw new Exception('SMTP từ chối yêu cầu (mã '.$n.').');}
function smtp_send($draft,$cfg){
  $prefix=$cfg['encryption']==='ssl'?'ssl://':'tcp://';$s=@stream_socket_client($prefix.$cfg['host'].':'.$cfg['port'],$errno,$errstr,20,STREAM_CLIENT_CONNECT);if(!$s)throw new Exception('Không kết nối được SMTP.');stream_set_timeout($s,20);
  try{smtp_cmd($s,null,array(220));smtp_cmd($s,'EHLO admin.mtpc.edu.vn',array(250));if($cfg['encryption']==='tls'){smtp_cmd($s,'STARTTLS',array(220));if(!stream_socket_enable_crypto($s,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new Exception('Không bật được TLS.');smtp_cmd($s,'EHLO admin.mtpc.edu.vn',array(250));}smtp_cmd($s,'AUTH LOGIN',array(334));smtp_cmd($s,base64_encode($cfg['username']),array(334));smtp_cmd($s,base64_encode($cfg['password']),array(235));smtp_cmd($s,'MAIL FROM:<'.$cfg['username'].'>',array(250));smtp_cmd($s,'RCPT TO:<'.$draft['to'].'>',array(250,251));smtp_cmd($s,'DATA',array(354));$h=array('From: MTPC <'.$cfg['username'].'>','To: <'.$draft['to'].'>','Subject: =?UTF-8?B?'.base64_encode($draft['subject']).'?=','Date: '.date(DATE_RFC2822),'MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64');$m=implode("\r\n",$h)."\r\n\r\n".chunk_split(base64_encode($draft['message']),76,"\r\n");fwrite($s,preg_replace('/(?m)^\./','..',$m)."\r\n.\r\n");smtp_cmd($s,null,array(250));smtp_cmd($s,'QUIT',array(221));fclose($s);}catch(Exception $e){fclose($s);throw $e;}
}

if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')out(405,array('ok'=>false,'error'=>'Chỉ hỗ trợ POST.'));
if(!empty($_SERVER['HTTP_ORIGIN'])){$oh=parse_url($_SERVER['HTTP_ORIGIN'],PHP_URL_HOST);$rh=isset($_SERVER['HTTP_HOST'])?preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']):'';if(!$oh||strcasecmp($oh,$rh)!==0)out(403,array('ok'=>false,'error'=>'Nguồn yêu cầu không hợp lệ.'));}
$config='/home/mtpc/private/email-config.php';if(!is_file($config))out(503,array('ok'=>false,'error'=>'Chưa cấu hình hộp thư.'));require $config;
$auth=isset($MTPC_EMAIL_REQUIRE_AUTH)?(bool)$MTPC_EMAIL_REQUIRE_AUTH:true;if($auth&&empty($_SERVER['REMOTE_USER']))out(403,array('ok'=>false,'error'=>'Hãy bật Directory Privacy cho admin.'));
$body=input();$action=isset($_GET['action'])?(string)$_GET['action']:'';$user=isset($MTPC_EMAIL_USERNAME)?trim((string)$MTPC_EMAIL_USERNAME):'';$pass=isset($MTPC_EMAIL_PASSWORD)?(string)$MTPC_EMAIL_PASSWORD:'';
if(stripos($user,'@gmail.com')!==false)$pass=preg_replace('/\s+/u','',$pass);

if($action==='draft'){
  $to=isset($body['to'])?trim((string)$body['to']):'';$subject=clean_line(isset($body['subject'])?$body['subject']:'');$message=trim(isset($body['message'])?(string)$body['message']:'');
  if(!filter_var($to,FILTER_VALIDATE_EMAIL)||$subject===''||$message==='')out(422,array('ok'=>false,'error'=>'Bản nháp cần người nhận, tiêu đề và nội dung hợp lệ.'));if(strlen($message)>50000)out(422,array('ok'=>false,'error'=>'Bản nháp quá dài.'));
  $all=load_drafts();$d=array('id'=>'mail_'.date('YmdHis').'_'.substr(md5(uniqid('',true)),0,8),'to'=>$to,'subject'=>cut($subject,250),'message'=>$message,'original_uid'=>isset($body['original_uid'])?preg_replace('/\D/','',(string)$body['original_uid']):'','status'=>'draft','created_at'=>date('c'),'updated_at'=>date('c'));array_unshift($all,$d);$all=array_slice($all,0,100);if(!save_drafts($all))out(500,array('ok'=>false,'error'=>'Không lưu được bản nháp.'));out(200,array('ok'=>true,'message'=>'Đã lưu bản nháp, chưa gửi.','draft'=>$d));
}
if($action==='drafts'){$all=load_drafts();foreach($all as &$d)unset($d['message']);unset($d);out(200,array('ok'=>true,'count'=>count($all),'drafts'=>$all));}
if($action==='send'){
  $id=isset($body['draft_id'])?trim((string)$body['draft_id']):'';$all=load_drafts();$idx=-1;foreach($all as $i=>$d)if(isset($d['id'])&&$d['id']===$id){$idx=$i;break;}if($idx<0)out(404,array('ok'=>false,'error'=>'Không tìm thấy bản nháp.'));if(isset($all[$idx]['status'])&&$all[$idx]['status']==='sent')out(409,array('ok'=>false,'error'=>'Email này đã gửi.'));
  $cfg=array('host'=>isset($MTPC_EMAIL_SMTP_HOST)?trim((string)$MTPC_EMAIL_SMTP_HOST):'smtp.gmail.com','port'=>isset($MTPC_EMAIL_SMTP_PORT)?(int)$MTPC_EMAIL_SMTP_PORT:465,'encryption'=>isset($MTPC_EMAIL_SMTP_ENCRYPTION)?strtolower(trim((string)$MTPC_EMAIL_SMTP_ENCRYPTION)):'ssl','username'=>$user,'password'=>$pass);if(!filter_var($user,FILTER_VALIDATE_EMAIL)||$pass===''||!in_array($cfg['encryption'],array('ssl','tls','none'),true))out(503,array('ok'=>false,'error'=>'Cấu hình SMTP chưa hợp lệ.'));try{smtp_send($all[$idx],$cfg);}catch(Exception $e){out(502,array('ok'=>false,'error'=>$e->getMessage()));}$all[$idx]['status']='sent';$all[$idx]['sent_at']=date('c');$all[$idx]['updated_at']=date('c');if(!save_drafts($all))out(500,array('ok'=>false,'error'=>'Đã gửi nhưng chưa cập nhật được lịch sử.'));out(200,array('ok'=>true,'message'=>'Email đã được gửi sau khi phê duyệt.','draft'=>array('id'=>$id,'to'=>$all[$idx]['to'],'subject'=>$all[$idx]['subject'],'status'=>'sent','sent_at'=>$all[$idx]['sent_at'])));
}

if(!function_exists('imap_open'))out(503,array('ok'=>false,'error'=>'PHP IMAP chưa được bật.'));
$host=isset($MTPC_EMAIL_IMAP_HOST)?trim((string)$MTPC_EMAIL_IMAP_HOST):'';$port=isset($MTPC_EMAIL_IMAP_PORT)?(int)$MTPC_EMAIL_IMAP_PORT:993;$enc=isset($MTPC_EMAIL_IMAP_ENCRYPTION)?strtolower(trim((string)$MTPC_EMAIL_IMAP_ENCRYPTION)):'ssl';$folder=isset($MTPC_EMAIL_FOLDER)?trim((string)$MTPC_EMAIL_FOLDER):'INBOX';$cert=isset($MTPC_EMAIL_VALIDATE_CERT)?(bool)$MTPC_EMAIL_VALIDATE_CERT:true;
if($host===''||$user===''||$pass==='')out(503,array('ok'=>false,'error'=>'Cấu hình IMAP còn thiếu.'));if(!preg_match('/^[a-z0-9.-]+$/i',$host)||$port<1||$port>65535||!in_array($enc,array('ssl','tls','none'),true))out(500,array('ok'=>false,'error'=>'Cấu hình IMAP không hợp lệ.'));
$flags='/imap'.($enc==='ssl'?'/ssl':'').($enc==='tls'?'/tls':'').(!$cert?'/novalidate-cert':'');if(function_exists('imap_timeout')){@imap_timeout(IMAP_OPENTIMEOUT,12);}@imap_errors();$imap=@imap_open('{'.$host.':'.$port.$flags.'}'.$folder,$user,$pass,OP_READONLY,1);if(!$imap){$ie=function_exists('imap_last_error')?(string)@imap_last_error():'';if($ie!=='')error_log('[MTPC_EMAIL_IMAP] '.$ie);$msg=stripos($ie,'auth')!==false||stripos($ie,'credential')!==false||stripos($ie,'password')!==false?'Gmail từ chối đăng nhập IMAP. Hãy kiểm tra tài khoản và mật khẩu ứng dụng.':'Không kết nối được máy chủ IMAP. Hãy kiểm tra PHP IMAP và kết nối ra cổng 993 trên hosting.';out(502,array('ok'=>false,'error'=>$msg,'code'=>'IMAP_CONNECTION_FAILED'));}
function mail_utf8($s){$parts=function_exists('imap_mime_header_decode')?@imap_mime_header_decode((string)$s):false;if(!is_array($parts))return trim((string)$s);$r='';foreach($parts as $p){$c=isset($p->charset)?strtoupper($p->charset):'DEFAULT';$t=isset($p->text)?$p->text:'';if($c!=='DEFAULT'&&$c!=='UTF-8'&&function_exists('iconv')){$x=@iconv($c,'UTF-8//IGNORE',$t);if($x!==false)$t=$x;}$r.=$t;}return trim($r);}
function charset_of($p){$g=array();if(isset($p->parameters)&&is_array($p->parameters))$g[]=$p->parameters;if(isset($p->dparameters)&&is_array($p->dparameters))$g[]=$p->dparameters;foreach($g as $a)foreach($a as $x)if(isset($x->attribute,$x->value)&&strtoupper($x->attribute)==='CHARSET')return(string)$x->value;return'UTF-8';}
function decode_part($raw,$encoding,$charset,$html){if((int)$encoding===3)$raw=base64_decode($raw);elseif((int)$encoding===4)$raw=quoted_printable_decode($raw);if($charset&&strtoupper($charset)!=='UTF-8'&&strtoupper($charset)!=='US-ASCII'&&function_exists('iconv')){$x=@iconv($charset,'UTF-8//IGNORE',$raw);if($x!==false)$raw=$x;}if($html){$raw=preg_replace('/<script\b[^>]*>.*?<\/script>/is',' ',$raw);$raw=preg_replace('/<style\b[^>]*>.*?<\/style>/is',' ',$raw);$raw=html_entity_decode(strip_tags($raw),ENT_QUOTES,'UTF-8');}return trim(preg_replace('/\r?\n(?:\s*\r?\n)+/',"\n\n",preg_replace('/[\t ]+/',' ',$raw)));}
function collect_parts($s,$prefix,&$plain,&$html){if(isset($s->parts)&&is_array($s->parts)){foreach($s->parts as $i=>$c)collect_parts($c,$prefix===''?(string)($i+1):$prefix.'.'.($i+1),$plain,$html);return;}if(isset($s->type)&&(int)$s->type===0){$sub=isset($s->subtype)?strtoupper($s->subtype):'PLAIN';$x=array('part'=>$prefix,'encoding'=>isset($s->encoding)?(int)$s->encoding:0,'charset'=>charset_of($s));if($sub==='PLAIN')$plain[]=$x;elseif($sub==='HTML')$html[]=$x;}}
function extract_text($imap,$uid){$s=@imap_fetchstructure($imap,$uid,FT_UID);if(!$s)return'';$p=array();$h=array();collect_parts($s,'',$p,$h);$c=count($p)?$p:$h;foreach($c as $x){$raw=$x['part']===''?@imap_body($imap,$uid,FT_UID|FT_PEEK):@imap_fetchbody($imap,$uid,$x['part'],FT_UID|FT_PEEK);if($raw!==false&&$raw!=='')return decode_part($raw,$x['encoding'],$x['charset'],!count($p));}$raw=@imap_body($imap,$uid,FT_UID|FT_PEEK);return$raw===false?'':decode_part($raw,isset($s->encoding)?$s->encoding:0,charset_of($s),isset($s->subtype)&&strtoupper($s->subtype)==='HTML');}
function valid_date($v){if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$v))return false;$d=DateTime::createFromFormat('!Y-m-d',$v,new DateTimeZone('Asia/Ho_Chi_Minh'));return$d&&$d->format('Y-m-d')===$v?$d:false;}
function date_range($b){
  $z=new DateTimeZone('Asia/Ho_Chi_Minh');
  $t=new DateTime('today',$z);
  $m=isset($b['date_mode'])?(string)$b['date_mode']:'today';
  if($m==='today'){
    $from=clone $t;$to=clone $t;$to->modify('+1 day');
    return array($from,$to,'hôm nay');
  }
  if($m==='yesterday'){
    $from=clone $t;$from->modify('-1 day');$to=clone $t;
    return array($from,$to,'hôm qua');
  }
  if($m==='recent'){
    $from=clone $t;$from->modify('-6 days');$to=clone $t;$to->modify('+1 day');
    return array($from,$to,'trong 7 ngày gần đây');
  }
  if($m==='date'){
    $d=valid_date(isset($b['date'])?$b['date']:'');
    if(!$d)return false;
    $to=clone $d;$to->modify('+1 day');
    return array($d,$to,'ngày '.$d->format('d/m/Y'));
  }
  if($m==='range'){
    $f=valid_date(isset($b['from_date'])?$b['from_date']:'');
    $to=valid_date(isset($b['to_date'])?$b['to_date']:'');
    if(!$f||!$to||$f>$to||(int)$f->diff($to)->format('%a')>31)return false;
    $end=clone $to;$end->modify('+1 day');
    return array($f,$end,'từ '.$f->format('d/m/Y').' đến '.$to->format('d/m/Y'));
  }
  return false;
}
function priority_hint($s){foreach(array('khẩn','gấp','hạn chót','xét tuyển','khiếu nại','thanh toán','xác nhận','hồ sơ')as$w)if(has_text($s,$w))return'high';return'normal';}

if($action==='list'){
  $range=date_range($body);if(!$range){imap_close($imap);out(422,array('ok'=>false,'error'=>'Khoảng ngày không hợp lệ hoặc dài quá 31 ngày.'));}$limit=isset($body['limit'])?max(1,min(20,(int)$body['limit'])):10;$criteria='SINCE "'.$range[0]->format('d-M-Y').'" BEFORE "'.$range[1]->format('d-M-Y').'"'.(!empty($body['unread_only'])?' UNSEEN':'');$uids=@imap_search($imap,$criteria,SE_UID,'UTF-8');if(!is_array($uids))$uids=array();$sender=isset($body['sender'])?trim((string)$body['sender']):'';$sf=isset($body['subject'])?trim((string)$body['subject']):'';$q=isset($body['query'])?trim((string)$body['query']):'';$items=array();
  foreach($uids as$uid){$o=@imap_fetch_overview($imap,(string)$uid,FT_UID);if(!is_array($o)||!isset($o[0]))continue;$row=$o[0];$from=mail_utf8(isset($row->from)?$row->from:'');$sub=mail_utf8(isset($row->subject)?$row->subject:'(Không có tiêu đề)');if(!has_text($from,$sender)||!has_text($sub,$sf))continue;$ts=isset($row->udate)?(int)$row->udate:(isset($row->date)?strtotime($row->date):0);$items[]=array('uid'=>(string)$uid,'from'=>$from,'subject'=>$sub,'date'=>$ts?date('c',$ts):'','unread'=>empty($row->seen),'_ts'=>$ts);}
  usort($items,function($a,$b){return$a['_ts']===$b['_ts']?0:($a['_ts']<$b['_ts']?1:-1);});$result=array();foreach($items as$x){$preview=cut(preg_replace('/\s+/',' ',extract_text($imap,$x['uid'])),500);if($q!==''&&!has_text($x['from'].' '.$x['subject'].' '.$preview,$q))continue;$x['preview']=$preview;$x['priority']=priority_hint($x['subject'].' '.$preview);unset($x['_ts']);$result[]=$x;if(count($result)>=$limit)break;}imap_close($imap);out(200,array('ok'=>true,'range_label'=>$range[2],'count'=>count($result),'emails'=>$result,'filters'=>array('sender'=>$sender,'subject'=>$sf,'query'=>$q)));
}
if($action==='read'){$uid=isset($body['uid'])?trim((string)$body['uid']):'';if(!preg_match('/^\d+$/',$uid)){imap_close($imap);out(422,array('ok'=>false,'error'=>'UID không hợp lệ.'));}$o=@imap_fetch_overview($imap,$uid,FT_UID);if(!is_array($o)||!isset($o[0])){imap_close($imap);out(404,array('ok'=>false,'error'=>'Không tìm thấy email.'));}$r=$o[0];$ts=isset($r->udate)?(int)$r->udate:(isset($r->date)?strtotime($r->date):0);$e=array('uid'=>$uid,'from'=>mail_utf8(isset($r->from)?$r->from:''),'to'=>mail_utf8(isset($r->to)?$r->to:''),'subject'=>mail_utf8(isset($r->subject)?$r->subject:'(Không có tiêu đề)'),'date'=>$ts?date('c',$ts):'','unread'=>empty($r->seen),'body'=>cut(extract_text($imap,$uid),16000));imap_close($imap);out(200,array('ok'=>true,'email'=>$e));}
imap_close($imap);out(400,array('ok'=>false,'error'=>'Thao tác email không hợp lệ.'));
