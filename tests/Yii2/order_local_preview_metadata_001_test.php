<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use FMonitor2\YiiRuntime\Models\OriginalMetadata;
// ORDER-LOCAL-PREVIEW-001: external number transport boundaries.
$fields=['csrfToken'=>'test-csrf','requestId'=>'14141414-1414-4414-8414-000000000001','mode'=>'initial','appendixStartDate'=>'2026-12-01','externalDocumentNumber'=>str_repeat('Я',255),'rootOriginalId'=>null,'targetRevisionId'=>null,'expectedCurrentRevisionId'=>null,'correctionReason'=>null,'originalFilename'=>'signed.pdf'];
$encode=static fn(array $f):string=>base64_encode(json_encode($f,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS|JSON_THROW_ON_ERROR));
assertSameValue($fields,OriginalMetadata::fromHeader($encode($fields))->fields,'255 Unicode characters accepted without byte truncation');
foreach(['', '   ',str_repeat('Я',256), 123, null] as $number){$bad=$fields;$bad['externalDocumentNumber']=$number;$denied=false;try{OriginalMetadata::fromHeader($encode($bad));}catch(InvalidArgumentException){$denied=true;}assertSameValue(true,$denied,'invalid number refused');}
foreach(['documentDate'=>'2000-01-01','compositionConfirmed'=>true] as $key=>$value){$bad=$fields;$bad[$key]=$value;$denied=false;try{OriginalMetadata::fromHeader($encode($bad));}catch(InvalidArgumentException){$denied=true;}assertSameValue(true,$denied,'client cannot reintroduce '.$key);}
echo "PASS ORDER-LOCAL-PREVIEW-001 number metadata boundaries\n";
