<?php
require __DIR__.'/../backend/vendor/autoload.php';
$app=require __DIR__.'/../backend/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$key=config('services.runcrate.key');$url=rtrim(config('services.runcrate.base_url'),'/');
$model=$argv[1]??config('services.runcrate.model');if(!in_array($model,config('services.runcrate.models'),true))throw new RuntimeException('Unsupported test model');
$start=microtime(true);
try{
 $res=Illuminate\Support\Facades\Http::withToken($key)->connectTimeout(8)->timeout(45)->post($url.'/chat/completions',[
  'model'=>$model,'max_tokens'=>400,'temperature'=>0,
  'messages'=>[['role'=>'system','content'=>'Return JSON only, no explanation: {"brand":string,"year":number,"price":number,"currency":string}. Extract only explicitly supplied facts.'],['role'=>'user','content'=>'Synthetic API test only: 2021 Toyota Corolla, asking GBP 16500.']],
 ]);
 $content=$res->json('choices.0.message.content','');$data=json_decode($content,true);
 $result=['http_status'=>$res->status(),'model'=>$model,'elapsed_seconds'=>round(microtime(true)-$start,2),'structured_response_valid'=>is_array($data)&&($data['brand']??null)==='Toyota'&&($data['year']??null)===2021,'provider_error_code'=>$res->successful()?null:$res->json('error.code')];
}catch(Throwable $error){$result=['connected'=>false,'error_type'=>get_class($error)];}
file_put_contents(__DIR__.'/../output/runcrate-inference-'.substr(hash('sha256',$model),0,10).'.json',json_encode($result,JSON_PRETTY_PRINT));echo json_encode($result);
