<?php
$root='/home/u878466595/domains/hositee.com/public_html/corts-expert';
$clusters=[
'riyadh-lawyer'=>['محامي في الرياض ومكتب محاماة ومستشار قانوني','محامي الرياض، محامي في الرياض، مكتب محاماة، مكتب محامي، مكاتب محامين، مستشار قانوني، رقم محامي، محامي شاطر، أفضل مكتب محاماة.'],
'legal-consultation'=>['استشارة محامي واستشارات قانونية في الرياض','استشارة محامي، محامي استشارة قانونية، استشارات محامي، اسأل محامي، مكتب استشارات قانونية، محامي أون لاين، استشارات قانونية سعودية.'],
'labor-law'=>['قضايا عمالية ومكتب العمل','محامي قضايا عمالية، محامي عمالي، محامي المحكمة العمالية، استشارات قانونية مكتب العمل، محامي مكتب عمل، محامي التأمينات الاجتماعية.'],
'debt-collection-execution'=>['تحصيل ديون وتنفيذ ومطالبات مالية','تحصيل الديون، شركة تحصيل ديون، مكتب تحصيل ديون، محامي محكمة التنفيذ، محامي تنفيذ أحكام، محامي مطالبات مالية، محامي سند لأمر.'],
'family-inheritance'=>['أحوال شخصية وطلاق ونفقة وحضانة','محامي أحوال شخصية، محامي أحوال شخصية الرياض، محامي طلاق، استشارات الطلاق، محامي نفقة، محامي حضانة الأطفال.'],
'inheritance-estates'=>['مواريث وتركات','محامي ميراث، محامي متخصص في قضايا الميراث، محامي تقسيم ميراث، تقسيم التركة، حصر الورثة، قضايا الورث.'],
'business-commercial-law'=>['تجاري وعقود وتحكيم','محامي تجاري، محامي قضايا تجارية، محامي عقود، محامي توثيق عقود، توثيق العقود، محامي عقود تجارية، طلب تحكيم.'],
'company-law'=>['شركات وتأسيس وتصفية وإفلاس','تكلفة تأسيس شركة في السعودية للأجانب، فتح شركة في السعودية، محامي شركات، حجز اسم تجاري، سجل تجاري، محامي تصفية شركات، محامي إفلاس.'],
'trademark-intellectual-property'=>['علامات تجارية وملكية فكرية','تسجيل علامة تجارية، تسجيل علامة تجارية في السعودية، حجز علامة تجارية، رسوم تسجيل علامة تجارية في السعودية، الهيئة السعودية للملكية الفكرية، الملكية الفكرية.'],
'real-estate-law'=>['عقارات وتوثيق وصكوك','محامي عقاري، محامي عقار، محامي متخصص في العقارات، استشارات قانونية عقارية، توثيق عقد إيجار سكني، تسجيل الصك العيني.'],
'criminal-specialized'=>['جنائي وخدمات قانونية متخصصة','محامي جنائي، محامي جنائي بالرياض، محامي شركات النصب، محامي نصب واحتيال، محامي قضايا مخدرات، محامي جرائم إلكترونية، محامي أخطاء طبية، محامي بنوك.']
];
$marker='<!-- ADGROUP_KEYWORDS_V3 -->';
foreach($clusters as $slug=>$data){
  $p="$root/$slug/index.html";
  if(!file_exists($p)) continue;
  $s=file_get_contents($p);
  if(strpos($s,$marker)!==false) continue;
  [$title,$terms]=$data;
  $block="\n$marker\n<section class=\"section campaign-match\" aria-labelledby=\"campaign-match-$slug\"><div class=\"c\"><div class=\"section-label\">الخدمة المناسبة لبحثك</div><h2 id=\"campaign-match-$slug\">$title</h2><p>نستقبل طلبات مرتبطة بعبارات مثل: $terms</p></div></section>\n";
  $s=preg_replace('/<\/main>/', $block.'</main>', $s, 1);
  file_put_contents($p,$s);
}
$routing=['campaigns'=>[
'Search | Riyadh | High Intent'=>['محامي عام'=>'/riyadh-lawyer/','مكتب محاماة ومستشار'=>'/riyadh-lawyer/','تواصل مباشر / الأفضل'=>'/riyadh-lawyer/','استشارة محامي / مستشار'=>'/legal-consultation/','استشارات عامة / أونلاين'=>'/legal-consultation/'],
'Search | Riyadh | Core Cases'=>['قضايا عمالية ومكتب العمل'=>'/labor-law/','تحصيل ديون وتنفيذ ومطالبات'=>'/debt-collection-execution/','أحوال شخصية / طلاق / نفقة / حضانة'=>'/family-inheritance/','مواريث وتركات'=>'/inheritance-estates/','تجاري وعقود وتحكيم'=>'/business-commercial-law/'],
'Search | Riyadh | Specialized'=>['علامات تجارية وملكية فكرية'=>'/trademark-intellectual-property/','شركات / تأسيس / تصفية / إفلاس'=>'/company-law/','عقارات وتوثيق وصكوك'=>'/real-estate-law/','جنائي وخدمات قانونية متخصصة'=>'/criminal-specialized/']
]];
file_put_contents("$root/_campaign-routing.json", json_encode($routing, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
@unlink("$root/__inventory.txt");
file_put_contents("$root/.ads-lp-version","CORTS_RIYADH_LP_V3_20260917\n");
echo "done\n";
?>