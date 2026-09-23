<?php
$root='/home/u414915683/domains/almowahid.sa/public_html/ads';
$home=$root.'/index.html';
if(!is_file($home)){fwrite(STDERR,"home_missing\n");exit(1);}
$h=file_get_contents($home);
if(!preg_match('~<div class="hero-contact-actions">.*?</div>~s',$h,$m)){
  fwrite(STDERR,"home_actions_not_found\n");exit(2);
}
$actions=$m[0];
$files=glob($root.'/*/index.html');
$changed=[];$skipped=[];$failed=[];
foreach($files as $p){
  $s=file_get_contents($p);
  if($s===false){$failed[]=basename(dirname($p)).':read';continue;}
  if(strpos($s,'hero-points')===false){$skipped[]=basename(dirname($p)).':no-hero';continue;}
  if(strpos($s,'hero-contact-actions')!==false){$skipped[]=basename(dirname($p)).':already';continue;}
  $bak=$p.'.bak-hero-actions-20260923';
  if(!is_file($bak)) copy($p,$bak);
  $n=0;
  $s=preg_replace('~(<div class="hero-points">.*?</div>)~s','$1'.$actions,$s,1,$n);
  if($n!==1){$failed[]=basename(dirname($p)).':insert';continue;}
  file_put_contents($p,$s);
  $changed[]=basename(dirname($p));
}
echo json_encode(['changed'=>$changed,'skipped'=>$skipped,'failed'=>$failed],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";
?>