<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$old="<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s);j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i;f.parentNode.insertBefore(j,f)})(window,document,'script','dataLayer','GTM-M9ZK36MB');</script>";
$new="<script async src=\"https://www.googletagmanager.com/gtag/js?id=AW-18435697489\"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','AW-18435697489');</script>";
$nos='<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-M9ZK36MB" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>';
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
$changed=0;$foundOld=0;$remaining=0;
foreach($it as $f){
  if(!$f->isFile() || strtolower($f->getExtension())!=='html') continue;
  $path=$f->getPathname();
  if(strpos($path,'/_gtag-direct-canary/')!==false) continue;
  $s=file_get_contents($path);$orig=$s;
  if(strpos($s,$old)!==false){$foundOld++;$s=str_replace($old,$new,$s);}
  $s=str_replace($nos,'',$s);
  $s=preg_replace('~/assets/app\\.js\\?v=[^\"\']+~','/assets/app.js?v=gtag-direct-v1-20260917',$s);
  if($s!==$orig){file_put_contents($path,$s);$changed++;}
  if(strpos($s,'GTM-M9ZK36MB')!==false)$remaining++;
}
echo "found_old=$foundOld\nchanged_html=$changed\nremaining_gtm_html=$remaining\n";
?>