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

		$value = '<sample>Test </sample>';

		$output = $value;

		//Tag Extensions 적용하고 싶다면 'noparse' => false
		return array($output, 'noparse' => false);
	}

	public static function renderTag($input, array $args, Parser $parser, PPFrame $frame){
		$dbr = wfgetDB( DB_SLAVE );
    	$var = $dbr->selectFieldValues(
	       'user',                  //í…Œì´ë¸” ì´ë¦„
	       'user_name',             //í•„ë“œ ì´ë¦„
	       '',                      //SQLë¬¸ LIKE ë˜ëŠ” CASEWHEN ë“± ì‚¬ìš©
	       __METHOD__,              //ê³ ì •(?)
	       array(),                 //ORDER BY ë“± ì˜µì…˜ ì„¤ì •
	       array()                  //LEFT JOIN ë“± join ì»¨ë””ì…˜ ì‚¬ìš©
	    );
		

		$ret = '<table class="wtable">';
		$ret .= '<tr>';
		$ret .= '<td>Feedback'.$var[0].'</td>';
		$ret .= '<td><input id="inp001" type="text" /></td>';
		$ret .= '</tr>';
		$ret .= '<tr>';
		$ret .= '<td>Previous feedback given on:</td>';
		$ret .= '<td>(never)</td>';
		$ret .= '</tr>';
		$ret .= '<tr>';
		$ret .= '<td>Clear history</td>';
		$ret .= '<td><input id="chk001" type="checkbox" /></td>';
		$ret .= '</tr>';
		$ret .= '</table>';
		$ret .= '<tr>';
		$ret .= '<td align="center" colspan=2><input id="btn001" type="button" value="Submit"></td>';
		$ret .= '</tr>';
		return $ret;
	}
}