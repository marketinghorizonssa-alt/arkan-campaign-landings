<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));$n=0;
foreach($it as $f){$p=$f->getPathname();$suffix='.pre-hero-webp-20260917';if(substr($p,-strlen($suffix))===$suffix){$dest=substr($p,0,-strlen($suffix));copy($p,$dest);$n++;}}
file_put_contents("$root/.hero-image-version","ROLLED_BACK_TO_JPEG_SYNC_20260917\n");echo "restored=$n\n";
?>