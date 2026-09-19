<?php
$p='/home/u878466595/domains/thehorizons.sa/public_html/google-mcp/index.php';
$s=file_get_contents($p);
if($s===false){fwrite(STDERR,"read_failed\n");exit(1);}
if(strpos($s,"function gadsfix(")!==false){echo "already_patched\n";exit(0);}
$anchor="function gapi(\$method,\$url,\$json=null,\$headers=array()){";
$fn="function gadsfix(\$x){if(!is_array(\$x))return \$x;\$o=array();foreach(\$x as \$k=>\$v){if(\$k==='maximizeConversions'&&is_array(\$v)&&count(\$v)===0)\$o[\$k]=(object)array();else \$o[\$k]=gadsfix(\$v);}return \$o;}\n";
if(strpos($s,$anchor)===false){fwrite(STDERR,"anchor_not_found\n");exit(2);}
$bak=$p.'.bak-20260919-empty-protobuf';
if(!file_exists($bak)) copy($p,$bak);
$s=str_replace("define('GMCP_VERSION','0.3.1');","define('GMCP_VERSION','0.3.2');",$s);
$s=str_replace($anchor,$fn.$anchor,$s);
$old="\$gh=(\$h==='googleads.googleapis.com'&&mcc())?array('login-customer-id: '.mcc()):array();return tresult(gapi(\$m,\$u,isset(\$a['body'])&&is_array(\$a['body'])?\$a['body']:null,\$gh));";
$new="\$gh=(\$h==='googleads.googleapis.com'&&mcc())?array('login-customer-id: '.mcc()):array();\$gb=isset(\$a['body'])&&is_array(\$a['body'])?\$a['body']:null;if(\$h==='googleads.googleapis.com'&&is_array(\$gb))\$gb=gadsfix(\$gb);return tresult(gapi(\$m,\$u,\$gb,\$gh));";
if(strpos($s,$old)===false){fwrite(STDERR,"callsite_not_found\n");exit(3);}
$s=str_replace($old,$new,$s);
file_put_contents($p,$s);
echo "patched\n";
?>