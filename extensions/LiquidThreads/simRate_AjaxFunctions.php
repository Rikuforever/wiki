<?php
/**
 * AJAX functions used by Vote extension.
 */
$wgAjaxExportList[] = 'wfSimRate';

function wfSimRate( $voteValue, $pageId ) {
	global $wgUser;

	if ( !$wgUser->isAllowed( 'simrate' ) ) {
		return '';
	}

	// HJ : TEST
	return 'success';

	if ( is_numeric( $pageId ) && ( is_numeric( $voteValue ) ) ) {
		$vote = new SimRate( $pageId );
		$vote->insert( $voteValue );

		//return $vote->count( 1 );
		return 'success';
	} else {
		return 'error';
	}
}

