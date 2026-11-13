# Production FMonitor

Постоянная точка входа, предоставленная владельцем (контакт: Гришин Тимофей Сергеевич, сообщение 21.09.2026 10:51). Сохранено по поручению владельца 13.11.2026. Пароли, ключи и токены здесь не хранятся.

- Приложение: https://fmonitor.antropophag.ru/
- Внутренняя сеть: `ssh antropophag@192.168.0.148`
- Внешний доступ: `ssh -p 22222 antropophag@80.251.125.77`
- После SSH на Windows-хост: `wsl -d Ubuntu-24.04`
- Checkout приложения внутри WSL: `/home/antropophag/code/fmonitor-2`.
- Runtime PHP-контейнер: `fm2-local-antropophag-php-1`, приложение внутри: `/workspace/fmonitor-2`.
- Сервисы того же стека: `fm2-local-antropophag-web-1`, `fm2-local-antropophag-db-1`, `fm2-local-antropophag-jobs-worker-1`, `fm2-local-antropophag-jobs-scheduler-1`.

13.11.2026 подтверждён внутренний SSH и запуск Ubuntu-24.04; имя хоста `DESKTOP-7EHPNJR`. Внешний SSH указан владельцем, отдельно в этой задаче не проверен.

Для автоматизации без сложного Windows shell quoting работает `ssh ... 'wsl -d Ubuntu-24.04 -- python3 -'` с программой на stdin. Перед изменениями сверять фактические host/container bytes: checkout и контейнер могут содержать ещё не интегрированные production-first правки. Сохранять их и private rollback вне репозитория. Наличие адресов доступа само по себе не разрешает выкладку или изменение данных; scope задаёт текущее поручение.

Последние подтверждённые поставки и backup evidence: [месячная сессия](month-session-20261113.md), [начало по распоряжению](actual-start-from-assignment-order-20261113.md).
