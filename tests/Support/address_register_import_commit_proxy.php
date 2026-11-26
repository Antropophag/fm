<?php
declare(strict_types=1);

// Test-only MySQL wire proxy: deliver COMMIT, consume the successful server ACK,
// then disconnect the client. No authentication material or SQL is logged.
$configuration=json_decode(file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);
$listener=stream_socket_server('tcp://127.0.0.1:0',$errorNumber,$errorText);
if($listener===false)throw new RuntimeException('Commit proxy listener unavailable.');
$endpoint=stream_socket_get_name($listener,false);
file_put_contents($argv[2],substr($endpoint,strrpos($endpoint,':')+1));
chmod($argv[2],0600);
$client=null;$server=null;
$readExact=static function($stream,int$length):string{
    $bytes='';
    while(strlen($bytes)<$length){
        $part=fread($stream,$length-strlen($bytes));
        if($part===false||$part==='')throw new RuntimeException('Commit proxy connection ended.');
        $bytes.=$part;
    }
    return$bytes;
};
$writeAll=static function($stream,string$bytes):void{
    $offset=0;
    while($offset<strlen($bytes)){
        $written=fwrite($stream,substr($bytes,$offset));
        if($written===false||$written===0)throw new RuntimeException('Commit proxy write failed.');
        $offset+=$written;
    }
};
try{
    $client=stream_socket_accept($listener,30);
    if($client===false)throw new RuntimeException('Commit proxy client absent.');
    $server=stream_socket_client('tcp://'.$configuration['host'].':'.$configuration['port'],$errorNumber,$errorText,10);
    if($server===false)throw new RuntimeException('Commit proxy backend unavailable.');
    stream_set_timeout($client,15);stream_set_timeout($server,15);
    $commitPending=false;$deadline=microtime(true)+90;
    while(microtime(true)<$deadline){
        $read=[$client,$server];$write=null;$except=null;
        $ready=stream_select($read,$write,$except,5);
        if($ready===false)throw new RuntimeException('Commit proxy select failed.');
        foreach($read as$input){
            $header=$readExact($input,4);
            $length=ord($header[0])|(ord($header[1])<<8)|(ord($header[2])<<16);
            if($length>4*1024*1024)throw new RuntimeException('Commit proxy fixture packet unexpectedly large.');
            $payload=$readExact($input,$length);
            if($input===$client){
                if(ord($header[3])===0&&($payload[0]??'')==="\x03"&&preg_match('/^COMMIT(?:\s|$)/i',trim(substr($payload,1))))$commitPending=true;
                $writeAll($server,$header.$payload);
            }else{
                if($commitPending){
                    if(($payload[0]??'')!=="\x00")throw new RuntimeException('Commit proxy did not receive a successful commit.');
                    file_put_contents($argv[3],"commit\n",FILE_APPEND|LOCK_EX);chmod($argv[3],0600);
                    fclose($client);$client=null;fclose($server);$server=null;
                    exit(0);
                }
                $writeAll($client,$header.$payload);
            }
        }
    }
    throw new RuntimeException('Commit proxy deadline exceeded.');
}finally{
    if(is_resource($client))fclose($client);
    if(is_resource($server))fclose($server);
    fclose($listener);
}
