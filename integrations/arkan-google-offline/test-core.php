<?php
require __DIR__.'/core.php';
function check(bool $x,string $m){if(!$x)throw new Exception($m);}
$now=time();$o=[];$a=ag_observe($o,'id1','Contacted','',$now,false);check($a['time']===null,'historical status time fabricated');
$a=ag_observe($o,'id1','Interested','',$now+20,false);check($a['time']!==null&&$a['write_time']!==null,'future change missing');
$b=ag_observe($o,'id1','Interested','',$now+40,false);check($a['time']===$b['time'],'repeated observation changed time');
check(ag_stage('Unqualified')===null&&ag_negative('Unqualified'),'negative promoted');
check(ag_stage('Contacted')==='contacted'&&ag_stage('Contacted')!=='interested','contacted inflated');
check(ag_click(['gclid'=>'local-test-click'])===[],'test click admitted');
check(ag_event('id','qualified',['gclid'=>'a-valid-click-value'],gmdate('c',$now+10),$now)===null,'future event admitted');
check(ag_event('id','qualified',['gclid'=>'a-valid-click-value'],gmdate('c',$now-90*86400),$now)===null,'expired event admitted');
$e1=ag_event('id','qualified',['gclid'=>'a-valid-click-value'],gmdate('c',$now),$now);$e2=ag_event('id','qualified',['gclid'=>'a-valid-click-value'],gmdate('c',$now+1),$now+1);check($e1['transactionId']===$e2['transactionId'],'dedup depends on evaluation time');
check(!isset($e1['userData'])&&!isset($e1['conversionValue']),'unknown user consent/value invented');
echo 'PASS: historical times, observed transitions, repeat dedup, negative stages, test clicks, expiry, future times, stable event IDs, minimal payload';
