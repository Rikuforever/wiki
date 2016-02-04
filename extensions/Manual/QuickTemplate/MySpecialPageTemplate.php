<?php
/**
 * @file
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( -1 );
}

/**
 * HTML template for Special:MySpecialPage
 * @ingroup Templates
 */
class MySpecialPageTemplate extends QuickTemplate {
	/**
	 * Main processing is done here.
	 *
	 * For proper i18n, use $this->getMsg( 'message-key' ) with the appropriate
	 * parameters (see [[Manual:Messages API]] on MediaWiki.org for further
	 * details).
	 * Because this is just an example, we're using hard-coded English text
	 * here. In your production-grade code, you obviously should be using the
	 * proper internationalization functions instead.
	 */
	public function execute() {
?>
<div id="MySpecialPageTemplate">
Your randomly generated secret key is: <?php echo $this->data['randomKey'] ?>

If you need to generate a new key, you can do so <a href="<?php echo htmlspecialchars( $this->data['mySpecialClass']->getPageTitle()->getFullURL() ) ?>">here</a>
</div>
<?php
	} // execute()
} // class