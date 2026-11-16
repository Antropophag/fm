# Production PHP dependencies

`deploy/dependencies/install` устанавливает production PHP-пакеты без изменения
исходных `composer.json` и `composer.lock`. В production image уже находится
закреплённый Composer из официального image по digest. Ручная сборка autoload или
установка Composer на host не требуется.

По умолчанию режим `auto` сначала выполняет обычный `composer install` из lock.
Если этот путь недоступен, installer скачивает только утверждённые прямые архивы,
проверяет их SHA-256 и размер и передаёт их тому же Composer через приватную копию
lock. Composer по-прежнему запускает разрешённый `yiisoft/yii2-composer`, создаёт
autoload и проверяет реальные PHP platform requirements. Частичный `vendor` не
публикуется.

Поддерживаемые режимы:

- `--mode auto` — Composer-first с автоматическим direct fallback;
- `--mode composer` — только обычный Composer path;
- `--mode direct` — только проверенные прямые архивы;
- `--check-manifest` — проверка manifest и lock без сети и установки;
- `--archive-dir DIR` — cache файлов `<sha256>.zip`. Отсутствующий файл скачивается,
  а существующий файл с неверным hash или размером отклоняется без замены.

Пример:

```sh
bash deploy/dependencies/install \
  --project /workspace/fmonitor-2 \
  --manifest deploy/dependencies/direct-dist-sha256.json \
  --composer /usr/local/bin/composer \
  --mode auto
```

Manifest версии 1 связывает точные SHA-256 `composer.json` и `composer.lock` и
содержит ровно все production packages из lock. Для каждого package закреплены
`name`, `version`, commit `reference`, archive `type`, прямой HTTPS `url`, `sha256`
и `byteSize`. URL разрешены только на `codeload.github.com`; owner/repository и
commit обязаны соответствовать исходному GitHub API dist URL в lock. Redirect и
произвольный mirror не принимаются.

## Обновление manifest

Manifest меняют только вместе с осознанным обновлением Composer inputs или после
подтверждённого изменения байтов GitHub archive:

1. Получить новый `composer.lock` штатным reviewed dependency change и проверить
   полный production graph, plugin policy и platform requirements.
2. Для каждого `dist.url` с точным 40-символьным commit reference определить его
   прямой `codeload.github.com/<owner>/<repo>/legacy.zip/<reference>` URL.
3. Скачать каждый архив по HTTPS в чистое приватное staging место. Зафиксировать
   SHA-256 и точный размер; проверить ZIP и соответствие package/reference.
4. Обновить оба Composer-input SHA-256 и полный отсортированный package list.
5. Выполнить `--check-manifest`, реальную direct установку без сети на Composer
   phase, проверку plugin paths/autoload/InstalledVersions/platform и независимое
   review новых manifest bytes. Старый hash нельзя автоматически заменять значением
   только что скачанного несовпавшего файла.

GitHub генерирует source archives по запросу. Для одного commit содержимое файлов
стабильно, но сжатые байты могут измениться при изменении compression settings, а
переименование repository меняет корневой каталог архива. Поэтому несовпадение
SHA-256 намеренно останавливает сборку и требует нового review; commit reference сам
по себе не заменяет проверку архивных байтов.

Direct fallback решает недоступность Composer repositories, но не делает source
build полностью автономным: base images, Debian/PECL packages, Microsoft ODBC,
Git clone и npm также требуют заранее доступных sources или сети. Для полностью
изолированного сервера предназначен проверенный release package с уже собранными
application и database images. Composer binary входит в application build image;
direct fallback не скачивает и не подменяет его.

Справочные первичные источники:

- [Composer install и точные версии lock](https://getcomposer.org/doc/03-cli.md#install-i)
- [Composer cache](https://getcomposer.org/doc/articles/caching.md)
- [Composer allow-plugins](https://getcomposer.org/doc/06-config.md#allow-plugins)
- [Composer platform check](https://getcomposer.org/doc/07-runtime.md#platform-check)
- [GitHub source archives и гарантии стабильности](https://docs.github.com/en/repositories/working-with-files/using-files/downloading-source-code-archives)
