<?php
$wgAutoloadClasses['MySpecialPageTemplate'] = __DIR__ . '/MySpecialPageTemplate.php';
$wgAutoloadClasses['SpecialMySpecialPage'] = __DIR__.'/MySpecialPage.body.php';

$wgSpecialPages['Test'] =  'SpecialMySpecialPage';