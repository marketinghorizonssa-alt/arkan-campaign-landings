<?php
if(PHP_SAPI!=='cli'){exit;}
$s='/home/u878466595/.marketing/biyaat';$root='/home/u878466595/domains/hositee.com/public_html';$marker=$s.'/websetup-v2.done';
if(is_file($marker)){echo json_encode(['ok'=>true,'already_deployed'=>true]);exit;}
$base='https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/dc7c399dece734e56b4f8c7bc26369018d18e879/biyaat-20261004/';
$files=['biyaat_quality_worker_v2.php'=>['marketing/biyaat_quality_worker.php','2a2d883476c5fab4c2b0fbd908535084da40b6cb0437bbb999cf59bc6f92439c'],'web-events-setup.php'=>['biyaat/web-events-setup.php','eec69c708b10dbd0c2dc665a9195b1e93b232c79f0a6a43f3f8e9e42cdac467d']];$bodies=[];
foreach($files as$n=>$v){$b=file_get_contents($base.$n);if(hash('sha256',$b)!==$v[1])throw new RuntimeException('download_mismatch');token_get_all($b,TOKEN_PARSE);$bodies[$n]=$b;}
$dest=$root.'/marketing/biyaat_quality_worker.php';if(hash_file('sha256',$dest)!=='bc5a70ae3086e76a4665a5bf1fb144ec2bb73f6a0bcc4a93858d0dbd78241c57')throw new RuntimeException('worker_changed');
copy($dest,$s.'/backups/worker-before-websetup.php');
foreach($files as$n=>$v){$p=$root.'/'.$v[0];file_put_contents($p.'.tmp',$bodies[$n],LOCK_EX);chmod($p.'.tmp',0644);rename($p.'.tmp',$p);}
$nonce=bin2hex(random_bytes(32));file_put_contents($s.'/setup_token',$nonce,LOCK_EX);chmod($s.'/setup_token',0600);file_put_contents($s.'/setup_expires',(string)(time()+86400),LOCK_EX);chmod($s.'/setup_expires',0600);
file_put_contents($marker,gmdate('c'));chmod($marker,0600);echo json_encode(['ok'=>true,'worker_updated'=>true,'setup_url'=>'https://biyaat.hositee.com/web-events-setup.php?setup='.$nonce,'expires_hours'=>24],JSON_UNESCAPED_SLASHES);
