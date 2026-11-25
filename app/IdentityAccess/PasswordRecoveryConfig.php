<?php
declare(strict_types=1);
namespace FMonitor2\IdentityAccess;

final class PasswordRecoveryConfig
{
    public function __construct(public readonly string $key,public readonly string $keyId,public readonly string $publicBaseUrl)
    {
        $parts=parse_url($publicBaseUrl);$valid=filter_var($publicBaseUrl,FILTER_VALIDATE_URL)!==false&&is_array($parts)&&($parts['scheme']??null)==='https'&&is_string($parts['host']??null)&&$parts['host']!==''&&!isset($parts['user'])&&!isset($parts['pass'])&&!isset($parts['query'])&&!isset($parts['fragment'])&&(!isset($parts['path'])||$parts['path']===''||$parts['path']==='/')&&(!isset($parts['port'])||((int)$parts['port']>=1&&(int)$parts['port']<=65535));
        if(strlen($key)!==32||preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$keyId)!==1||!$valid)throw new \RuntimeException('Password recovery configuration unavailable.');
    }
    public static function from(array $value):self{return new self((string)($value['key']??''),(string)($value['keyId']??''),rtrim((string)($value['publicBaseUrl']??''),'/'));}
}
