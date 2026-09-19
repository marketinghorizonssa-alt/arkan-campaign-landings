<?php
$p='/home/u878466595/domains/thehorizons.sa/public_html/google-mcp/index.php';
$s=file_get_contents($p);
if($s===false){fwrite(STDERR,"read_failed\n");exit(1);}
$old="return tresult(gapi(\$m,\$u,isset(\$a['body'])&&is_array(\$a['body'])?\$a['body']:null));";
$new="\$gh=(\$h==='googleads.googleapis.com'&&mcc())?array('login-customer-id: '.mcc()):array();return tresult(gapi(\$m,\$u,isset(\$a['body'])&&is_array(\$a['body'])?\$a['body']:null,\$gh));";
if(strpos($s,$new)!==false){echo "already_patched\n";exit(0);}
if(strpos($s,$old)===false){fwrite(STDERR,"target_not_found\n");exit(2);}
$bak=$p.'.bak-20260919-login-header';
if(!file_exists($bak)) copy($p,$bak);
$s=str_replace("define('GMCP_VERSION','0.3.0');","define('GMCP_VERSION','0.3.1');",$s);
$s=str_replace($old,$new,$s);
file_put_contents($p,$s);
echo "patched\n";
?>