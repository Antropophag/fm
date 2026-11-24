<?php
declare(strict_types=1);
namespace FMonitor2\IdentityAccess;

final class PasswordPolicy
{
    public static function error(string $password,string $confirmation,string $email):?string
    {
        if($password!==$confirmation)return 'Пароли не совпадают.';
        if(!mb_check_encoding($password,'UTF-8'))return 'Пароль содержит недопустимые символы.';
        $length=mb_strlen($password,'UTF-8');
        if($length<10||$length>200)return 'Пароль должен содержать от 10 до 200 символов.';
        if(preg_match('/[a-z]/',$password)!==1||preg_match('/[A-Z]/',$password)!==1||preg_match('/[0-9]/',$password)!==1||preg_match('~[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]~',$password)!==1)return 'Пароль должен содержать строчную и заглавную латинские буквы, цифру и спецсимвол.';
        $local=explode('@',$email,2)[0];
        return strlen($local)>=4&&stripos($password,$local)!==false?'Пароль не должен содержать часть email до знака @.':null;
    }
}
