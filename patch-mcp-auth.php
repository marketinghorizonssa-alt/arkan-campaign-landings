<?php
$f='/home/u878466595/domains/thehorizons.sa/public_html/google-mcp/index.php';
$d='/home/u878466595/.horizons-google-mcp';
$k=$d.'/mcp-path';
if(!is_dir($d)) mkdir($d,0700,true);
if(!is_file($k)) file_put_contents($k,bin2hex(random_bytes(24)));
chmod($k,0600);
$s=file_get_contents($f);
$old="if(\$p==='/mcp')mcp();";
$new="if(\$p==='/mcp'&&isset(\$_GET['k'])&&hash_equals(trim((string)@file_get_contents(GMCP_DIR.'/mcp-path')),(string)\$_GET['k']))mcp();";
if(strpos($s,$old)!==false){$s=str_replace($old,$new,$s);file_put_contents($f,$s);} 
echo trim(file_get_contents($k)),"\n";
passthru('php -l '.escapeshellarg($f));
