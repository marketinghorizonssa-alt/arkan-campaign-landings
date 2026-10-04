<?php
if(PHP_SAPI!=='cli')exit;
$s='/home/u878466595/.marketing/biyaat';$marker=$s.'/websetup-validation-v2.done';
if(is_file($marker)){echo '{"ok":true,"already_done":true}';exit;}
$p='/home/u878466595/domains/hositee.com/public_html/biyaat/web-events-setup.php';
if(hash_file('sha256',$p)!=='eec69c708b10dbd0c2dc665a9195b1e93b232c79f0a6a43f3f8e9e42cdac467d')throw new RuntimeException('setup_changed');
$b=file_get_contents('https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/e006ca31c716a98fb486b23525b92cf4d78ed520/biyaat-20261004/web-events-setup-v2.php');
if(hash('sha256',$b)!=='d94a2801d62f4423dc58f17d7ab6dfa8a7f5b78e7bdee2e9cd6a003acf134a1e')throw new RuntimeException('download_mismatch');token_get_all($b,TOKEN_PARSE);
$predicate=trim(explode(';',explode('$valid=',$b,2)[1],2)[0]);
$tests=[[400,40002,'data: Must contain at least 1 item',true],[401,40001,'No permission to operate event source id',false],[400,40002,'Invalid access token',false],[200,0,'OK',true]];
foreach($tests as[$http,$code,$message,$expected]){$errno=0;$j=['message'=>$message];$valid=eval('return '.$predicate.';');if($valid!==$expected)throw new RuntimeException('validation_test_failed');}
copy($p,$s.'/backups/setup-before-validation-v2.php');file_put_contents($p.'.tmp',$b,LOCK_EX);chmod($p.'.tmp',0644);rename($p.'.tmp',$p);file_put_contents($marker,gmdate('c'));chmod($marker,0600);
echo json_encode(['ok'=>true,'validation_tests'=>4,'syntax_valid'=>true,'setup_sha'=>hash_file('sha256',$p),'same_setup_link_active'=>is_file($s.'/setup_token')]);
