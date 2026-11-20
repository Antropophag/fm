<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

/** Immutable bounded snapshot used only by preview identity resolution. */
final class AddressRegisterIdentityLookup
{
    private $requests;
    private $matches;
    private array $requestOffsets=[];
    private array $registrationIndex=[];
    private array $identityIndex=[];
    private array $matchOffsets=[];
    private array $matchIds=[];

    private function __construct()
    {
        $this->requests=tmpfile();$this->matches=tmpfile();
        if(!is_resource($this->requests)||!is_resource($this->matches))throw new \RuntimeException('Unable to create identity lookup spool');
    }

    public static function create(iterable $sourceObjects,iterable $candidateRows):self
    {
        $self=new self();$self->snapshotRequests($sourceObjects);$self->indexCandidates($candidateRows);return$self;
    }

    public function find(int $ordinal,array $fields):array
    {
        $request=$this->request($ordinal);
        if((string)($fields['regnumber']??'')!==$request['registration']||(string)($fields['unom']??'')!==$request['unom']||(string)($fields['entrance']??'')!==$request['entrance'])throw new \RuntimeException('IDENTITY_LOOKUP_MISMATCH');
        $out=[];foreach($this->matchOffsets[$ordinal]??[]as$offset)$out[]=$this->read($this->matches,$offset);return$out;
    }

    private function snapshotRequests(iterable $sourceObjects):void
    {
        $ordinal=0;foreach($sourceObjects as$source){$fields=is_array($source)&&is_array($source['fields']??null)?$source['fields']:$source;if(!is_array($fields))throw new \RuntimeException('IDENTITY_LOOKUP_INVALID');$registration=(string)($fields['regnumber']??'');$unom=(string)($fields['unom']??'');$entrance=(string)($fields['entrance']??'');$identity=hash('sha256',$registration.'|'.$unom.'|'.$entrance);$this->requestOffsets[$ordinal]=$this->write($this->requests,['registration'=>$registration,'unom'=>$unom,'entrance'=>$entrance,'identity'=>$identity]);$this->registrationIndex[$this->registrationDigest($registration)][]=$ordinal;$this->identityIndex[$identity][]=$ordinal;$ordinal++;}
    }

    private function indexCandidates(iterable $candidateRows):void
    {
        foreach($candidateRows as$row){if(!is_array($row))throw new \RuntimeException('IDENTITY_LOOKUP_INVALID');$ordinals=[];$effective=$row['regnumber']??null;if(is_string($effective))foreach($this->registrationIndex[$this->registrationDigest($effective)]??[]as$ordinal)$ordinals[$ordinal]=true;$sourceRegistration=$row['source_registration']??null;if(is_string($sourceRegistration))foreach($this->registrationIndex[$this->registrationDigest($sourceRegistration)]??[]as$ordinal)$ordinals[$ordinal]=true;$sourceIdentity=$row['source_identity_hash']??null;if(is_string($sourceIdentity))foreach($this->identityIndex[$sourceIdentity]??[]as$ordinal)$ordinals[$ordinal]=true;foreach(array_keys($ordinals)as$ordinal){if(count($this->matchIds[$ordinal]??[])>=2)continue;$request=$this->request((int)$ordinal);$matched=(is_string($effective)&&$effective===$request['registration'])||(is_string($sourceRegistration)&&$sourceRegistration===$request['registration'])||(is_string($sourceIdentity)&&$sourceIdentity===$request['identity']);if(!$matched)continue;$id=(string)($row['id']??'');if($id===''||isset($this->matchIds[$ordinal][$id]))continue;$witness=['id'=>$row['id'],'regnumber'=>$row['regnumber'],'ordadr_address'=>$row['ordadr_address'],'entrance'=>$row['entrance'],'unom'=>$row['unom'],'house_fias_id'=>$row['house_fias_id']];$this->matchIds[$ordinal][$id]=true;$this->matchOffsets[$ordinal][]=$this->write($this->matches,$witness);}}
    }

    private function request(int $ordinal):array
    {
        if($ordinal<0||!array_key_exists($ordinal,$this->requestOffsets))throw new \OutOfBoundsException();return$this->read($this->requests,$this->requestOffsets[$ordinal]);
    }
    private function registrationDigest(string $value):string{return hash('sha256',"address-register-regnumber\0".$value);}
    private function write($stream,array $value):int{$bytes=serialize($value);$offset=ftell($stream);$record=pack('N',strlen($bytes)).$bytes;if(!is_int($offset)||fwrite($stream,$record)!==strlen($record))throw new \RuntimeException('Unable to write identity lookup spool');return$offset;}
    private function read($stream,int $offset):array{if(fseek($stream,$offset)!==0)throw new \RuntimeException('Corrupt identity lookup spool');$header=fread($stream,4);if(!is_string($header)||strlen($header)!==4)throw new \RuntimeException('Corrupt identity lookup spool');$length=(int)unpack('Nlength',$header)['length'];$bytes='';while(strlen($bytes)<$length){$chunk=fread($stream,$length-strlen($bytes));if(!is_string($chunk)||$chunk==='')throw new \RuntimeException('Corrupt identity lookup spool');$bytes.=$chunk;}$value=unserialize($bytes,['allowed_classes'=>false]);if(!is_array($value))throw new \RuntimeException('Corrupt identity lookup spool');return$value;}
}
