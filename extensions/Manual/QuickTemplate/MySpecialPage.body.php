<?php
/**
 * Main class for MySpecialPage example extension.
 *
 * @file
 * @ingroup Extensions
 */
class SpecialMySpecialPage extends SpecialPage {
	/**
	 * @var string $userName The current user's username
	 */
	private $userName;

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		$this->userName = $this->getUser()->getName(); // Define the private class member variable
		parent::__construct( 'MySpecialPage' );
	}

	/**
	 * Show the special page
	 *
	 * @param mixed|null $par Parameter passed to the special page or null
	 */
	public function execute( $par ) {
		global $wgArticlePath;
		// Maybe check for user block/DB lock state and permissions here...
		// It's up to you, really.

		// Set page title etc. stuff
		$this->setHeaders();

		// Begin actual template stuff
		$template = new MySpecialPageTemplate();

		// Here we set the template variable 'foo', which in this case is a
		// string ($wgArticlePath with '$1' being replaced by 'Foo')
		$template->set( 'foo', str_replace( '$1', 'Foo', $wgArticlePath ) );
		// $this is SpecialMySpecialPage object
		$template->setRef( 'mySpecialClass', $this );
		// Template variable 'randomKey' is a randomly generated method that
		// the getRandomKey() method here defines for us.
		// getRandomKey() is a private class method, so this is pretty much
		// the only way we can use it outside this file/class
		$template->set( 'randomKey', $this->getRandomKey() );

		// In the template class, all the variables we define here can be
		// accessed by using $this->data['variable_name']

		// et voilà!
		$this->getOutput()->addTemplate( $template );
	}

	/**
	 * Gets a randomly generated key.
	 *
	 * @return string
	 */
	private function getRandomKey() {
		$key = md5( 'superRandomString-' . $this->userName );
		return $key;
	}
}