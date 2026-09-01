<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$id=(string)($argv[1]??'');if(!preg_match('/^yc_[a-z0-9_]{4,80}$/',$id)||$id==='yc_legacy'){fwrite(STDERR,"usage: php init_setup.php yc_connection_id\n");exit(2);}
$secureDir=dirname(__DIR__,4).'/.marketing/ycloud/'.$id;if(!is_dir($secureDir)&&!mkdir($secureDir,0700,true)){fwrite(STDERR,"cannot_create_secure_dir\n");exit(1);}@chmod($secureDir,0700);$token=bin2hex(random_bytes(24));$tokenFile=$secureDir.'/setup_token';if(file_put_contents($tokenFile,$token."\n",LOCK_EX)===false){fwrite(STDERR,"cannot_write_token\n");exit(1);}@chmod($tokenFile,0600);echo 'https://marketing.hositee.com/connect.php?c='.rawurlencode($id).'&token='.$token."\n";
