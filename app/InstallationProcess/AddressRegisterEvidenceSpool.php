<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

/** Hash-indexed immutable snapshot of provider cache rows with responses on disk. */
final class AddressRegisterEvidenceSpool implements \IteratorAggregate, \Countable
{
    private $stream; private array $offsets = [];
    public function __construct() { $this->stream=tmpfile();if(!is_resource($this->stream))throw new \RuntimeException('Unable to create evidence spool'); }
    public function put(string $hash, array $entry): void
    {
        if(isset($this->offsets[$hash]))return;
        $offset=ftell($this->stream);$bytes=serialize($entry);$record=pack('N',strlen($bytes)).$bytes;
        if(!is_int($offset)||fwrite($this->stream,$record)!==strlen($record))throw new \RuntimeException('Unable to write evidence spool');
        $this->offsets[$hash]=$offset;
    }
    public function get(string $hash): array
    {
        if(!isset($this->offsets[$hash]))throw new \OutOfBoundsException('Missing evidence');return$this->read($this->offsets[$hash]);
    }
    public function count():int{return count($this->offsets);}
    public function getIterator():\Traversable{foreach($this->offsets as$hash=>$offset)yield$hash=>$this->read($offset);}
    private function read(int $offset):array
    {
        if(fseek($this->stream,$offset)!==0)throw new \RuntimeException('Unable to seek evidence spool');$header=fread($this->stream,4);if(!is_string($header)||strlen($header)!==4)throw new \RuntimeException('Corrupt evidence spool');$length=unpack('Nlength',$header)['length'];$bytes='';while(strlen($bytes)<$length){$chunk=fread($this->stream,$length-strlen($bytes));if(!is_string($chunk)||$chunk==='')throw new \RuntimeException('Corrupt evidence spool');$bytes.=$chunk;}$entry=unserialize($bytes,['allowed_classes'=>false]);if(!is_array($entry))throw new \RuntimeException('Corrupt evidence spool');return$entry;
    }
}
