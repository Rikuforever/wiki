<?php

/**
 * Register parser hooks
 */
class ProfileHooks{
	public static function onParserFirstCallInit(&$parser){
		//?!
		//$parser->disableCache();

		//<> 태그 적용
		$parser->setHook('sample', 'ProfileHooks::renderTag');
		//Profile 틀 적용
		$parser->setFunctionHook('profile', 'ProfileHooks::parserFunction');
		
		return true;
	}

	public static function parserFunction( $parser, $value){

		$value = '<sample>Test </sample>';

		$output = $value;

		//Tag Extensions 적용하고 싶다면 'noparse' => false
		return array($output, 'noparse' => false);
	}

	public static function renderTag($input, array $args, Parser $parser, PPFrame $frame){

		$output = $input.'success';

		return htmlspecialchars($output);
	}
}