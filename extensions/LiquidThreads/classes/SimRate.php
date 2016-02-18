<?php

class SimRate {
	public $PageID = 0;
	public $Userid = 0;
	public $Username = null;


	public function __construct( $pageID ) {
		global $wgUser;

		$this->PageID = $pageID;
		$this->Username = $wgUser->getName();
		$this->Userid = $wgUser->getID();
	}

	function insert( $voteValue ) {
		global $wgRequest;
		$dbw = wfGetDB( DB_MASTER );
		wfSuppressWarnings(); // E_STRICT whining
		$voteDate = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();
		if ( $this->UserAlreadyVoted() == false ) {
			$dbw->begin();
			$dbw->insert(
				'Vote',
				array(
					'username' => $this->Username,
					'vote_user_id' => $this->Userid,
					'vote_page_id' => $this->PageID,
					'vote_value' => $voteValue,
					'vote_date' => $voteDate,
					'vote_ip' => $wgRequest->getIP(),
				),
				__METHOD__
			);
			$dbw->commit();

			//$this->clearCache();

			// Update social statistics if SocialProfile extension is enabled
			if ( class_exists( 'UserStatsTrack' ) ) {
				$stats = new UserStatsTrack( $this->Userid, $this->Username );
				$stats->incStatField( 'vote' );
			}
		}
	}

	function delete() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$dbw->delete(
			'sim_rate',
			array(
				'vote_page_id' => $this->PageID,
				'username' => $this->Username
			),
			__METHOD__
		);
		$dbw->commit();

		$this->clearCache();

		// Update social statistics if SocialProfile extension is enabled
		if ( class_exists( 'UserStatsTrack' ) ) {
			$stats = new UserStatsTrack( $this->Userid, $this->Username );
			$stats->decStatField( 'vote' );
		}
	}


	function UserAlreadyVoted() {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'Vote',
			array( 'vote_value' ),
			array(
				'vote_page_id' => $this->PageID,
				'username' => $this->Username
			),
			__METHOD__
		);
		if ( $s === false ) {
			return false;
		} else {
			return $s->vote_value;
		}
	}

	function clearCache() {
		global $wgUser, $wgMemc;

		// Kill internal cache
		$wgMemc->delete( wfMemcKey( 'vote', 'count', $this->PageID ) );
		$wgMemc->delete( wfMemcKey( 'vote', 'avg', $this->PageID ) );

		// Purge squid
		$pageTitle = Title::newFromID( $this->PageID );
		if ( is_object( $pageTitle ) ) {
			$pageTitle->invalidateCache();
			$pageTitle->purgeSquid();

			// Kill parser cache
			$article = new Article( $pageTitle, /* oldid */0 );
			$parserCache = ParserCache::singleton();
			$parserKey = $parserCache->getKey( $article, $wgUser );
			$wgMemc->delete( $parserKey );
		}
	}
}