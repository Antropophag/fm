<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\InspectionEvidence\InspectionPhotoThumbnail;
// PRODUCTION-PERFORMANCE-001 A03: the thumbnail preserves the browser-visible orientation of original evidence.
if(!extension_loaded('gd'))throw new TestFailure('SETUP_FAILURE: GD required');
$root=sys_get_temp_dir().'/fm2-photo-orientation-'.bin2hex(random_bytes(6));mkdir($root,0700);$service=new InspectionPhotoThumbnail();
$cleanup=static function(string$p)use(&$cleanup):void{if(is_file($p)){unlink($p);return;}foreach(scandir($p)?:[]as$f)if($f!=='.'&&$f!=='..')$cleanup($p.'/'.$f);rmdir($p);};
try{
 $image=imagecreatetruecolor(60,40);$palette=['R'=>[240,10,10],'G'=>[10,240,10],'B'=>[10,10,240],'Y'=>[240,240,10]];foreach([[0,0,'R'],[30,0,'G'],[0,20,'B'],[30,20,'Y']]as[$x,$y,$name])imagefilledrectangle($image,$x,$y,$x+29,$y+19,imagecolorallocate($image,...$palette[$name]));
 $expected=[1=>['R','G','B','Y'],2=>['G','R','Y','B'],3=>['Y','B','G','R'],4=>['B','Y','R','G'],5=>['R','B','G','Y'],6=>['B','R','Y','G'],7=>['Y','G','B','R'],8=>['G','Y','R','B']];
 foreach(['jpeg','png','webp']as$format){ob_start();$encoder='image'.$format;$encoder($image,null);$raw=ob_get_clean();foreach(['II','MM']as$byteOrder)foreach($expected as$orientation=>$corners){
  $short=$byteOrder==='II'?'v':'n';$long=$byteOrder==='II'?'V':'N';$entry=$short.$short.$long;$tiff=$byteOrder.pack($short.$long,42,8).pack($short,2).pack($entry,270,2,4)."abc\0".pack($entry,274,3,1).pack($short,$orientation)."\0\0".pack($long,0);
  if($format==='jpeg'){$exif="Exif\0\0".$tiff;$bytes=substr($raw,0,2)."\xff\xe1".pack('n',strlen($exif)+2).$exif.substr($raw,2);}
  elseif($format==='png'){$chunk='eXIf'.$tiff;$bytes=substr($raw,0,33).pack('N',strlen($tiff)).$chunk.pack('N',crc32($chunk)).substr($raw,33);}
  else{$chunk=$tiff;$header="\x08\0\0\0".substr(pack('V',59),0,3).substr(pack('V',39),0,3);$payload='WEBP'.'VP8X'.pack('V',10).$header.substr($raw,12).'EXIF'.pack('V',strlen($chunk)).$chunk.(strlen($chunk)%2?"\0":'');$bytes='RIFF'.pack('V',strlen($payload)).$payload;}
  $path=$root.'/'.$format.'-'.$byteOrder.'-'.$orientation;file_put_contents($path,$bytes);$sha=hash('sha256',$bytes);$directory=$root.'/cache/checklist-thumbnails';if(!is_dir($directory))mkdir($directory,0700,true);$mime='image/'.$format;$oldKey=hash('sha256',"gd-640-jpeg82-v1\0".$sha."\0".$mime);$oldPath=$directory.'/'.$oldKey.'.bin';file_put_contents($oldPath,$raw);file_put_contents($directory.'/'.$oldKey.'.json',json_encode(['mime'=>$mime,'width'=>60,'height'=>40],JSON_THROW_ON_ERROR));chmod($oldPath,0600);chmod($directory.'/'.$oldKey.'.json',0600);$result=$service->render($path,$sha,$root.'/cache');$dimensions=$orientation>=5?[40,60]:[60,40];assertSameValue($dimensions,[$result['width'],$result['height']],'INTENDED_RED EXIF display dimensions '.$format.' '.$orientation);$decoded=imagecreatefromstring($result['bytes']);$actual=[];foreach([[.25,.25],[.75,.25],[.25,.75],[.75,.75]]as[$x,$y]){$rgb=imagecolorat($decoded,(int)($x*imagesx($decoded)),(int)($y*imagesy($decoded)));$channels=[($rgb>>16)&255,($rgb>>8)&255,$rgb&255];$distances=[];foreach($palette as$name=>$color)$distances[$name]=abs($channels[0]-$color[0])+abs($channels[1]-$color[1])+abs($channels[2]-$color[2]);asort($distances);$actual[]=array_key_first($distances);}assertSameValue($corners,$actual,'INTENDED_RED visible orientation/reflection '.$format.' '.$byteOrder.' '.$orientation);if($orientation!==1)assertSameValue(false,$raw===$result['bytes'],'v1 sideways cache never served');assertSameValue($raw,file_get_contents($oldPath),'versioned correction does not overwrite legacy derivative');assertSameValue($bytes,file_get_contents($path),'original bytes unchanged');assertSameValue($result,$service->render($path,$sha,$root.'/cache'),'oriented derivative cache stable');unset($decoded);
 }}
 echo "PASS PRODUCTION-PERFORMANCE-001 A03 all EXIF orientations for JPEG PNG WebP\n";
}finally{$cleanup($root);}
