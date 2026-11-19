<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class AddressRegisterPayloadWriter
{
    private $stream;
    private int $count = 0;
    private bool $finished = false;
    public function __construct()
    {
        $this->stream = tmpfile();
        if (!is_resource($this->stream)) throw new \RuntimeException('Unable to create payload spool');
    }
    public function add(mixed $item): void
    {
        if ($this->finished) throw new \LogicException('Payload writer is finalized');
        $bytes = serialize($item); $record = pack('N', strlen($bytes)).$bytes;
        if (fwrite($this->stream, $record) !== strlen($record)) throw new \RuntimeException('Unable to write payload spool');
        $this->count++;
    }
    public function finish(): AddressRegisterPayloadSequence
    {
        if ($this->finished) throw new \LogicException('Payload writer is finalized');
        $this->finished = true;
        $stream = $this->stream; $count = $this->count;
        return AddressRegisterPayloadSequence::fromFactory(static function () use ($stream, $count): \Generator {
            $offset = 0;
            for ($i = 0; $i < $count; $i++) {
                if (fseek($stream, $offset) !== 0) throw new \RuntimeException('Unable to seek payload spool');
                $header = fread($stream, 4); if (!is_string($header) || strlen($header) !== 4) throw new \RuntimeException('Corrupt payload spool');
                $length = unpack('Nlength', $header)['length']; $bytes = '';
                while (strlen($bytes) < $length) { $chunk = fread($stream, $length - strlen($bytes)); if (!is_string($chunk) || $chunk === '') throw new \RuntimeException('Corrupt payload spool'); $bytes .= $chunk; }
                $offset += 4 + $length;
                yield unserialize($bytes, ['allowed_classes' => false]);
            }
        }, $count);
    }
}
