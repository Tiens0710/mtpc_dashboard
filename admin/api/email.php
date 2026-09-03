<?php
/* MTPC Admin email gateway. PHP 5.6 compatible. */
namespace MTPCAdminEmail;
use Exception;
use DateTime;
use DateTimeZone;

ini_set('display_errors','0');
header('X-MTPC-Email-Api-Version: 2026-09-03.2');
register_shutdown_function(function(){
  $e=error_get_last();
  if(!$e||!in_array($e['type'],array(E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR),true))return;
  error_log('[MTPC_EMAIL_FATAL] '.$e['message'].' in '.$e['file'].':'.$e['line']);
  $detail=basename($e['file']).':'.$e['line'].' - '.preg_replace('/[\r\n]+/',' ',(string)$e['message']);
  if(!headers_sent()){
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
  }
  echo json_encode(array('ok'=>false,'error'=>'PHP gặp lỗi khi xử lý hộp thư.','detail'=>$detail,'code'=>'EMAIL_PHP_FATAL'),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
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
function address_only($raw){
  $raw=clean_line($raw);$m=array();
  if(preg_match('/<([^>]+)>/',$raw,$m)&&filter_var(trim($m[1]),FILTER_VALIDATE_EMAIL))return trim($m[1]);
  if(preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',$raw,$m))return trim($m[0]);
  return filter_var($raw,FILTER_VALIDATE_EMAIL)?$raw:'';
}
function smtp_read($s){$r='';while(($l=fgets($s,1024))!==false){$r.=$l;if(strlen($l)<4||$l[3]===' ')break;}return $r;}
function smtp_cmd($s,$c,$codes){if($c!==null)fwrite($s,$c."\r\n");$r=smtp_read($s);$n=(int)substr($r,0,3);if(!in_array($n,$codes,true))throw new Exception('SMTP từ chối yêu cầu (mã '.$n.').');}
function smtp_send($draft,$cfg){
  $prefix=$cfg['encryption']==='ssl'?'ssl://':'tcp://';$s=@stream_socket_client($prefix.$cfg['host'].':'.$cfg['port'],$errno,$errstr,20,STREAM_CLIENT_CONNECT);if(!$s)throw new Exception('Không kết nối được SMTP.');stream_set_timeout($s,20);
  try{smtp_cmd($s,null,array(220));smtp_cmd($s,'EHLO admin.mtpc.edu.vn',array(250));if($cfg['encryption']==='tls'){smtp_cmd($s,'STARTTLS',array(220));if(!stream_socket_enable_crypto($s,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new Exception('Không bật được TLS.');smtp_cmd($s,'EHLO admin.mtpc.edu.vn',array(250));}smtp_cmd($s,'AUTH LOGIN',array(334));smtp_cmd($s,base64_encode($cfg['username']),array(334));smtp_cmd($s,base64_encode($cfg['password']),array(235));smtp_cmd($s,'MAIL FROM:<'.$cfg['username'].'>',array(250));smtp_cmd($s,'RCPT TO:<'.$draft['to'].'>',array(250,251));smtp_cmd($s,'DATA',array(354));$h=array('From: MTPC <'.$cfg['username'].'>','To: <'.$draft['to'].'>','Subject: =?UTF-8?B?'.base64_encode($draft['subject']).'?=','Date: '.date(DATE_RFC2822),'Message-ID: <'.uniqid('mtpc-',true).'@admin.mtpc.edu.vn>','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: base64');if(!empty($draft['in_reply_to']))$h[]='In-Reply-To: '.clean_line($draft['in_reply_to']);if(!empty($draft['references']))$h[]='References: '.clean_line($draft['references']);$m=implode("\r\n",$h)."\r\n\r\n".chunk_split(base64_encode($draft['message']),76,"\r\n");fwrite($s,preg_replace('/(?m)^\./','..',$m)."\r\n.\r\n");smtp_cmd($s,null,array(250));smtp_cmd($s,'QUIT',array(221));fclose($s);}catch(Exception $e){fclose($s);throw $e;}
}

if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST')out(405,array('ok'=>false,'error'=>'Chỉ hỗ trợ POST.'));
if(!empty($_SERVER['HTTP_ORIGIN'])){$oh=parse_url($_SERVER['HTTP_ORIGIN'],PHP_URL_HOST);$rh=isset($_SERVER['HTTP_HOST'])?preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']):'';if(!$oh||strcasecmp($oh,$rh)!==0)out(403,array('ok'=>false,'error'=>'Nguồn yêu cầu không hợp lệ.'));}
$config='/home/mtpc/private/email-config.php';if(!is_file($config))out(503,array('ok'=>false,'error'=>'Chưa cấu hình hộp thư.'));require $config;
/*
 * cPanel/LiteSpeed enforces Directory Privacy before this script executes, but
 * some PHP 5.6 handlers do not expose REMOTE_USER/PHP_AUTH_USER to PHP. Do not
 * reject an already authenticated request solely because those variables are
 * missing. Same-origin POST validation above remains enabled.
 */
$body=input();$action=isset($_GET['action'])?(string)$_GET['action']:'';$user=isset($MTPC_EMAIL_USERNAME)?trim((string)$MTPC_EMAIL_USERNAME):'';$pass=isset($MTPC_EMAIL_PASSWORD)?(string)$MTPC_EMAIL_PASSWORD:'';
if(stripos($user,'@gmail.com')!==false)$pass=preg_replace('/\s+/u','',$pass);
$imapCfg=array('host'=>isset($MTPC_EMAIL_IMAP_HOST)?trim((string)$MTPC_EMAIL_IMAP_HOST):'','port'=>isset($MTPC_EMAIL_IMAP_PORT)?(int)$MTPC_EMAIL_IMAP_PORT:993,'encryption'=>isset($MTPC_EMAIL_IMAP_ENCRYPTION)?strtolower(trim((string)$MTPC_EMAIL_IMAP_ENCRYPTION)):'ssl','username'=>$user,'password'=>$pass,'validate_cert'=>isset($MTPC_EMAIL_VALIDATE_CERT)?(bool)$MTPC_EMAIL_VALIDATE_CERT:true);

if($action==='send-direct'){
  $mail=array('to'=>isset($body['to'])?trim((string)$body['to']):'','subject'=>cut(clean_line(isset($body['subject'])?$body['subject']:''),250),'message'=>trim(isset($body['message'])?(string)$body['message']:''),'in_reply_to'=>isset($body['in_reply_to'])?clean_line($body['in_reply_to']):'','references'=>isset($body['references'])?clean_line($body['references']):'');
  if(!filter_var($mail['to'],FILTER_VALIDATE_EMAIL)||$mail['subject']===''||$mail['message']==='')out(422,array('ok'=>false,'error'=>'Email cần người nhận, tiêu đề và nội dung hợp lệ.'));
  if(strlen($mail['message'])>50000)out(422,array('ok'=>false,'error'=>'Nội dung email quá dài.'));
  $cfg=array('host'=>isset($MTPC_EMAIL_SMTP_HOST)?trim((string)$MTPC_EMAIL_SMTP_HOST):'smtp.gmail.com','port'=>isset($MTPC_EMAIL_SMTP_PORT)?(int)$MTPC_EMAIL_SMTP_PORT:465,'encryption'=>isset($MTPC_EMAIL_SMTP_ENCRYPTION)?strtolower(trim((string)$MTPC_EMAIL_SMTP_ENCRYPTION)):'ssl','username'=>$user,'password'=>$pass);
  if(!filter_var($user,FILTER_VALIDATE_EMAIL)||$pass===''||!in_array($cfg['encryption'],array('ssl','tls','none'),true))out(503,array('ok'=>false,'error'=>'Cấu hình SMTP chưa hợp lệ.'));
  try{smtp_send($mail,$cfg);}catch(Exception $e){out(502,array('ok'=>false,'error'=>$e->getMessage(),'code'=>'SMTP_SEND_FAILED'));}
  out(200,array('ok'=>true,'message'=>'Email đã được gửi.','email'=>array('to'=>$mail['to'],'subject'=>$mail['subject'],'status'=>'sent','sent_at'=>date('c'))));
}

if($action==='templates'){
  $signature=isset($MTPC_EMAIL_SIGNATURE)?trim((string)$MTPC_EMAIL_SIGNATURE):"Trân trọng,\nTrường Trung cấp Miền Tây";
  $templates=array(
    array('id'=>'acknowledgement','name'=>'Xác nhận đã nhận thông tin','subject'=>'Xác nhận đã nhận thông tin','message'=>"Chào {{ten}},\n\nNhà trường đã nhận được thông tin của bạn và sẽ phản hồi trong thời gian sớm nhất.\n\n".$signature),
    array('id'=>'document_request','name'=>'Yêu cầu bổ sung hồ sơ','subject'=>'Đề nghị bổ sung hồ sơ','message'=>"Chào {{ten}},\n\nNhà trường đề nghị bạn bổ sung các giấy tờ còn thiếu: {{giay_to}}. Vui lòng gửi trước ngày {{han_chot}}.\n\n".$signature),
    array('id'=>'tuition_reminder','name'=>'Nhắc học phí','subject'=>'Thông báo học phí cần hoàn tất','message'=>"Chào {{ten}},\n\nNhà trường thông báo khoản học phí còn cần hoàn tất là {{so_tien}}. Hạn thanh toán: {{han_chot}}. Nếu đã thanh toán, vui lòng bỏ qua thông báo này.\n\n".$signature),
    array('id'=>'admissions_consultation','name'=>'Tư vấn tuyển sinh','subject'=>'Thông tin tư vấn tuyển sinh Trường Trung cấp Miền Tây','message'=>"Chào {{ten}},\n\nCảm ơn bạn đã quan tâm đến ngành {{nganh}}. Nhà trường xin gửi thông tin tư vấn: {{noi_dung}}.\n\n".$signature)
  );
  out(200,array('ok'=>true,'templates'=>$templates,'signature'=>$signature));
}

if(!function_exists('imap_open'))out(503,array('ok'=>false,'error'=>'PHP IMAP chưa được bật.'));
$host=isset($MTPC_EMAIL_IMAP_HOST)?trim((string)$MTPC_EMAIL_IMAP_HOST):'';$port=isset($MTPC_EMAIL_IMAP_PORT)?(int)$MTPC_EMAIL_IMAP_PORT:993;$enc=isset($MTPC_EMAIL_IMAP_ENCRYPTION)?strtolower(trim((string)$MTPC_EMAIL_IMAP_ENCRYPTION)):'ssl';$folder=isset($MTPC_EMAIL_FOLDER)?trim((string)$MTPC_EMAIL_FOLDER):'INBOX';$cert=isset($MTPC_EMAIL_VALIDATE_CERT)?(bool)$MTPC_EMAIL_VALIDATE_CERT:true;
if($host===''||$user===''||$pass==='')out(503,array('ok'=>false,'error'=>'Cấu hình IMAP còn thiếu.'));if(!preg_match('/^[a-z0-9.-]+$/i',$host)||$port<1||$port>65535||!in_array($enc,array('ssl','tls','none'),true))out(500,array('ok'=>false,'error'=>'Cấu hình IMAP không hợp lệ.'));
$probeError='';$probeCode=0;$probe=@stream_socket_client('tcp://'.$host.':'.$port,$probeCode,$probeError,6,STREAM_CLIENT_CONNECT);if(!$probe){error_log('[MTPC_EMAIL_PORT] '.$host.':'.$port.' code='.$probeCode.' '.$probeError);out(502,array('ok'=>false,'error'=>'Hosting không kết nối ra được máy chủ Gmail qua cổng 993. Hãy yêu cầu nhà cung cấp hosting mở kết nối IMAP outbound.','code'=>'IMAP_PORT_UNREACHABLE'));}fclose($probe);
$flags='/imap'.($enc==='ssl'?'/ssl':'').($enc==='tls'?'/tls':'').(!$cert?'/novalidate-cert':'');if(function_exists('imap_timeout')){@imap_timeout(IMAP_OPENTIMEOUT,12);}@imap_errors();$openOptions=$action==='manage'?0:OP_READONLY;$imap=@imap_open('{'.$host.':'.$port.$flags.'}'.$folder,$user,$pass,$openOptions,1);if(!$imap){$ie=function_exists('imap_last_error')?(string)@imap_last_error():'';$ies=function_exists('imap_errors')?@imap_errors():false;if($ie===''&&is_array($ies))$ie=implode(' | ',$ies);if($ie!=='')error_log('[MTPC_EMAIL_IMAP] '.$ie);$low=strtolower($ie);if(strpos($low,'auth')!==false||strpos($low,'credential')!==false||strpos($low,'password')!==false||strpos($low,'login')!==false)out(502,array('ok'=>false,'error'=>'Gmail từ chối đăng nhập IMAP. Hãy kiểm tra địa chỉ Gmail, xác minh hai bước và mật khẩu ứng dụng.','code'=>'IMAP_AUTH_FAILED'));if(strpos($low,'certificate')!==false||strpos($low,'peer certificate')!==false||strpos($low,'ssl')!==false)out(502,array('ok'=>false,'error'=>'PHP 5.6 không xác minh được chứng chỉ SSL của Gmail. Hãy cập nhật CA certificate trên hosting hoặc tạm kiểm tra với MTPC_EMAIL_VALIDATE_CERT=false.','code'=>'IMAP_CERTIFICATE_FAILED'));out(502,array('ok'=>false,'error'=>'Máy chủ đã mở được cổng 993 nhưng phiên IMAP không khởi tạo được. Hãy kiểm tra cấu hình Gmail và php.error.log.','code'=>'IMAP_SESSION_FAILED'));}
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
function header_value($raw,$name){$raw=preg_replace("/\r?\n[ \t]+/",' ',(string)$raw);$m=array();return preg_match('/^'.preg_quote($name,'/').':\s*(.+)$/mi',$raw,$m)?mail_utf8(trim($m[1])):'';}
function imap_error_text(){
  $errors=array();
  if(function_exists('imap_last_error')){$last=@imap_last_error();if($last)$errors[]=(string)$last;}
  if(function_exists('imap_errors')){$all=@imap_errors();if(is_array($all))foreach($all as$one)if($one&&!in_array((string)$one,$errors,true))$errors[]=(string)$one;}
  return trim(implode(' | ',$errors));
}
function imap_close_safe($imap){if($imap)@imap_close($imap);}
function mail_row_value($row,$key,$fallback=''){
  if(is_object($row))return isset($row->$key)?$row->$key:$fallback;
  if(is_array($row))return isset($row[$key])?$row[$key]:$fallback;
  return$fallback;
}

if($action==='manage'){
  $uid=isset($body['uid'])?trim((string)$body['uid']):'';$operation=isset($body['operation'])?trim((string)$body['operation']):'';
  if(!preg_match('/^\d+$/',$uid)||!in_array($operation,array('mark_read','mark_unread','star','unstar','archive'),true)){imap_close($imap);out(422,array('ok'=>false,'error'=>'UID hoặc thao tác email không hợp lệ.'));}
  $ok=false;
  if($operation==='mark_read')$ok=@imap_setflag_full($imap,$uid,'\\Seen',ST_UID);
  elseif($operation==='mark_unread')$ok=@imap_clearflag_full($imap,$uid,'\\Seen',ST_UID);
  elseif($operation==='star')$ok=@imap_setflag_full($imap,$uid,'\\Flagged',ST_UID);
  elseif($operation==='unstar')$ok=@imap_clearflag_full($imap,$uid,'\\Flagged',ST_UID);
  else{$archive=isset($MTPC_EMAIL_ARCHIVE_FOLDER)?trim((string)$MTPC_EMAIL_ARCHIVE_FOLDER):'[Gmail]/All Mail';$ok=@imap_mail_move($imap,$uid,$archive,CP_UID);if($ok)@imap_expunge($imap);}
  if(!$ok){$error=function_exists('imap_last_error')?(string)@imap_last_error():'';imap_close($imap);out(502,array('ok'=>false,'error'=>'Không thực hiện được thao tác với email.'.($error!==''?' '.$error:''),'code'=>'EMAIL_MANAGE_FAILED'));}
  imap_close($imap);$labels=array('mark_read'=>'đánh dấu đã đọc','mark_unread'=>'đánh dấu chưa đọc','star'=>'gắn sao','unstar'=>'bỏ gắn sao','archive'=>'lưu trữ');out(200,array('ok'=>true,'message'=>'Đã '.$labels[$operation].' email.','uid'=>$uid,'operation'=>$operation));
}

if($action==='list'){
  $range=date_range($body);if(!$range){imap_close($imap);out(422,array('ok'=>false,'error'=>'Khoảng ngày không hợp lệ hoặc dài quá 31 ngày.'));}$limit=isset($body['limit'])?max(1,min(20,(int)$body['limit'])):10;$criteria='SINCE "'.$range[0]->format('d-M-Y').'" BEFORE "'.$range[1]->format('d-M-Y').'"'.(!empty($body['unread_only'])?' UNSEEN':'');$uids=@imap_search($imap,$criteria,SE_UID,'UTF-8');if(!is_array($uids))$uids=array();$sender=isset($body['sender'])?trim((string)$body['sender']):'';$sf=isset($body['subject'])?trim((string)$body['subject']):'';$q=isset($body['query'])?trim((string)$body['query']):'';$items=array();
  foreach($uids as$uid){$o=@imap_fetch_overview($imap,(string)$uid,FT_UID);if(!is_array($o)||!isset($o[0]))continue;$row=$o[0];$from=mail_utf8(isset($row->from)?$row->from:'');$sub=mail_utf8(isset($row->subject)?$row->subject:'(Không có tiêu đề)');if(!has_text($from,$sender)||!has_text($sub,$sf))continue;$ts=isset($row->udate)?(int)$row->udate:(isset($row->date)?strtotime($row->date):0);$items[]=array('uid'=>(string)$uid,'from'=>$from,'from_email'=>address_only($from),'subject'=>$sub,'date'=>$ts?date('c',$ts):'','unread'=>empty($row->seen),'flagged'=>!empty($row->flagged),'_ts'=>$ts);}
  usort($items,function($a,$b){return$a['_ts']===$b['_ts']?0:($a['_ts']<$b['_ts']?1:-1);});$result=array();foreach($items as$x){$preview=cut(preg_replace('/\s+/',' ',extract_text($imap,$x['uid'])),500);if($q!==''&&!has_text($x['from'].' '.$x['subject'].' '.$preview,$q))continue;$x['preview']=$preview;$x['priority']=priority_hint($x['subject'].' '.$preview);unset($x['_ts']);$result[]=$x;if(count($result)>=$limit)break;}imap_close($imap);out(200,array('ok'=>true,'range_label'=>$range[2],'count'=>count($result),'emails'=>$result,'filters'=>array('sender'=>$sender,'subject'=>$sf,'query'=>$q)));
}
/* Read one message defensively. Some PHP 5.6 IMAP builds return an array
 * instead of an object for overview fields, and older builds can emit a
 * warning while decoding a malformed MIME message. Neither case should turn
 * the whole endpoint into an unexplained HTTP 500. */
if($action==='read'){
  $uid=isset($body['uid'])?trim((string)$body['uid']):'';
  if(!preg_match('/^\d+$/',$uid)){imap_close_safe($imap);out(422,array('ok'=>false,'error'=>'UID không hợp lệ.','code'=>'EMAIL_UID_INVALID'));}
  $readError='';
  set_error_handler(function($severity,$message,$file,$line)use(&$readError){if(!(error_reporting()&$severity))return false;$readError=$message.' tại dòng '.$line;throw new Exception($message);});
  try{
    $overview=@imap_fetch_overview($imap,$uid,FT_UID);
    if(!is_array($overview)||!isset($overview[0])){restore_error_handler();imap_close_safe($imap);out(404,array('ok'=>false,'error'=>'Không tìm thấy email với UID '.$uid.'.','code'=>'EMAIL_NOT_FOUND'));}
    $row=$overview[0];
    $rawHeader=@imap_fetchheader($imap,$uid,FT_UID|FT_PEEK);if($rawHeader===false)$rawHeader='';
    $from=mail_utf8(mail_row_value($row,'from',''));
    $replyTo=header_value($rawHeader,'Reply-To');
    $messageId=clean_line(mail_row_value($row,'message_id',header_value($rawHeader,'Message-ID')));
    $references=clean_line(header_value($rawHeader,'References'));
    $dateValue=mail_row_value($row,'udate',0);$dateFallback=mail_row_value($row,'date','');$ts=(int)$dateValue;if(!$ts&&$dateFallback)$ts=(int)strtotime((string)$dateFallback);
    $bodyText=extract_text($imap,$uid);
    /* Keep this compatible with PHP 5.6: empty() should receive a variable,
     * not the return value of a function on older IMAP/PHP combinations. */
    $seenValue=mail_row_value($row,'seen',false);
    $flaggedValue=mail_row_value($row,'flagged',false);
    $email=array('uid'=>$uid,'from'=>$from,'from_email'=>address_only($from),'reply_to'=>address_only($replyTo!==''?$replyTo:$from),'to'=>mail_utf8(mail_row_value($row,'to','')),'subject'=>mail_utf8(mail_row_value($row,'subject','(Không có tiêu đề)')),'date'=>$ts?date('c',$ts):'','unread'=>empty($seenValue),'flagged'=>!empty($flaggedValue),'message_id'=>$messageId,'references'=>$references,'body'=>cut($bodyText,16000));
    restore_error_handler();imap_close_safe($imap);out(200,array('ok'=>true,'email'=>$email));
  }catch(Exception $exception){
    restore_error_handler();$detail=$readError!==''?$readError:$exception->getMessage();error_log('[MTPC_EMAIL_READ] '.$detail);imap_close_safe($imap);out(502,array('ok'=>false,'error'=>'Không đọc được nội dung email. Hộp thư đã kết nối nhưng email này có header hoặc nội dung không tương thích.','detail'=>$detail,'code'=>'EMAIL_MESSAGE_READ_FAILED'));
  }
}
imap_close($imap);out(400,array('ok'=>false,'error'=>'Thao tác email không hợp lệ.'));
