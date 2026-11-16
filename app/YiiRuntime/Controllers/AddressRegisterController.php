<?php

declare(strict_types=1);

namespace FMonitor2\YiiRuntime\Controllers;

use FMonitor2\AddressRegister\ColumnPreferences;
use FMonitor2\AddressRegister\Columns;
use FMonitor2\AddressRegister\PreferenceConflict;
use FMonitor2\YiiRuntime\InstallationProcessFactory;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

final class AddressRegisterController extends PilotController
{
    public $layout = false;

    public function behaviors(): array
    {
        return [
            'verbs' => ['class' => VerbFilter::class, 'actions' => [
                'index' => ['GET', 'HEAD'],
                'preferences' => ['POST'],
            ]],
            'access' => ['class' => AccessControl::class, 'rules' => [['allow' => true, 'roles' => ['@']]], 'denyCallback' => function (): void {
                Yii::$app->user->setReturnUrl(Yii::$app->request->url);
                Yii::$app->response->statusCode = 303;
                Yii::$app->response->headers->set('Location', '/pilot/login');
            }],
        ];
    }

    public function actionIndex(): string|Response
    {
        $actor = $this->authorizedActor();
        [$q, $status, $page] = $this->filters();
        try {
            $register = InstallationProcessFactory::addressRegister(
                Yii::$app->db,
                (string) (getenv('FMONITOR_PROCESS_TABLE_PREFIX') ?: ''),
                (string) (getenv('FMONITOR_LEGACY_TABLE_PREFIX') ?: ''),
            )->read($actor, $q, $status, $page);
            $preferences = $this->preferences()->read($actor);
            $response = $this->render('@app/app/YiiRuntime/Views/address-register', $register + [
                'identity' => Yii::$app->user->identity,
                'catalogue' => Columns::catalogue(),
                'defaults' => Columns::defaults(),
                'preferences' => $preferences,
                'q' => $q,
                'status' => $status,
                'page' => $page,
            ]);
            Yii::$app->response->headers->set('Cache-Control', 'no-store');
            return $response;
        } catch (\InvalidArgumentException|\OutOfRangeException) {
            throw new BadRequestHttpException();
        } catch (\DomainException) {
            throw new ForbiddenHttpException();
        } catch (\Throwable) {
            return $this->unavailable();
        }
    }

    public function actionPreferences(): Response
    {
        $actor = $this->authorizedActor();
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_JSON;
        $response->headers->set('Cache-Control', 'no-store');
        try {
            $fields = Yii::$app->request->post();
            if (is_array($fields) && array_key_exists('widths', $fields)) {
                $response->statusCode = 422;
                $response->data = ['error' => 'preferences_client_outdated', 'action' => 'reload'];
                return $response;
            }
            if (!is_array($fields) || array_diff(array_keys($fields), ['_csrf', 'expectedRevision', 'visible']) !== []
                || array_diff(['expectedRevision', 'visible'], array_keys($fields)) !== []) {
                throw new \InvalidArgumentException();
            }
            foreach (['expectedRevision', 'visible'] as $key) {
                if (!is_string($fields[$key])) throw new \InvalidArgumentException();
            }
            if (preg_match('/^(?:0|[1-9][0-9]{0,18})$/D', $fields['expectedRevision']) !== 1) throw new \InvalidArgumentException();
            $visible = json_decode($fields['visible'], true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($visible) || !array_is_list($visible)) throw new \InvalidArgumentException();
            $response->data = $this->preferences()->save($actor, (int) $fields['expectedRevision'], $visible);
            return $response;
        } catch (PreferenceConflict) {
            $response->statusCode = 409;
            $response->data = ['error' => 'preferences_conflict'];
            return $response;
        } catch (\JsonException|\InvalidArgumentException) {
            $response->statusCode = 422;
            $response->data = ['error' => 'invalid_preferences'];
            return $response;
        } catch (\DomainException) {
            throw new ForbiddenHttpException();
        } catch (\Throwable) {
            $response->statusCode = 503;
            $response->headers->set('Retry-After', '60');
            $response->data = ['error' => 'preferences_unavailable'];
            return $response;
        }
    }

    private function authorizedActor(): int
    {
        $actor = (int) Yii::$app->user->id;
        if (!Yii::$app->canonicalAccess->checkAccess($actor, 'objects.read')) throw new ForbiddenHttpException();
        return $actor;
    }

    private function preferences(): ColumnPreferences
    {
        $root = (string) (getenv('FMONITOR_SESSION_STATE_ROOT') ?: '/home/fmonitor/.local/state/fmonitor2');
        return new ColumnPreferences($root . '/address-register-preferences', static fn(int $actor): bool =>
            Yii::$app->canonicalAccess->checkAccess($actor, 'objects.read'));
    }

    /** @return array{string,string,int} */
    private function filters(): array
    {
        $values = ['q' => '', 'status' => '', 'page' => '1'];
        $seen = [];
        $raw = (string) ($_SERVER['QUERY_STRING'] ?? '');
        foreach ($raw === '' ? [] : explode('&', $raw) as $part) {
            if ($part === '' || !str_contains($part, '=') || preg_match('/%(?![0-9A-Fa-f]{2})/', $part) === 1) throw new BadRequestHttpException();
            [$key, $value] = explode('=', $part, 2);
            $key = rawurldecode($key);
            if (!array_key_exists($key, $values) || isset($seen[$key]) || str_contains($key, '[')) throw new BadRequestHttpException();
            $seen[$key] = true;
            $values[$key] = rawurldecode(str_replace('+', ' ', $value));
        }
        $values['q'] = trim($values['q']);
        $statuses = ['', 'no_case', 'needs_assignment_order', 'ready_to_open', 'installation', 'document_closeout', 'completed', 'needs_assignment_change'];
        if (mb_strlen($values['q']) > 120 || !in_array($values['status'], $statuses, true)
            || preg_match('/^[1-9][0-9]*$/D', $values['page']) !== 1 || strlen($values['page']) > 18) {
            throw new BadRequestHttpException();
        }
        return [$values['q'], $values['status'], (int) $values['page']];
    }

    private function unavailable(): Response
    {
        $response = Yii::$app->response;
        $response->statusCode = 503;
        $response->format = Response::FORMAT_RAW;
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Retry-After', '60');
        $response->content = "Service unavailable.\n";
        return $response;
    }
}
