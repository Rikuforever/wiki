<?php

/**
 * Register parser hooks
 */
class ProfileHooks{
	public static function onParserFirstCallInit(&$parser){
		//?!
		//$parser->disableCache();

		//Module 적용 (wgOut 쓰는거 문제 있다고 함. 문제 생기면 다른 방법 찾기)
		global $wgOut;
		$wgOut->addModules('ext.profile.button');
		
		//<> 태그 적용
		$parser->setHook('sample', 'ProfileHooks::renderTag');
		
		//Profile 틀 적용
		$parser->setFunctionHook('profile', 'ProfileHooks::parserFunction');
		
		return true;
	}

	public static function parserFunction( $parser, $value){

		//PHP관련(보통 DB) 변수 선언문서
		include __DIR__.'/resources/setup.php';

		//틀 RAW 문서
		$xml = file_get_contents(__DIR__.'/resources/test2.xml');
		var_dump($xml);
		$value = $xml;

		$output = $value;

		//Tag Extensions 적용하고 싶다면 'noparse' => false
		return array($output, 'noparse' => false);
	}

	public static function renderTag($input, array $args, Parser $parser, PPFrame $frame){
		
		//Tag RAW 문서
		$xml = file_get_contents(__DIR__.'/resources/test1.xml');
		$ret = $xml;	

		return $ret;
	}
}