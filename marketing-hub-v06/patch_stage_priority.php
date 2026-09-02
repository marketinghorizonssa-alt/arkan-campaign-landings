<?php
$p=__DIR__.'/webhook.php';
$s=file_get_contents($p);
$s=str_replace('qualEligible&&contains_any','qualEligible&&stage_rank($current)>=1&&contains_any',$s);
$s=str_replace('repliedToStaff&&stage_rank($current)<2','repliedToStaff&&stage_rank($current)>=1&&stage_rank($current)<2',$s);
file_put_contents($p,$s);
echo 'interested_first='.substr_count($s,'stage_rank($current)>=1&&contains_any')."\n";
echo 'qualified_after_interest='.substr_count($s,'repliedToStaff&&stage_rank($current)>=1&&stage_rank($current)<2')."\n";
