<?php
declare(strict_types=1);

function reject(): never
{
    fwrite(STDERR, "RECOVERY_BUNDLE_INVALID\n");
    exit(67);
}

if ($argc !== 9 || $argv[1] !== 'verify') {
    reject();
}

[$script, $verb, $bundle, $source, $image, $imageId, $config, $targetConfig, $sourceProject] = $argv;
if (!is_dir($bundle) || is_link($bundle) || !is_file($config) || is_link($config)
    || !is_file($targetConfig) || is_link($targetConfig)) {
    reject();
}
/** @return array<string,string> */
function environmentFile(string $path): array
{
    $result = [];
    foreach (preg_split('/\n/', (string)file_get_contents($path)) as $line) {
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!preg_match('/^([A-Z][A-Z0-9_]*)=(.*)$/D', $line, $m) || array_key_exists($m[1], $result)) reject();
        $value = $m[2];
        if ($value !== '' && ($value[0] === "'" || $value[0] === '"')) {
            if (strlen($value) < 2 || $value[strlen($value) - 1] !== $value[0]) reject();
            $value = substr($value, 1, -1);
        }
        $result[$m[1]] = $value;
    }
    return $result;
}
$bytes = file_get_contents($bundle . '/manifest.json');
$privateBytes = file_get_contents(dirname($config) . '/private-manifest.json');
if ($bytes === false || $privateBytes === false) {
    reject();
}
try {
    $manifest = json_decode($bytes, true, 64, JSON_THROW_ON_ERROR);
    $private = json_decode($privateBytes, true, 16, JSON_THROW_ON_ERROR);
} catch (Throwable) {
    reject();
}
if (!is_array($manifest) || ($manifest['formatVersion'] ?? null) !== 'fmonitor-runtime-backup-v1'
    || ($manifest['sourceCommit'] ?? null) !== $source
    || ($manifest['imageReference'] ?? null) !== $image
    || ($manifest['imageId'] ?? null) !== $imageId
    || !is_array($private)
    || array_keys($private) !== ['formatVersion', 'sourceProject', 'configSha256', 'runtimeManifestSha256']
    || $private['formatVersion'] !== 'fmonitor-production-private-v1'
    || $private['sourceProject'] !== $sourceProject
    || !hash_equals((string)$private['configSha256'], hash_file('sha256', $config))
    || !hash_equals((string)$private['runtimeManifestSha256'], hash('sha256', $bytes))) {
    reject();
}
$saved = environmentFile($config);
$target = environmentFile($targetConfig);
foreach (['FMONITOR_SESSION_INSTANCE', 'FMONITOR_YII_COOKIE_VALIDATION_KEY', 'FMONITOR_YII_IDENTITY_KEY'] as $key) {
    if (!isset($saved[$key], $target[$key]) || !hash_equals($saved[$key], $target[$key])) reject();
}
foreach (['database.sql' => 'database', 'state.tar' => 'state'] as $file => $section) {
    $path = $bundle . '/' . $file;
    $expected = $manifest[$section]['sha256'] ?? null;
    if (!is_string($expected) || !preg_match('/^[0-9a-f]{64}$/D', $expected)
        || !is_file($path) || is_link($path) || !hash_equals($expected, hash_file('sha256', $path))) {
        reject();
    }
}
fwrite(STDOUT, "RECOVERY_BUNDLE_VERIFIED\n");
