<?php
/* Persistent, reviewable knowledge bundle helpers. PHP 5.6 compatible. */
function mtpc_knowledge_dir(){return '/home/mtpc/private/mtpc-knowledge';}
function mtpc_knowledge_path(){return mtpc_knowledge_dir().'/manual-bundle.json';}
function mtpc_knowledge_read($path){
    if(!is_file($path))return array('version'=>3,'sources'=>array(),'chunks'=>array());
    $data=json_decode(@file_get_contents($path),true);
    return is_array($data)?$data:array('version'=>3,'sources'=>array(),'chunks'=>array());
}
function mtpc_knowledge_cut($value,$limit){$value=trim((string)$value);return function_exists('mb_substr')?mb_substr($value,0,$limit,'UTF-8'):substr($value,0,$limit);}
function mtpc_knowledge_clean($text){
    $text=str_replace(array("\0","\xC2\xA0"),array('',' '),(string)$text);
    $lines=preg_split('/\r\n|\r|\n/',$text);$out=array();
    foreach($lines as$line){$line=trim(preg_replace('/[ \t]+/u',' ',$line));if($line!=='')$out[]=$line;}
    return implode("\n",$out);
}
function mtpc_knowledge_ascii($text){$text=function_exists('mb_strtolower')?mb_strtolower((string)$text,'UTF-8'):strtolower((string)$text);$ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text);return $ascii!==false?strtolower($ascii):$text;}
function mtpc_knowledge_private_reason($name,$text){
    $value=mtpc_knowledge_ascii($name.' '.$text);
    $value=trim(preg_replace('/[^a-z0-9]+/',' ',$value));
    if(strpos($value,'the hoc sinh')!==false)return 'File thẻ học sinh có dữ liệu cá nhân.';
    if(strpos($value,'idvnm')!==false||strpos($value,'dac diem nhan dang')!==false)return 'File có dữ liệu CCCD/nhận dạng cá nhân.';
    if(preg_match('/\b\d{12}\b/',$value)&&preg_match('/(cccd|can cuoc|ngay sinh|ho va ten)/',$value))return 'File có số CCCD và thông tin cá nhân.';
    if(preg_match('/ma (hoc sinh|sinh vien)/',$value)&&strpos($value,'ngay sinh')!==false&&preg_match_all('/\b(?:[0-3]?\d[\/-][01]?\d[\/-](?:19|20)\d{2}|(?:19|20)\d{2}[\/-][01]?\d[\/-][0-3]?\d)\b/',$value,$dates)>=2)return 'File có danh sách học sinh/sinh viên và ngày sinh.';
    return '';
}
function mtpc_knowledge_year($text){
    preg_match_all('/\b20(?:1[5-9]|2[0-9])\b/',(string)$text,$matches);$years=array_map('intval',$matches[0]);
    return count($years)?max($years):(int)date('Y');
}
function mtpc_knowledge_chunks($source){
    if(empty($source['active']))return array();$text=(string)$source['text'];$length=function_exists('mb_strlen')?mb_strlen($text,'UTF-8'):strlen($text);$result=array();$offset=0;$number=0;
    while($offset<$length){$part=function_exists('mb_substr')?mb_substr($text,$offset,1100,'UTF-8'):substr($text,$offset,1100);$part=trim($part);if(strlen($part)>=80){$result[]=array('id'=>sha1($source['id'].'#'.$number.'#'.$part),'source_id'=>$source['id'],'url'=>'https://mtpc.edu.vn/tuyen-sinh','title'=>$source['title'],'text'=>$part,'source_type'=>$source['type'],'source_year'=>$source['year'],'updated_at'=>$source['updated_at'],'origin'=>isset($source['origin'])?$source['origin']:'upload');$number++;}$offset+=920;}
    return $result;
}
function mtpc_knowledge_rebuild($bundle){
    $chunks=array();foreach(isset($bundle['sources'])?$bundle['sources']:array() as$source)foreach(mtpc_knowledge_chunks($source)as$chunk)$chunks[]=$chunk;
    $bundle['version']=3;$bundle['built_at']=gmdate('c');$bundle['source_count']=count(isset($bundle['sources'])?$bundle['sources']:array());$bundle['active_source_count']=0;foreach($bundle['sources']as$source)if(!empty($source['active']))$bundle['active_source_count']++;$bundle['chunk_count']=count($chunks);$bundle['chunks']=$chunks;return $bundle;
}
function mtpc_knowledge_write($bundle){
    $dir=mtpc_knowledge_dir();if(!is_dir($dir)&&!@mkdir($dir,0750,true))throw new Exception('Không tạo được thư mục dữ liệu AI.');
    $bundle=mtpc_knowledge_rebuild($bundle);$json=json_encode($bundle,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);if($json===false)throw new Exception('Không mã hóa được dữ liệu AI.');
    $temp=tempnam($dir,'knowledge-');if($temp===false||file_put_contents($temp,$json,LOCK_EX)===false){if($temp)@unlink($temp);throw new Exception('Không ghi được dữ liệu AI.');}if(!@rename($temp,mtpc_knowledge_path())){@unlink($temp);throw new Exception('Không thay thế được dữ liệu AI.');}return $bundle;
}
function mtpc_knowledge_upsert($bundle,$source){
    $sources=isset($bundle['sources'])&&is_array($bundle['sources'])?$bundle['sources']:array();$found=false;
    foreach($sources as$i=>$existing)if(isset($existing['id'])&&$existing['id']===$source['id']){$sources[$i]=$source;$found=true;break;}
    if(!$found)array_unshift($sources,$source);$bundle['sources']=$sources;return $bundle;
}
function mtpc_knowledge_source($name,$type,$text,$actor,$active){
    $text=mtpc_knowledge_clean($text);if(strlen($text)<80)throw new Exception('Tài liệu không có đủ nội dung để lưu.');
    $reason=mtpc_knowledge_private_reason($name,$text);if($reason!=='')throw new Exception($reason.' Hãy xóa dữ liệu cá nhân trước khi nhập.');
    $base=pathinfo($name,PATHINFO_FILENAME);$key=preg_replace('/[^a-z0-9]+/','-',mtpc_knowledge_ascii($name));$key=trim($key,'-');
    return array('id'=>'upload-'.sha1($key),'title'=>mtpc_knowledge_cut(str_replace('_',' ',$base),180),'file_name'=>mtpc_knowledge_cut(basename($name),220),'relative_path'=>basename($name),'type'=>mtpc_knowledge_cut($type,80),'year'=>mtpc_knowledge_year($text),'active'=>(bool)$active,'origin'=>'upload','warning'=>'','updated_at'=>gmdate('c'),'updated_by'=>mtpc_knowledge_cut($actor,120),'sha256'=>hash('sha256',$text),'text'=>$text);
}
