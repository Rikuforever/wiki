<?php
class SpecialProfile extends SpecialPage {

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		// A special page should at least have a name.
		// We do this by calling the parent class (the SpecialPage class)
		// constructor method with the name as first and only parameter.
		parent::__construct( 'Profile' );
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub: The subpage string argument (if any).
	 *  [[Special:HelloWorld/subpage]].
	 */
	public function execute( $sub ) {
		global $wgRequest;

		//DB 접속 Test
		$dbr = wfGetDB( DB_SLAVE );
		$var1 = $dbr->select(
			'user',
			array('user_name'),
			'',
			__METHOD__,
			array(),
			array()
		);
		$var2 = $dbr->selectFieldValues(
			'user',
			'user_name',
			'',
			__METHOD__,
			array(),
			array()
		);
		//DB 접속 Test END

		$out = $this->getOutput();

		$out->setPageTitle( $this->msg( 'profile-title' ) );

		// Parses message from .i18n.php as wikitext and adds it to the
		// page output.
		// OutputPage 클래스와 관련
		$out->addWikiMsg( 'profile-body1' );
		$out->addWikimsg( 'profile-body2', '이호중' );
		$out->addHTML('<h2>TEST</h2>');
		//Debug용
		$out->addHTML($var2[0]);
	}

	protected function getGroupName() {
		return 'other';
	}
}
