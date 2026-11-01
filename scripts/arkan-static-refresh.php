<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$commit=defined('ARKAN_STATIC_COMMIT')?(string)ARKAN_STATIC_COMMIT:'';
if(!preg_match('/^[a-f0-9]{40}$/',$commit))exit(2);
$root='/home/u958097356/domains/arkan2030.com/public_html';$home='/home/u958097356';
if(realpath($root)!==$root||!is_dir($root.'/crm')||!is_dir($root.'/sales'))exit(3);
$status=$root.'/assets/arkan-release.json';$old=json_decode((string)@file_get_contents($status),true);if(($old['commit']??'')===$commit)exit(0);
$base='https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/'.$commit.'/static-refresh/';
function as_fetch(string $url):string{
 if(!str_starts_with($url,'https://raw.githubusercontent.com/marketinghorizonssa-alt/arkan-campaign-landings/')&&!str_starts_with($url,'https://arkan-realestate-solutions.hositee.com/assets/'))throw new RuntimeException('origin_not_allowed');
 $ctx=stream_context_create(['http'=>['timeout'=>35,'follow_location'=>0,'header'=>"User-Agent: ARKAN-Authorized-Static-Deployment\r\n"]]);$data=@file_get_contents($url,false,$ctx);if(!is_string($data)||$data==='')throw new RuntimeException('download_failed');return $data;
}
function as_mkdir(string $dir,int $mode):void{if(!is_dir($dir)&&!mkdir($dir,$mode,true))throw new RuntimeException('mkdir_failed');if(is_link($dir))throw new RuntimeException('symlink_denied');}
$release=$home.'/private/arkan-static-20261101-'.substr($commit,0,12);$installed=[];$had=[];$log=['domain'=>'arkan2030.com','version'=>'20261101-search-5','commit'=>$commit,'state'=>'preparing','files'=>0,'updated_at'=>gmdate('c')];
try{
 as_mkdir($release,0700);$lock=fopen($release.'/install.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(0);
 $manifest=json_decode(as_fetch($base.'manifest.json'),true,512,JSON_THROW_ON_ERROR);if(($manifest['domain']??'')!=='arkan2030.com'||($manifest['version']??'')!=='20261101-search-5')throw new RuntimeException('manifest_scope_mismatch');
 foreach($manifest['files'] as $file){$path=(string)$file['path'];if(str_contains($path,'..')||str_starts_with($path,'/')||str_contains($path,'\\')||preg_match('~^(crm|sales|violations)(/|$)~i',$path)||!preg_match('~^(?:index\.html|robots\.txt|sitemap\.xml|assets/[A-Za-z0-9._-]+|[^/.]+/index\.html)$~u',$path)||str_ends_with($path,'.php'))throw new RuntimeException('path_not_allowed');
  $target=$root.'/'.$path;if(is_link($target))throw new RuntimeException('file_symlink_denied');$parent=dirname($target);if(is_dir($parent)&&!str_starts_with((string)realpath($parent),$root))throw new RuntimeException('path_escape');
  $source=($file['kind']??'')==='original_asset'?'https://arkan-realestate-solutions.hositee.com/assets/'.$file['source']:$base.'public/'.implode('/',array_map('rawurlencode',explode('/',$path)));$data=as_fetch($source);
  if(!hash_equals($file['sha256'],hash('sha256',$data)))throw new RuntimeException('hash_mismatch');as_mkdir($release.'/stage/'.dirname($path),0700);if(file_put_contents($release.'/stage/'.$path,$data,LOCK_EX)===false)throw new RuntimeException('stage_failed');
  $had[$path]=is_file($target);if($had[$path]){as_mkdir($release.'/backup/'.dirname($path),0700);if(!copy($target,$release.'/backup/'.$path))throw new RuntimeException('backup_failed');}
 }
 foreach($manifest['files'] as $file){$path=$file['path'];$target=$root.'/'.$path;as_mkdir(dirname($target),0755);$tmp=$target.'.arkan-tmp';if(!copy($release.'/stage/'.$path,$tmp)||!chmod($tmp,0644)||!rename($tmp,$target))throw new RuntimeException('install_failed');$installed[]=$path;if(!hash_equals($file['sha256'],hash_file('sha256',$target)))throw new RuntimeException('readback_failed');}
 $log['state']='deployed';$log['files']=count($installed);$log['backup_created']=true;$log['protected_applications_preserved']=is_dir($root.'/crm')&&is_dir($root.'/sales')&&is_dir($root.'/violations');$log['updated_at']=gmdate('c');file_put_contents($release.'/status.json',json_encode($log),LOCK_EX);as_mkdir($root.'/assets',0755);file_put_contents($status,json_encode($log,JSON_UNESCAPED_SLASHES),LOCK_EX);chmod($status,0644);flock($lock,LOCK_UN);fclose($lock);
}catch(Throwable $e){foreach(array_reverse($installed) as $path){$target=$root.'/'.$path;if(!str_starts_with($target,$root.'/'))continue;if($had[$path]??false)copy($release.'/backup/'.$path,$target);elseif(is_file($target))unlink($target);}$log['state']='failed_rolled_back';$log['error']=$e->getMessage();$log['updated_at']=gmdate('c');@file_put_contents($release.'/status.json',json_encode($log),LOCK_EX);@file_put_contents($status,json_encode($log),LOCK_EX);exit(4);}
