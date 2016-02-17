<?php
/**
 * AJAX functions used by Vote extension.
 */
$wgAjaxExportList[] = 'wfSimVote';

function wfSimVote( $voteValue, $pageId ) {
	global $wgUser;

	if ( !$wgUser->isAllowed( 'voteny' ) ) {
		return '';
	}

	if ( is_numeric( $pageId ) && ( is_numeric( $voteValue ) ) ) {
		$vote = new Vote( $pageId );
		$vote->insert( $voteValue );

		return $vote->count( 1 );
	} else {
		return 'error';
	}
}

