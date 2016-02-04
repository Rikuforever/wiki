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
	$var1 = '<h2>틀 테스트 : '.$value.'</h2>';
	$var2 = '[[Test1]]';
	
	$dbr = wfgetDB( DB_SLAVE );
	$var3 = $dbr->selectFieldValues(
	   'user',                  //테이블 이름
	   'user_name',             //필드 이름
	   '',                      //SQL문 LIKE 또는 CASEWHEN 등 사용
	   __METHOD__,              //고정(?)
	   array(),                 //ORDER BY 등 옵션 설정
	   array()                  //LEFT JOIN 등 join 컨디션 사용
  	);

  	$output = $var1.$var2.$var3[0];

	return $output;
	}
}