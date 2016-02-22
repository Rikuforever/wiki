<?php
# Alert the user that this is not a valid access point to MediaWiki if they try to access the special pages file directly.
if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/MyExtension/MyExtension.php" );
EOT;
	exit( 1 );
}

$wgExtensionCredits['Profile'][] = array(
	'path' => __FILE__,
	'name' => 'Profile',
	'author' => 'HJ',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Profile',
	'descriptionmsg' => 'profile-desc',
	'version' => '0.0.0',
);

// Register files
$wgAutoloadClasses['ProfileHooks'] = __DIR__. '/Profile.hooks.php';

$wgMessagesDirs['Profile'] = __DIR__ . "/i18n"; # Location of localisation files (Tell MediaWiki to load them)
$wgExtensionMessagesFiles['ProfileAlias'] = __DIR__ . '/Profile.alias.php'; # Location of an aliases file (Tell MediaWiki to load it)

// Register hooks
$wgHooks['ParserFirstCallInit'][] = 'ProfileHooks::onParserFirstCallInit';

// Register Modules
$wgResourceModules['ext.profile.button'] = array(
	'scripts' 		=> 'modules/ext.Profile.js',
	'styles'		=> 'modules/ext.Profile.css',
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Profile',
);


global $wgAjaxExportList;

$wgAjaxExportList[] = 'wfConnectDB';

function wfConnectDB() {
	$dbr = wfgetDB( DB_SLAVE );				//UTF-8 테스트
	$var = $dbr->selectFieldValues(
       'user',                  
       'user_name',             
       '',                      
       __METHOD__,              
       array(),                 
       array()                  
    );

	return strval($var[0]);
}