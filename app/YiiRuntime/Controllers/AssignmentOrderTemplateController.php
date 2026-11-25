<?php
declare(strict_types=1);
namespace FMonitor2\YiiRuntime\Controllers;

use FMonitor2\YiiRuntime\PreopeningResources;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Response;

final class AssignmentOrderTemplateController extends PreopeningController
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), ['verbs' => [
            'class' => VerbFilter::class,
            'actions' => ['generate' => ['GET', 'HEAD', 'POST']],
        ]]);
    }

    public function actionGenerate(string $id, string $orderId): Response
    {
        $id = $this->canonicalId($id); $orderId = $this->canonicalId($orderId);
        if ($id === null || $orderId === null) return $this->status(404);
        $resources = null;
        try {
            $resources = new PreopeningResources();
            if (!$this->processCap('assignment_order.composition.select') || $resources->portal()->authorizeActor($this->actor())['status'] !== 'allowed') return $this->status(403);
            $model = $resources->portal()->readSelectionPortal($id, $this->actor());
            if ($model['status'] !== 'found') return $this->domain($model);
            if (Yii::$app->request->isGet || Yii::$app->request->isHead) {
                $result = $resources->lastTemplate((int) $model['caseId'], $orderId, $this->actor());
                if ($result['status'] !== 'generated') return $this->domain($result);
                $bytes = $result['bytes'];
            } else {
                if (!$this->formMedia()) return $this->status(415);
                $fields=$this->templateFields();if($fields===null)return$this->status(400);
                $result = $resources->template((int) $model['caseId'], $orderId, $this->actor(), $fields['appendixStartDate']);
                if ($result['status'] !== 'generated') return $this->domain($result);
                $bytes = $result['bytes'];
            }
            $response = Yii::$app->response;
            $response->format = Response::FORMAT_RAW; $response->statusCode = 200;
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set('Content-Disposition', 'attachment; filename="assignment-order.pdf"');
            $response->headers->set('Content-Length', (string) strlen($bytes));
            $response->content = $bytes;
            return $response;
        } catch (\Throwable) {
            return $this->status(503, true);
        } finally {
            $resources?->close();
        }
    }

    private function formMedia(): bool
    {
        return preg_match('~^application/x-www-form-urlencoded(?:\s*;\s*charset=utf-8)?$~iD', (string) Yii::$app->request->contentType) === 1;
    }

    private function templateFields():?array
    {
        $body=Yii::$app->request->rawBody;if($body===''||str_ends_with($body,'&')||preg_match('/%(?![0-9A-Fa-f]{2})/',$body))return null;$names=[];foreach(explode('&',$body)as$pair){$name=rawurldecode(str_replace('+',' ',explode('=',$pair,2)[0]));if(isset($names[$name]))return null;$names[$name]=true;}parse_str($body,$fields);
        if(!is_array($fields)||array_keys($fields)!==['_csrf','appendixStartDate']||!is_string($fields['_csrf'])||!is_string($fields['appendixStartDate']))return null;
        $date=$fields['appendixStartDate'];$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);return$parsed!==false&&$parsed->format('Y-m-d')===$date?$fields:null;
    }

    public function actionMethod(string $id, string $orderId): Response
    {
        return $this->canonicalId($id) === null || $this->canonicalId($orderId) === null
            ? $this->status(404)
            : $this->methodNotAllowed('POST');
    }
}
