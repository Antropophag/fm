<?php

declare(strict_types=1);

namespace FMonitor2\AddressRegister;

use Fuse\Fuse;

if (!class_exists(Fuse::class)) require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Read-only natural address matching shared by the list projections. */
final class Search
{
    /**
     * @param iterable<array{id:mixed,address:string,entrance:string,extra:list<string>}> $records
     * @return list<mixed>
     */
    public static function ids(iterable $records, string $query): array
    {
        $rows = is_array($records) ? array_values($records) : iterator_to_array($records, false);
        $normalizedQuery = self::normalize($query);
        if ($normalizedQuery === '') return array_column($rows, 'id');

        $parsedQuery = self::parse($query, true);
        if ($parsedQuery['conflict']) return [];

        $candidates = [];
        foreach ($rows as $index => $record) {
            $extra = array_map(static fn (mixed $value): string => self::normalize((string) $value), $record['extra'] ?? []);
            if (self::containsAny($extra, $normalizedQuery)) {
                $candidates[$index] = ['record' => $record, 'street' => '', 'extraMatch' => true];
                continue;
            }
            $parsed = self::parse((string) ($record['address'] ?? ''), false);
            $entrance = self::entrance((string) ($record['entrance'] ?? ''));
            if ($entrance !== '') $parsed['entrance'] = $entrance;
            if (!self::componentsMatch($parsedQuery, $parsed)) continue;
            $candidates[$index] = ['record' => $record, 'street' => $parsed['street'], 'extraMatch' => false];
        }

        if ($parsedQuery['words'] !== []) {
            foreach ($parsedQuery['words'] as $word) {
                $exact = [];
                $fuzzyDocs = [];
                $fuzzyOwners = [];
                foreach ($candidates as $index => $candidate) {
                    $hasDigit = preg_match('/\d/u', $word) === 1;
                    $exactWord = $hasDigit ? in_array($word, explode(' ', $candidate['street']), true) : str_contains($candidate['street'], $word);
                    if ($candidate['extraMatch'] || $exactWord) $exact[$index] = $candidate;
                    elseif (mb_strlen($word) >= 5) foreach (explode(' ', $candidate['street']) as $candidateWord) {
                        if (mb_strlen($candidateWord) < 5) continue;
                        $fuzzyDocs[] = $candidateWord;
                        $fuzzyOwners[] = $index;
                    }
                }
                if ($fuzzyDocs !== []) {
                    $fuse = new Fuse(array_values($fuzzyDocs), [
                        'threshold' => mb_strlen($word) <= 5 ? 0.2 : 0.28,
                        'distance' => 1000,
                        'ignoreLocation' => true,
                        'minMatchCharLength' => max(2, mb_strlen($word) - 1),
                        'shouldSort' => false,
                    ]);
                    foreach ($fuse->search($word) as $match) {
                        $position = (int) $match['refIndex'];
                        if (self::editDistance($word, $fuzzyDocs[$position]) > max(1, (int) floor(mb_strlen($word) * 0.28))) continue;
                        $index = $fuzzyOwners[$position];
                        $exact[$index] = $candidates[$index];
                    }
                }
                $candidates = $exact;
                if ($candidates === []) break;
            }
        }

        $ids = [];
        foreach ($rows as $index => $record) if (isset($candidates[$index])) $ids[] = $record['id'];
        return $ids;
    }

    /** @return array{street:string,words:list<string>,streetType:string,house:string,corpus:string,building:string,entrance:string,conflict:bool} */
    private static function parse(string $value, bool $query): array
    {
        $text = self::normalize($value);
        $text = preg_replace('/\b(дом|д)\s*№?\s*(\d+[а-я]?(?:\/\d+)?)\b/u', ' house:$2 ', $text) ?? $text;
        $text = preg_replace('/\b(корпус|корп|к)\s*№?\s*(\d+[а-я]?)\b/u', ' corpus:$2 ', $text) ?? $text;
        $text = preg_replace('/\b(строение|стр|с)\s*№?\s*(\d+[а-я]?)\b/u', ' building:$2 ', $text) ?? $text;
        $text = preg_replace('/\b(подъезд|подезд|под|п)\s*№?\s*(\d+)\b/u', ' entrance:$2 ', $text) ?? $text;

        $parts = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $values = ['house' => [], 'corpus' => [], 'building' => [], 'entrance' => []];
        $plainNumbers = [];
        $words = [];
        $streetTypes = [];
        $lastNameIndex = null;
        foreach ($parts as $index => $part) {
            if (!preg_match('/^(?:house|corpus|building|entrance):/u', $part) && !preg_match('/^\d+[а-я]?(?:\/\d+)?$/u', $part)) $lastNameIndex = $index;
        }
        foreach ($parts as $index => $part) {
            if (preg_match('/^(house|corpus|building|entrance):(.*)$/u', $part, $m)) $values[$m[1]][] = self::component($m[2]);
            elseif (preg_match('/^\d+[а-я]?(?:\/\d+)?$/u', $part) && ($lastNameIndex === null || $index > $lastNameIndex)) $plainNumbers[] = $part;
            else {
                $word = self::streetWord($part);
                if (self::isStreetType($word)) $streetTypes[] = $word;
                else $words[] = $word;
            }
        }
        $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));
        if ($plainNumbers !== []) {
            $slots = ['house', 'corpus', 'entrance'];
            foreach ($plainNumbers as $number) {
                while ($slots !== [] && $values[$slots[0]] !== []) array_shift($slots);
                if ($slots === []) { $values['house'][] = '__conflict__'; break; }
                $values[array_shift($slots)][] = self::component($number);
            }
        }
        $conflict = false;
        $resolved = [];
        foreach ($values as $name => $items) {
            $items = array_values(array_unique(array_filter($items, static fn (string $item): bool => $item !== '')));
            if (count($items) > 1) $conflict = true;
            $resolved[$name] = $items[0] ?? '';
        }
        $streetTypes = array_values(array_unique($streetTypes));
        if (count($streetTypes) > 1) $conflict = true;
        return [
            'street' => implode(' ', $words), 'words' => $words,
            'streetType' => $streetTypes[0] ?? '',
            'house' => $resolved['house'], 'corpus' => $resolved['corpus'],
            'building' => $resolved['building'], 'entrance' => $resolved['entrance'],
            'conflict' => $conflict,
        ];
    }

    private static function normalize(string $value): string
    {
        $value = str_replace(['ё', "\u{00a0}"], ['е', ' '], mb_strtolower($value, 'UTF-8'));
        $value = str_replace(['пр-т', 'пр-кт', 'б-р', 'пр-д'], ['проспект', 'проспект', 'бульвар', 'проезд'], $value);
        $value = preg_replace('/(?<=[а-я])-+(?=[а-я])/u', ' ', $value) ?? $value;
        $value = preg_replace('/(?<=[а-я])\.(?=\d)|(?<=\d)\.(?=[а-я])/u', '', $value) ?? $value;
        $value = preg_replace('/(?<=\d)(?=[ксп]\s*№?\s*\d)/u', ' ', $value) ?? $value;
        $value = preg_replace('/[,.]+/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private static function component(string $value): string { return self::normalize($value); }

    private static function entrance(string $value): string
    {
        $value = self::normalize($value);
        return preg_match('/^(?:(?:подъезд|подезд|под|п)\s*)?№?\s*(\d+)$/u', $value, $match) === 1 ? $match[1] : $value;
    }

    private static function isStreetType(string $word): bool
    {
        return in_array($word, ['улица', 'проспект', 'переулок', 'шоссе', 'набережная', 'бульвар', 'проезд'], true);
    }

    private static function streetWord(string $word): string
    {
        $word = trim($word, " \t\n\r\0\x0B-");
        return match ($word) {
            'ул', 'улица' => 'улица', 'просп', 'проспект', 'пр-т', 'пр-кт' => 'проспект',
            'пер', 'переулок' => 'переулок', 'ш', 'шоссе' => 'шоссе',
            'наб', 'набережная' => 'набережная', 'б-р', 'бул', 'бульвар' => 'бульвар',
            'пр-д', 'проезд' => 'проезд', default => $word,
        };
    }

    private static function componentsMatch(array $query, array $record): bool
    {
        if ($query['streetType'] !== '' && $record['streetType'] !== '' && $query['streetType'] !== $record['streetType']) return false;
        foreach (['house', 'corpus', 'building', 'entrance'] as $name) {
            if ($query[$name] !== '' && $query[$name] !== $record[$name]) return false;
        }
        return true;
    }

    private static function containsAny(array $values, string $query): bool
    {
        foreach ($values as $value) if ($value !== '' && str_contains($value, $query)) return true;
        return false;
    }

    private static function editDistance(string $left, string $right): int
    {
        $a = preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('//u', $right, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $previous = range(0, count($b));
        foreach ($a as $i => $charA) {
            $current = [$i + 1];
            foreach ($b as $j => $charB) $current[] = min($current[$j] + 1, $previous[$j + 1] + 1, $previous[$j] + ($charA === $charB ? 0 : 1));
            $previous = $current;
        }
        return $previous[count($b)];
    }
}
