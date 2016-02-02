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

$wgAutoloadClasses['SpecialProfile'] = __DIR__ . '/SpecialProfile.php'; # Location of the SpecialMyExtension class (Tell MediaWiki to load this file)
$wgMessagesDirs['Profile'] = __DIR__ . "/i18n"; # Location of localisation files (Tell MediaWiki to load them)
$wgExtensionMessagesFiles['ProfileAlias'] = __DIR__ . '/Profile.alias.php'; # Location of an aliases file (Tell MediaWiki to load it)
$wgSpecialPages['Profile'] = 'SpecialProfile'; # Tell MediaWiki about the new special page and its class name