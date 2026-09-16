<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$file = __DIR__ . '/lead_pool_worker.php';
if (!is_file($file)) throw new RuntimeException('worker_missing');
$s = (string)file_get_contents($file);
if (str_contains($s, '$effectivePrev=')) { echo "already_patched\n"; return; }
$old1 = <<<'OLD'
        $prev=is_array($previous[$leadId]??null)?$previous[$leadId]:[]; $prevStage=lp_s($prev['stage']??'');
        if(!$firstRun && $prevStage!=='' && $prevStage!==$stage && in_array($stage,$exportStages,true)){
            $eventId='ev_' . substr(lp_hmac($secret,$clientId,$leadId,$prevStage,$stage,lp_s($r['last_seen_at'])),0,32);
OLD;
$new1 = <<<'NEW'
        $prev=is_array($previous[$leadId]??null)?$previous[$leadId]:[]; $prevStage=lp_s($prev['stage']??'');
        $effectivePrev=$prevStage!==''?$prevStage:'new';
        $sentStages=is_array($prev['sent_stages']??null)?array_values(array_unique(array_map('strval',$prev['sent_stages']))):[];
        if($prevStage!=='' && !array_key_exists('sent_stages',$prev) && in_array($prevStage,$exportStages,true)) $sentStages[]=$prevStage;
        $shouldRoute=!$firstRun && $effectivePrev!==$stage && in_array($stage,$exportStages,true) && !in_array($stage,$sentStages,true);
        if($shouldRoute){
            $eventId='ev_' . substr(lp_hmac($secret,$clientId,$leadId,$effectivePrev,$stage,lp_s($r['last_seen_at'])),0,32);
NEW;
if (!str_contains($s,$old1)) throw new RuntimeException('transition_anchor_not_found');
$s = str_replace($old1,$new1,$s);
$old2 = <<<'OLD'
            }
        }
        $nextState[$leadId]=['stage'=>$stage,'source'=>$source,'last_seen_at'=>$r['last_seen_at'],'updated_at'=>gmdate('c')];
OLD;
$new2 = <<<'NEW'
            }
            $sentStages[]=$stage;$sentStages=array_values(array_unique($sentStages));
        }
        if($firstRun && in_array($stage,$exportStages,true) && !in_array($stage,$sentStages,true))$sentStages[]=$stage;
        $nextState[$leadId]=['stage'=>$stage,'source'=>$source,'last_seen_at'=>$r['last_seen_at'],'sent_stages'=>array_values(array_unique($sentStages)),'updated_at'=>gmdate('c')];
NEW;
if (!str_contains($s,$old2)) throw new RuntimeException('state_anchor_not_found');
$s = str_replace($old2,$new2,$s);
$tmp=$file.'.tmp.'.bin2hex(random_bytes(4));
if(file_put_contents($tmp,$s,LOCK_EX)===false)throw new RuntimeException('write_failed');
chmod($tmp,0600);
if(!rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('rename_failed');}
echo "patched\n";
