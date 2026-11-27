<?php

declare(strict_types=1);

namespace FMonitor2\YiiRuntime\Assets;

final class AssetVersion
{
    public static function file(string $name): string
    {
        if (basename($name) !== $name) throw new \InvalidArgumentException('Invalid asset name.');
        return $name . '?v=' . substr(self::digest($name, []), 0, 12);
    }

    private static function digest(string $name, array $seen): string
    {
        if (isset($seen[$name])) return '';
        $seen[$name] = true;
        $path = __DIR__ . '/' . $name;
        if ($name === 'fuse.min.js') $path=dirname(__DIR__,3).'/public/third-party/fuse/fuse.min.js';
        if ($name === 'shlz-behaviors.js') {
            $root=(string)(getenv('FMONITOR_SHLZ_UI_ROOT')?:dirname(__DIR__,4).'/shlz-ui');
            $path=$root.'/packages/behaviors/dist/browser.js';
        }
        $bytes=file_get_contents($path);
        if (!is_string($bytes)) throw new \RuntimeException('Asset unavailable.');
        if (!str_ends_with($name,'.js')) return hash('sha256',$bytes);
        $dependencies=[];
        if (preg_match_all("~(?:from\\s*|import\\s*)['\"](?:/pilot/assets/)?([A-Za-z0-9.-]+\\.js)['\"]~",$bytes,$matches)) {
            foreach(array_unique($matches[1])as$dependency)$dependencies[]=$dependency.':'.self::digest($dependency,$seen);
        }
        sort($dependencies);
        return hash('sha256',$bytes."\n".implode("\n",$dependencies));
    }
}
