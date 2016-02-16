<?php

$wgHooks['ParserFirstCallInit'][] = 'wfSampleSetup';

function wfSampleSetup(Parser $parser){
	$parser->setHook('sample','wfSampleRender');
	return true;
} 

/*
function wfSampleRender($input,array $args, Parser $parser, PPFrame $frame){
	$attr = array();
	foreach ($args as $name => $value) {
		$attr[] = '<strong>'.htmlspecialchars($name).'</strong> = '.htmlspecialchars($value);
		return implode('<br />', $attr)."\n\n".htmlspecialchars($input);
	}
}
*/

function wfSampleRender($input,array $args, Parser $parser, PPFrame $frame){
	return htmlspecialchars($input);
}