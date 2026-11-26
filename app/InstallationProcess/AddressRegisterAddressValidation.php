<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class AddressRegisterAddressValidation
{
    /** @param array<string,mixed> $cleaned @return array{accepted:bool,reasonCode:?string,address?:string,houseFiasId?:string} */
    public static function validate(string $source, array $cleaned): array
    {
        $reject = static fn(): array => ['accepted'=>false,'reasonCode'=>'ADDRESS_UNVERIFIED'];
        if (($cleaned['qc'] ?? null) !== 0 || ($cleaned['qc_house'] ?? null) !== 2
            || (string)($cleaned['fias_level'] ?? '') !== '8' || (string)($cleaned['fias_actuality_state'] ?? '') !== '0'
            || ($cleaned['region_iso_code'] ?? null) !== 'RU-MOW' || trim((string)($cleaned['result'] ?? '')) === ''
            || trim((string)($cleaned['house'] ?? '')) === '' || !self::uuid((string)($cleaned['house_fias_id'] ?? ''))
            || trim((string)($cleaned['unparsed_parts'] ?? '')) !== '' || trim((string)($cleaned['flat'] ?? '')) !== ''
            || trim((string)($cleaned['room'] ?? '')) !== '') return $reject();
        $sourceParts = self::sourceHouse($source); if ($sourceParts === null) return $reject();
        if (self::component($sourceParts['house']) !== self::component((string)$cleaned['house'])) return $reject();
        $cleanBlock = trim((string)($cleaned['block'] ?? '')); $cleanType = self::blockType((string)($cleaned['block_type'] ?? ''));
        if (($sourceParts['block'] ?? '') !== self::component($cleanBlock) || ($sourceParts['blockType'] ?? '') !== $cleanType) return $reject();
        return ['accepted'=>true,'reasonCode'=>null,'address'=>trim((string)$cleaned['result']),'houseFiasId'=>(string)$cleaned['house_fias_id']];
    }

    private static function uuid(string $value): bool { return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$value)===1; }
    private static function component(string $value): string { return mb_strtolower(trim($value),'UTF-8'); }
    private static function blockType(string $value): string { $value=rtrim(self::component($value),'.');return match($value){'к','корп','корпус'=>'к','с','стр','строение'=>'с',default=>$value}; }
    /** @return array{house:string,block?:string,blockType?:string}|null */
    private static function sourceHouse(string $source): ?array
    {
        $text = preg_replace('/\s*\(\s*подъезд[^)]*\)\s*$/iu','',trim($source));
        if ($text === null || preg_match('/\bили\b/iu',$text) || str_contains($text,'\\')
            || preg_match('/(?:^|[,\s])(?:кв(?:артира)?|офис|пом(?:ещение)?)(?:\.|\s)+[0-9а-яa-z]/iu',$text)) return null;
        if (preg_match('/^(?:(?:г\.?\s*)?москва\s*,\s*)?(?:г\.?\s*)?зеленоград(?:\s+г\.?)?\s*,?\s*к\.?\s*([0-9]+[а-яa-z]?)\s*$/iu',$text,$m)) return ['house'=>self::component($m[1])];
        if (preg_match('/^(?:(?:г\.?\s*)?москва\s*,\s*)?квартал\s+38-й\s+юго-запада\s+к\.?\s*([0-9]+[а-яa-z]?)\s*$/iu',$text,$m)) return ['house'=>self::component($m[1])];
        if (preg_match('/(?:^|[,\s])(?:д(?:ом)?\.?\s*)?([0-9]+(?:\/[0-9]+(?:-[0-9]+)?|-[0-9]+|[а-яa-z])?)(?:\s+(к(?:орп(?:ус)?)?\.?|с(?:тр(?:оение)?)?\.?)\s*([0-9]+[а-яa-z]?|[а-яa-z]))?\s*$/iu',$text,$m,PREG_OFFSET_CAPTURE)) {
            $matched=$m[0][0];$offset=$m[0][1];$rest=substr($text,0,$offset).substr($text,$offset+strlen($matched));
            $explicitHouse=preg_match('/(?:^|[,\s])д(?:ом)?\.?\s*[0-9]/iu',$matched)===1;
            if(preg_match('/(?:^|[,\s])(?:д(?:ом)?|к(?:орп(?:ус)?)?|с(?:тр(?:оение)?)?)\.?\s*(?:[0-9]|[а-яa-z](?:\s|$))/iu',$rest)
                || (!$explicitHouse&&preg_match('/(?:,|\s)\s*[0-9]+(?:\/[0-9]+(?:-[0-9]+)?|-[0-9]+|[а-яa-z])?\s*,?\s*$/iu',$rest)))return null;
            $out=['house'=>self::component($m[1][0])];
            if (($m[3][0]??'')!=='') { $out['block']=self::component($m[3][0]); $out['blockType']=str_starts_with(self::component($m[2][0]),'к')?'к':'с'; }
            return $out;
        }
        return null;
    }
}
