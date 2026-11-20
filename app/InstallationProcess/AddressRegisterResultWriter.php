<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class AddressRegisterResultWriter
{
    public static function write($stream, mixed $result, bool $pretty = false): void
    {
        if (!is_resource($stream)) throw new \InvalidArgumentException('Output is not a stream');
        self::value($stream, $result, $pretty, 0);
    }
    private static function value($stream, mixed $value, bool $pretty, int $depth): void
    {
        if (is_array($value) || $value instanceof \Traversable) {
            $isList = !is_array($value) || array_is_list($value);
            self::bytes($stream, $isList ? '[' : '{'); $first = true;
            foreach ($value as $key => $item) {
                if (!$first) self::bytes($stream, ',');
                if ($pretty) self::bytes($stream, "\n".str_repeat('    ', $depth + 1));
                if (!$isList) self::bytes($stream, self::json((string)$key).($pretty ? ': ' : ':'));
                self::value($stream, $item, $pretty, $depth + 1); $first = false;
            }
            if (!$first && $pretty) self::bytes($stream, "\n".str_repeat('    ', $depth));
            self::bytes($stream, $isList ? ']' : '}'); return;
        }
        self::bytes($stream, self::json($value));
    }
    private static function json(mixed $value): string
    { return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR); }
    private static function bytes($stream, string $bytes): void
    {
        $offset = 0;
        while ($offset < strlen($bytes)) { $written = fwrite($stream, substr($bytes, $offset)); if (!is_int($written) || $written < 1) throw new \RuntimeException('Output write failed'); $offset += $written; }
    }
}
