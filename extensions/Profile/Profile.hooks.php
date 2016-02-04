<?php

/**
 * Register parser hooks
 */
class ProfileHooks{
	public static function onParserFirstCallInit(&$parser){
		//{{#profile: a}}
		$parser->setFunctionHook('profile', 'ProfileHooks::parserFunction');
	
		return true;
	}

public static function parserFunction( $parser, $value){
	return $value;
	}
}