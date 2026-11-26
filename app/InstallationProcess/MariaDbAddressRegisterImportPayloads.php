<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

/** Internal bounded persistence for immutable package payload generations. */
final class MariaDbAddressRegisterImportPayloads
{
    private const TARGET_BYTES = 262144;
    private const HARD_BYTES = 4194304;
    private const MAX_ITEMS = 128;
    private const MARKER = '$fm2Payload';

    private function __construct(private \mysqli $db, private string $prefix)
    {
        if (strlen($prefix) > 28 || preg_match('/^[A-Za-z0-9_]*$/D', $prefix) !== 1) throw new \InvalidArgumentException();
    }

    public static function create(\mysqli $db,string $prefix):self{return new self($db,$prefix);}

    public function store(string $packageId, array $document, string $now): array
    {
        $generation = $this->uuid();
        return $this->externalize($packageId, $generation, $document, $now, 'root');
    }

    public function hydrate(string $packageId, array $document): array
    {
        return $this->restore($packageId, $document);
    }

    private function externalize(string $packageId, string $generation, mixed $value, string $now, string $path): mixed
    {
        if ($value instanceof \Traversable) return $this->writeSequence($packageId, $generation, $this->kind($path), $value, $now);
        if (!is_array($value)) return $value;
        $out = [];
        foreach ($value as $key => $item) $out[$key] = $this->externalize($packageId, $generation, $item, $now, $path.'.'.(string)$key);
        return $out;
    }

    private function writeSequence(string $packageId, string $generation, string $kind, iterable $values, string $now): array
    {
        $ordinal = 0; $count = 0; $chunk = []; $bytes = 2; $digest = hash_init('sha256');
        foreach ($values as $value) {
            $encoded = $this->json($value); $addition = strlen($encoded) + ($chunk === [] ? 0 : 1);
            if ($chunk !== [] && (count($chunk) >= self::MAX_ITEMS || $bytes + $addition > self::TARGET_BYTES)) {
                $this->insert($packageId, $generation, $kind, $ordinal++, $chunk, $now); $chunk = []; $bytes = 2;
            }
            if (strlen($encoded) + 2 > self::HARD_BYTES) throw new \RuntimeException('PAYLOAD_ITEM_TOO_LARGE');
            $chunk[] = $value; $bytes += $addition; $count++;
            hash_update($digest, pack('N', strlen($encoded)).$encoded);
        }
        if ($chunk !== []) $this->insert($packageId, $generation, $kind, $ordinal++, $chunk, $now);
        return [self::MARKER => ['version'=>1,'generation'=>$generation,'kind'=>$kind,'count'=>$count,'chunks'=>$ordinal,'digest'=>hash_final($digest)]];
    }

    private function insert(string $packageId, string $generation, string $kind, int $ordinal, array $items, string $now): void
    {
        $json = $this->json($items);
        if (strlen($json) > self::HARD_BYTES) throw new \RuntimeException('PAYLOAD_CHUNK_TOO_LARGE');
        $statement = $this->db->prepare("INSERT INTO `{$this->prefix}fm2_address_import_payload_chunks`(package_id,generation_id,kind,ordinal,item_count,payload_json,payload_sha256,created_at_utc)VALUES(?,?,?,?,?,?,?,?)");
        $statement->execute([$packageId,$generation,$kind,$ordinal,count($items),$json,hash('sha256',$json),$now]);
    }

    private function restore(string $packageId, mixed $value): mixed
    {
        if ($this->isMarker($value)) return $this->sequence($packageId, $value[self::MARKER]);
        if (!is_array($value)) return $value;
        $out=[]; foreach ($value as $key=>$item) $out[$key]=$this->restore($packageId,$item); return $out;
    }

    private function sequence(string $packageId, array $manifest): AddressRegisterPayloadSequence
    {
        if (array_keys($manifest)!==['version','generation','kind','count','chunks','digest'] || $manifest['version']!==1 || !$this->uuidValid($manifest['generation']) || !is_string($manifest['kind']) || !preg_match('/^[a-z0-9_]{1,32}$/D',$manifest['kind']) || !is_int($manifest['count']) || $manifest['count']<0 || !is_int($manifest['chunks']) || $manifest['chunks']<0 || !is_string($manifest['digest']) || !preg_match('/^[0-9a-f]{64}$/D',$manifest['digest'])) throw new \RuntimeException('PAYLOAD_GENERATION_CORRUPT');
        $generation=$manifest['generation'];$kind=$manifest['kind'];$expectedCount=$manifest['count'];$expectedChunks=$manifest['chunks'];$expectedDigest=$manifest['digest'];
        $this->validate($packageId,$generation,$kind,$expectedCount,$expectedChunks,$expectedDigest);
        return AddressRegisterPayloadSequence::fromFactory(function()use($packageId,$generation,$kind,$expectedCount,$expectedChunks,$expectedDigest):\Generator{
            $seen=0;$chunks=0;$digest=hash_init('sha256');
            while($chunks<$expectedChunks){$row=$this->chunk($packageId,$generation,$kind,$chunks);if($row===null)throw new \RuntimeException('PAYLOAD_GENERATION_CORRUPT');$this->assertChunk($row,$chunks);$items=json_decode($row['payload_json'],true,flags:JSON_THROW_ON_ERROR);foreach($items as$item){$encoded=$this->json($item);hash_update($digest,pack('N',strlen($encoded)).$encoded);$seen++;yield$item;}$chunks++;}
            if($this->chunk($packageId,$generation,$kind,$chunks)!==null)throw new \RuntimeException('PAYLOAD_GENERATION_CORRUPT');
            if($seen!==$expectedCount||$chunks!==$expectedChunks||!hash_equals($expectedDigest,hash_final($digest)))throw new \RuntimeException('PAYLOAD_GENERATION_CORRUPT');
        },$expectedCount);
    }

    private function validate(string $packageId,string $generation,string $kind,int $expectedCount,int $expectedChunks,string $expectedDigest):void
    {
        $seen=0;$chunks=0;$digest=hash_init('sha256');while($chunks<$expectedChunks){$row=$this->chunk($packageId,$generation,$kind,$chunks);if($row===null)throw new \RuntimeException('PAYLOAD_GENERATION_CORRUPT');$this->assertChunk($row,$chunks);$items=json_decode($row['payload_json'],true,flags:JSON_THROW_ON_ERROR);foreach($items as$item){$encoded=$this->json($item);hash_update($digest,pack('N',strlen($encoded)).$encoded);$seen++;}$chunks++;}
        if($this->chunk($packageId,$generation,$kind,$chunks)!==null)throw new \RuntimeException('PAYLOAD_GENERATION_CORRUPT');
        if($seen!==$expectedCount||$chunks!==$expectedChunks||!hash_equals($expectedDigest,hash_final($digest)))throw new \RuntimeException('PAYLOAD_GENERATION_CORRUPT');
    }

    private function chunk(string $packageId,string $generation,string $kind,int $ordinal):?array
    {
        $statement=$this->db->prepare("SELECT ordinal,item_count,payload_json,payload_sha256 FROM `{$this->prefix}fm2_address_import_payload_chunks` WHERE package_id=? AND generation_id=? AND kind=? AND ordinal>=? ORDER BY ordinal LIMIT 1");$statement->execute([$packageId,$generation,$kind,$ordinal]);return$statement->get_result()->fetch_assoc()?:null;
    }

    private function assertChunk(array $row,int $ordinal):void
    {
        $json=(string)$row['payload_json'];$items=json_decode($json,true,flags:JSON_THROW_ON_ERROR);
        if((int)$row['ordinal']!==$ordinal||!is_array($items)||!array_is_list($items)||count($items)!==(int)$row['item_count']||(int)$row['item_count']<1||(int)$row['item_count']>self::MAX_ITEMS||strlen($json)>self::HARD_BYTES||!hash_equals((string)$row['payload_sha256'],hash('sha256',$json)))throw new \RuntimeException('PAYLOAD_GENERATION_CORRUPT');
    }

    private function isMarker(mixed $value):bool{return is_array($value)&&array_keys($value)===[self::MARKER]&&is_array($value[self::MARKER]);}
    private function kind(string $path):string{return 'p_'.substr(hash('sha256',$path),0,30);}
    private function json(mixed $value):string{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    private function uuidValid(mixed $value):bool{return is_string($value)&&preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',$value)===1;}
    private function uuid():string{$hex=bin2hex(random_bytes(16));return substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-8'.substr($hex,17,3).'-'.substr($hex,20,12);}
}
