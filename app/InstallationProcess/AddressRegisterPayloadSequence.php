<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

/** Repeatable, immutable sequence whose values need not be resident in memory. */
final class AddressRegisterPayloadSequence implements \IteratorAggregate, \Countable, \ArrayAccess
{
    private function __construct(private $factory, private int $count) {}

    public static function fromIterable(iterable $values): self
    {
        $stream = tmpfile();
        if (!is_resource($stream)) throw new \RuntimeException('Unable to create payload spool');
        $count = 0;
        foreach ($values as $value) {
            $bytes = serialize($value);
            if (fwrite($stream, pack('N', strlen($bytes)).$bytes) !== strlen($bytes) + 4) {
                throw new \RuntimeException('Unable to write payload spool');
            }
            $count++;
        }
        return new self(static function () use ($stream, $count): \Generator {
            $offset = 0;
            for ($i = 0; $i < $count; $i++) {
                if (fseek($stream, $offset) !== 0) throw new \RuntimeException('Unable to seek payload spool');
                $header = fread($stream, 4);
                if (!is_string($header) || strlen($header) !== 4) throw new \RuntimeException('Corrupt payload spool');
                $length = unpack('Nlength', $header)['length'];
                $bytes = '';
                while (strlen($bytes) < $length) {
                    $chunk = fread($stream, $length - strlen($bytes));
                    if (!is_string($chunk) || $chunk === '') throw new \RuntimeException('Corrupt payload spool');
                    $bytes .= $chunk;
                }
                $offset += 4 + $length;
                yield unserialize($bytes, ['allowed_classes' => false]);
            }
        }, $count);
    }

    public static function fromFactory(callable $iteratorFactory, int $count): self
    {
        if ($count < 0) throw new \InvalidArgumentException('Negative sequence count');
        return new self($iteratorFactory, $count);
    }

    public function getIterator(): \Traversable
    {
        $iterator = ($this->factory)();
        if (!is_iterable($iterator)) throw new \RuntimeException('Payload factory must return iterable');
        yield from $iterator;
    }
    public function count(): int { return $this->count; }
    public function offsetExists(mixed $offset): bool { return is_int($offset) && $offset >= 0 && $offset < $this->count; }
    public function offsetGet(mixed $offset): mixed
    {
        if (!$this->offsetExists($offset)) throw new \OutOfBoundsException();
        foreach ($this as $index => $value) if ($index === $offset) return $value;
        throw new \OutOfBoundsException();
    }
    public function offsetSet(mixed $offset, mixed $value): void { throw new \LogicException('Payload sequence is immutable'); }
    public function offsetUnset(mixed $offset): void { throw new \LogicException('Payload sequence is immutable'); }
}
