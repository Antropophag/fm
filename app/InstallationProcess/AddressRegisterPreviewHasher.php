<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class AddressRegisterPreviewHasher
{
    public static function hash(mixed $value): string
    {
        $context = hash_init('sha256'); hash_update($context, "address-register-preview-v2\0");
        self::frame($context, $value); return hash_final($context);
    }
    private static function frame(\HashContext $context, mixed $value): void
    {
        if (is_array($value) || $value instanceof \Traversable) {
            $list = !is_array($value) || array_is_list($value); hash_update($context, $list ? "L\0" : "O\0");
            if (!$list) ksort($value, SORT_STRING);
            $ordinal = 0;
            foreach ($value as $key => $item) { hash_update($context, pack('N', $ordinal++)); if (!$list) self::scalar($context, (string)$key); self::frame($context, $item); }
            hash_update($context, "E\0"); return;
        }
        self::scalar($context, $value);
    }
    private static function scalar(\HashContext $context, mixed $value): void
    {
        $bytes = json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        hash_update($context, "S\0".pack('N', strlen($bytes)).$bytes);
    }
}
