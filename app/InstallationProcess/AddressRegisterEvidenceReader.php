<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

/** Bounded JSON reader for the public evidence envelope. */
final class AddressRegisterEvidenceReader
{
    private $stream; private ?string $look = null; private bool $eof = false; private ?int $budget = null;
    private function __construct($stream) { $this->stream = $stream; }
    public static function read(string $path): array
    {
        $stream = fopen($path, 'rb'); if (!is_resource($stream)) throw new \RuntimeException('Evidence unavailable');
        try { $reader = new self($stream); $reader->budget=4194304;$value = $reader->object(true); $reader->space(); if ($reader->peek() !== null) throw new \RuntimeException('Trailing JSON'); return $value; }
        finally { fclose($stream); }
    }
    private function object(bool $envelope = false, int $depth = 0): array
    {
        if($depth>32)throw new \RuntimeException('JSON nesting too deep');
        $this->expect('{'); $out = []; $this->space(); if ($this->peek() === '}') { $this->take(); return $out; }
        while (true) {
            $key = $this->string(); $this->space(); $this->expect(':');
            if ($envelope && $key === 'entries') {$remaining=$this->budget;$this->budget=null;$out[$key] = $this->entries();$this->budget=$remaining;} else $out[$key] = $this->value($depth+1);
            $this->space(); $next = $this->take(); if ($next === '}') return $out; if ($next !== ',') throw new \RuntimeException('Invalid object');
        }
    }
    private function entries(): AddressRegisterPayloadSequence
    {
        $writer = new AddressRegisterPayloadWriter(); $this->space(); $this->expect('['); $this->space();
        if ($this->peek() === ']') { $this->take(); return $writer->finish(); }
        while (true) { $this->budget=4194304;$entry = $this->value(1);$this->budget=null;if (!is_array($entry)) throw new \RuntimeException('Invalid evidence entry'); $writer->add($entry); $this->space(); $next=$this->take(); if($next===']')return$writer->finish();if($next!==',')throw new \RuntimeException('Invalid entries'); }
    }
    private function value(int $depth = 0): mixed
    {
        if($depth>32)throw new \RuntimeException('JSON nesting too deep');
        $this->space(); $c=$this->peek();
        if($c==='{')return$this->object(false,$depth); if($c==='[')return$this->array($depth); if($c==='"')return$this->string();
        $token='';while(($c=$this->peek())!==null&&!str_contains(" \t\r\n,]}",$c))$token.=$this->take();
        if($token==='true')return true;if($token==='false')return false;if($token==='null')return null;
        if(!preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?$/D',$token))throw new \RuntimeException('Invalid JSON value');
        return json_decode($token,true,flags:JSON_THROW_ON_ERROR);
    }
    private function array(int $depth): array
    {
        $this->expect('[');$out=[];$this->space();if($this->peek()===']'){$this->take();return$out;}
        while(true){$out[]=$this->value($depth+1);$this->space();$next=$this->take();if($next===']')return$out;if($next!==',')throw new \RuntimeException('Invalid array');}
    }
    private function string(): string
    {
        $this->space();$this->expect('"');$json='"';$escaped=false;
        while(($c=$this->take())!==null){$json.=$c;if(!$escaped&&$c==='"')return json_decode($json,true,flags:JSON_THROW_ON_ERROR);if(!$escaped&&$c==='\\')$escaped=true;else$escaped=false;if(strlen($json)>4194304)throw new \RuntimeException('JSON string too large');}
        throw new \RuntimeException('Unterminated JSON string');
    }
    private function space():void{while(($c=$this->peek())!==null&&str_contains(" \t\r\n",$c))$this->take();}
    private function expect(string$c):void{$this->space();if($this->take()!==$c)throw new \RuntimeException('Invalid JSON');}
    private function peek():?string{if($this->look===null&&!$this->eof){$c=fgetc($this->stream);if($c===false)$this->eof=true;else$this->look=$c;}return$this->eof?null:$this->look;}
    private function take():?string{$c=$this->peek();$this->look=null;if($c!==null&&$this->budget!==null&&--$this->budget<0)throw new \RuntimeException('JSON payload too large');return$c;}
}
