<?php
declare(strict_types=1);
$base='https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/marketing-deploy/marketing-hub-v06/';
foreach(['sync_all_ycloud_numbers.php','flush_outbox.php'] as $f){
  $b=@file_get_contents($base.$f);
  if(!is_string($b)||!str_starts_with(ltrim($b),'<?php')){fwrite(STDERR,"fetch_failed:$f\n");exit(2);}
  file_put_contents(__DIR__.'/'.$f,$b,LOCK_EX);
}
passthru('php '.escapeshellarg(__DIR__.'/flush_outbox.php'),$code);
exit($code);
