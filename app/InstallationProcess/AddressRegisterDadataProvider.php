<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class AddressRegisterDadataProvider implements AddressRegisterAddressProvider
{
    private const ENDPOINT = 'https://cleaner.dadata.ru/api/v1/clean/address';
    private const POLICY = ['connectTimeout'=>5,'totalTimeout'=>15,'maxResponseBytes'=>1048576,'verifyTls'=>true,'followRedirects'=>false];
    private readonly \Closure $transport;

    public function __construct(private readonly string $apiKey, private readonly string $secretKey, ?callable $transport = null)
    {
        $this->transport = $transport === null ? self::nativeTransport(...) : $transport(...);
    }

    public function clean(string $source): array
    {
        $query = preg_match('/^(?:(?:г(?:ород)?\.?\s*)?)москва(?=\s|,|$)/iu',trim($source))===1
            ? $source
            : 'г Москва, '.$source;
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Token '.$this->apiKey, 'X-Secret: '.$this->secretKey];
        $body = json_encode([$query], JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        try {
            $response = ($this->transport)(self::ENDPOINT, $headers, $body, self::POLICY);
        } catch (AddressRegisterProviderFailure $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new AddressRegisterProviderFailure('PROVIDER_OUTCOME_UNKNOWN', false, true);
        }
        if (!is_array($response) || !isset($response['status'], $response['body']) || !is_int($response['status']) || !is_string($response['body'])) {
            throw new AddressRegisterProviderFailure('PROVIDER_RESPONSE_INVALID', false, false);
        }
        $status = $response['status'];
        if ($status !== 200) {
            $reason=match(true){$status===401||$status===403=>'PROVIDER_AUTH_FAILED',$status===429=>'PROVIDER_RATE_LIMIT',default=>'PROVIDER_UNAVAILABLE'};
            throw new AddressRegisterProviderFailure($reason, $status===429||($status>=500&&$status<=599), false);
        }
        if (strlen($response['body']) > self::POLICY['maxResponseBytes']) {
            throw new AddressRegisterProviderFailure('PROVIDER_RESPONSE_INVALID', false, false);
        }
        try { $decoded = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new AddressRegisterProviderFailure('PROVIDER_RESPONSE_INVALID', false, false); }
        if (!is_array($decoded) || count($decoded) !== 1 || !is_array($decoded[0]) || $decoded[0] === []) {
            throw new AddressRegisterProviderFailure('PROVIDER_RESPONSE_INVALID', false, false);
        }
        return $decoded[0];
    }

    /** @return array{status:int,body:string} */
    private static function nativeTransport(string $url, array $headers, string $body, array $policy): array
    {
        if (!function_exists('curl_init')) throw new \RuntimeException('transport unavailable');
        $curl = curl_init($url); if ($curl === false) throw new \RuntimeException('transport unavailable');
        $response = ''; $overflow = false;
        curl_setopt_array($curl, [
            CURLOPT_POST=>true, CURLOPT_HTTPHEADER=>$headers, CURLOPT_POSTFIELDS=>$body, CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_CONNECTTIMEOUT=>$policy['connectTimeout'], CURLOPT_TIMEOUT=>$policy['totalTimeout'],
            CURLOPT_SSL_VERIFYPEER=>$policy['verifyTls'], CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_FOLLOWLOCATION=>$policy['followRedirects'], CURLOPT_MAXREDIRS=>0,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION=>static function($handle, string $chunk) use (&$response, &$overflow, $policy): int {
                if (strlen($response) + strlen($chunk) > $policy['maxResponseBytes']) { $overflow = true; return 0; }
                $response .= $chunk; return strlen($chunk);
            },
        ]);
        $ok = curl_exec($curl); $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($overflow) throw new AddressRegisterProviderFailure('PROVIDER_RESPONSE_INVALID',false,false);
        if ($ok !== true) throw new \RuntimeException('transport failed');
        return ['status'=>$status, 'body'=>$response];
    }
}
