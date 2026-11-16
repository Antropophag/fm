<?php

declare(strict_types=1);

namespace FMonitor2\YiiRuntime\Controllers;

use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

final class HelpController extends PilotController
{
    private const FILES = [
        'index.html' => 'text/html; charset=UTF-8',
        'guide.css' => 'text/css; charset=UTF-8',
        'guide.js' => 'text/javascript; charset=UTF-8',
        'screenshots/01-login.webp' => 'image/webp',
        'screenshots/02-objects.webp' => 'image/webp',
        'screenshots/03-object-card.webp' => 'image/webp',
        'screenshots/04-object-edit.webp' => 'image/webp',
        'screenshots/05-team.webp' => 'image/webp',
        'screenshots/06-installer-picker.webp' => 'image/webp',
        'screenshots/07-calendar.webp' => 'image/webp',
        'screenshots/08-dashboard.webp' => 'image/webp',
        'screenshots/09-feedback.webp' => 'image/webp',
        'screenshots/10-construction-control.webp' => 'image/webp',
        'screenshots/11-checklist-mobile.webp' => 'image/webp',
        'screenshots/12-otiz-register.webp' => 'image/webp',
        'screenshots/14-original-form.webp' => 'image/webp',
        'screenshots/15-team-opened.webp' => 'image/webp',
    ];

    public function actionIndex(): Response
    {
        return $this->file('index.html');
    }

    public function actionFile(string $path): Response
    {
        return $this->file($path);
    }

    private function file(string $path): Response
    {
        $mime = self::FILES[$path] ?? null;
        $root = realpath(dirname(__DIR__, 3) . '/public/help');
        $candidate = is_string($root) ? $root . '/' . $path : '';
        $resolved = $candidate !== '' ? realpath($candidate) : false;
        if (!is_string($mime) || !is_string($root) || !is_string($resolved)
            || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)
            || !is_file($candidate) || is_link($candidate)) {
            throw new NotFoundHttpException();
        }
        $bytes = file_get_contents($resolved);
        if (!is_string($bytes)) {
            throw new \RuntimeException();
        }
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Length', (string) strlen($bytes));
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->content = $bytes;
        return $response;
    }
}
