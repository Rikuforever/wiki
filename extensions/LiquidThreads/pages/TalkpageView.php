<?php

class TalkpageView extends LqtView {
	protected $mShowItems = array( 'toc', 'options', 'header' );
	protected $talkpage;

	function __construct( &$output, &$article, &$title, &$user, &$request ) {
		parent::__construct( $output, $article, $title, $user, $request );

		$this->talkpage = $article;
	}

	function setTalkPage( $tp ) {
		$this->talkpage = $tp;
	}

	static function customizeTalkpageNavigation( $skin, &$links, $view ) {
		$remove = array( 'views/edit', 'views/viewsource', 'actions/delete' );

		foreach ( $remove as $rem ) {
			list( $section, $item ) = explode( '/', $rem, 2 );
			unset( $links[$section][$item] );
		}

		if ( isset( $links['views']['history'] ) ) {
			$title = $view->article->getTitle();
			$history_url = $title->getLocalURL( 'lqt_method=talkpage_history' );
			$links['views']['history']['href'] = $history_url;
		}
	}

	function customizeNavigation( $skintemplate, &$links ) {
		self::customizeTalkpageNavigation( $skintemplate, $links, $this );
	}

	function showHeader() {
		/* Show the contents of the actual talkpage article if it exists. */

		global $wgUser;

		$article = $this->talkpage;

		// If $article_text == "", the talkpage was probably just created
		// when the first thread was posted to make the links blue.
		if ( $article->exists() ) {
			$html = '';

			$article->view();

			$actionLinks = array();
			$msgKey =  $article->getTitle()->quickUserCan( 'edit' ) ? 'edit' : 'viewsource';
			$actionLinks[] = Linker::link(
				$article->getTitle(),
				wfMessage( $msgKey )->parse() . "↑",
				array(),
				array( 'action' => 'edit' )
			);

			$actionLinks[] = Linker::link(
				$this->title,
				wfMessage( 'history_short' )->parse() . "↑",
				array(),
				array( 'action' => 'history' )
			);

			if ( $wgUser->isAllowed( 'delete' ) ) {
				$actionLinks[] = Linker::link(
					$article->getTitle(),
					wfMessage( 'delete' )->parse() . '↑',
					array(),
					array( 'action' => 'delete' )
				);
			}

			$actions = '';
			foreach ( $actionLinks as $link ) {
				$actions .= Xml::tags( 'li', null, "[$link]" ) . "\n";
			}
			$actions = Xml::tags( 'ul', array( 'class' => 'lqt_header_commands' ), $actions );
			$html .= $actions;

			$html = Xml::tags( 'div', array( 'class' => 'lqt_header_content' ), $html );

			$this->output->addHTML( $html );
		} elseif ( $article->getTitle()->quickUserCan( 'edit' ) ) {

			$editLink = Linker::link(
				$this->talkpage->getTitle(),
				wfMessage( 'lqt_add_header' )->parse(),
				array(),
				array( 'action' => 'edit' )
			);

			$html = Xml::tags( 'p', array( 'class' => 'lqt_header_notice' ), "[$editLink]" );

			$this->output->addHTML( $html );
		}
	}

	function getTOC( $threads ) {
		global $wgLang, $wgContLang;

		$html = '';

		$h2_header = Xml::tags( 'h2', null, wfMessage( 'lqt_contents_title' )->parse() );

		// Header row
		$headerRow = '';
		$headers = array( 'lqt_toc_thread_title',
				'lqt_toc_thread_replycount', 'lqt_toc_thread_modified' );
		foreach ( $headers as $msg ) {
			$headerRow .= Xml::tags( 'th', null, wfMessage( $msg )->parse() );
		}
		$headerRow = Xml::tags( 'tr', null, $headerRow );
		$headerRow = Xml::tags( 'thead', null, $headerRow );

		// Table body
		$rows = array();
		foreach ( $threads as $thread ) {
			if ( $thread->root() && !$thread->root()->getContent() &&
				    !LqtView::threadContainsRepliesWithContent( $thread ) ) {
				continue;
			}

			$row = '';
			$anchor = '#' . $this->anchorName( $thread );
			$subject = Xml::tags( 'a', array( 'href' => $anchor ),
					Threads::stripHTML( $thread->formattedSubject() ) );
			$row .= Xml::tags( 'td', array( 'dir' => $wgContLang->getDir() ), $subject );

			$row .= Xml::element( 'td', null, $wgLang->formatNum( $thread->replyCount() ) );

			$timestamp = $wgLang->timeanddate( $thread->modified(), true );
			$row .= Xml::element( 'td', null, $timestamp );

			$row = Xml::tags( 'tr', null, $row );
			$rows[] = $row;
		}

		$html .= $headerRow . "\n" . Xml::tags( 'tbody', null, implode( "\n", $rows ) );
		$html = $h2_header . Xml::tags( 'table', array( 'class' => 'lqt_toc' ), $html );
		// wrap our output in a div for containment
		$html = Xml::tags( 'div', array( 'class' => 'lqt-contents-wrapper' ), $html );

		return $html;
	}

	function getList( $kind, $class, $id, $contents ) {
		$html = '';
		foreach ( $contents as $li ) {
			$html .= Xml::tags( 'li', null, $li );
		}
		$html = Xml::tags( $kind, array( 'class' => $class, 'id' => $id ), $html );

		return $html;
	}

	function getArchiveWidget( ) {
		$html = '';
		$html = Xml::tags( 'div', array( 'class' => 'lqt_archive_teaser' ), $html );
		return $html;
	}

	function showTalkpageViewOptions( ) {
		$form_action_url = $this->talkpageUrl( $this->title, 'talkpage_sort_order' );
		$html = '';

		$html .= Xml::label( wfMessage( 'lqt_sorting_order' )->text(), 'lqt_sort_select' ) . ' ';

		$sortOrderSelect =
			new XmlSelect( 'lqt_order', 'lqt_sort_select', $this->getSortType() );

		$sortOrderSelect->setAttribute( 'class', 'lqt_sort_select' );
		$sortOrderSelect->addOption(
			wfMessage( 'lqt_sort_newest_changes' )->text(),
			LQT_NEWEST_CHANGES
		);
		$sortOrderSelect->addOption(
			wfMessage( 'lqt_sort_newest_threads' )->text(),
			LQT_NEWEST_THREADS
		);
		$sortOrderSelect->addOption(
			wfMessage( 'lqt_sort_oldest_threads' )->text(),
			LQT_OLDEST_THREADS
		);
		$html .= $sortOrderSelect->getHTML();

		$html .= Xml::submitButton( wfMessage( 'lqt-changesortorder' )->text(), array( 'class' => 'lqt_go_sort' ) );
		$html .= Html::hidden( 'title', $this->title->getPrefixedText() );


		$html = Xml::tags(
			'form',
			array(
				'action' => $form_action_url,
				'method' => 'get',
				'name' => 'lqt_sort'
			),
			$html
		);
		$html = Xml::tags( 'div', array( 'class' => 'lqt_view_options' ), $html );

		return $html;
	}

	function show() {
		$this->output->addModules( 'ext.liquidThreads' );

		$article = $this->talkpage;
		if ( ! LqtDispatch::isLqtPage( $article->getTitle() ) ) {
			$this->output->addWikiMsg( 'lqt-not-discussion-page' );
			return false;
		}

		$this->output->setPageTitle( $this->title->getPrefixedText() );

		// Expose feed links.
		global $wgFeedClasses;
		$apiParams = array( 'action' => 'feedthreads', 'type' => 'replies|newthreads',
				'talkpage' => $this->title->getPrefixedText() );
		$urlPrefix = wfScript( 'api' ) . '?';
		foreach ( $wgFeedClasses as $format => $class ) {
			$theseParams = $apiParams + array( 'feedformat' => $format );
			$url = $urlPrefix . wfArrayToCgi( $theseParams );
			$this->output->addFeedLink( $format, $url );
		}

		if ( $this->request->getBool( 'lqt_inline' ) ) {
			$this->doInlineEditForm();
			return false;
		}

		$this->output->addHTML(
			Xml::openElement( 'div', array( 'class' => 'lqt-talkpage' ) )
		);

		// Search!
		if ( $this->request->getCheck( 'lqt_search' ) ) {
			$q = $this->request->getText( 'lqt_search' );
			$q .= ' ondiscussionpage:' . $article->getTitle()->getPrefixedText();

			$params = array(
				'search' => $q,
				'fulltext' => 1,
				'ns' . NS_LQT_THREAD => 1,
				'srbackend' => 'LuceneSearch',
			);

			$t = SpecialPage::getTitleFor( 'Search' );
			$url = $t->getLocalURL( wfArrayToCgi( $params ) );

			$this->output->redirect( $url );
			return true;
		}

		if ( $this->shouldShow( 'header' ) ) {
			$this->showHeader();
		}

		global $wgLang;

		// This closes the div of mw-content-ltr/rtl containing lang and dir attributes
		$this->output->addHTML(
			Html::closeElement( 'div' ) . Html::openElement( 'div',
			array( 'class' => 'lqt-talkpage', 'lang' => $wgLang->getCode(), 'dir' => $wgLang->getDir() )
		) );

		$html = '';

		// Set up a per-page header for new threads, search box, and sorting stuff.

		$talkpageHeader = '';

		if ( Thread::canUserPost( $this->user, $this->talkpage ) ) {
			$newThreadText = wfMessage( 'lqt_new_thread' )->parse();
			$newThreadLink = Linker::link(
				$this->title, $newThreadText,
				array( 'lqt_talkpage' => $this->talkpage->getTitle()->getPrefixedText() ),
				array( 'lqt_method' => 'talkpage_new_thread' ),
				array( 'known' )
			);

			$newThreadLink = Xml::tags(
				'strong',
				array( 'class' => 'lqt_start_discussion' ),
				$newThreadLink
			);

			$talkpageHeader .= $newThreadLink;
		}

		global $wgSearchTypeAlternatives, $wgSearchType;
		if ( $wgSearchType == "LuceneSearch" || in_array( "LuceneSearch", $wgSearchTypeAlternatives ?: array() ) ) {
			$talkpageHeader .= $this->getSearchBox();
		}
		$talkpageHeader .= $this->showTalkpageViewOptions( $article );
		$talkpageHeader = Xml::tags(
			'div',
			array( 'class' => 'lqt-talkpage-header' ),
			$talkpageHeader
		);

 		if ( $this->shouldShow( 'options' ) ) {
 			$this->output->addHTML( $talkpageHeader );
 		} elseif ( $this->shouldShow( 'simplenew' ) ) {
 			$this->output->addHTML( $newThreadLink );
 		}

		if ( $this->methodApplies( 'talkpage_new_thread' ) ) {
			$this->showNewThreadForm( $this->talkpage );
		} else {
			$this->output->addHTML( Xml::tags( 'div',
				array( 'class' => 'lqt-new-thread lqt-edit-form' ), '' ) );
		}

		$pager = $this->getPager();

		$threads = $this->getPageThreads( $pager );

		if ( count( $threads ) > 0 && $this->shouldShow( 'toc' ) ) {
			$html .= $this->getTOC( $threads );
		} elseif ( count( $threads ) == 0 ) {
			$html .= Xml::tags( 'div', array( 'class' => 'lqt-no-threads' ),
				wfMessage( 'lqt-no-threads' )->parse() );
		}

		$html .= $pager->getNavigationBar();
		$html .= Xml::openElement( 'div', array( 'class' => 'lqt-threads lqt-talkpage-threads' ) );

		$this->output->addHTML( $html );

		foreach ( $threads as $t ) {
			$this->showThread( $t );
		}

		$this->output->addHTML(
			Xml::closeElement( 'div' ) .
			$pager->getNavigationBar() .
			Xml::closeElement( 'div' )
		);

		return false;
	}

	function getSearchBox() {
		$html = '';
		$html .= Xml::inputLabel(
			wfMessage( 'lqt-search-label' )->text(),
			'lqt_search',
			'lqt-search-box',
			45
		);

		$html .= ' ' . Xml::submitButton( wfMessage( 'lqt-search-button' )->text() );
		$html .= Html::hidden( 'title', $this->title->getPrefixedText() );
		$html = Xml::tags(
			'form',
			array(
				'action' => $this->title->getLocalURL(),
				'method' => 'get'
			),
			$html
		);

		$html = Xml::tags( 'div', array( 'class' => 'lqt-talkpage-search' ), $html );

		return $html;
	}

	function getPager() {

		$sortType = $this->getSortType();
		return new LqtDiscussionPager( $this->talkpage, $sortType );
	}

	function getPageThreads( $pager ) {
		$rows = $pager->getRows();

		return Thread::bulkLoad( $rows );
	}

	function getSortType() {
		// Determine sort order
		if ( $this->request->getCheck( 'lqt_order' ) ) {
			// Sort order is explicitly specified through UI
			$lqt_order = $this->request->getVal( 'lqt_order' );
			switch( $lqt_order ) {
				case 'nc':
					return LQT_NEWEST_CHANGES;
				case 'nt':
					return LQT_NEWEST_THREADS;
				case 'ot':
					return LQT_OLDEST_THREADS;
			}
		}

		// Default
		return LQT_NEWEST_CHANGES;
	}

	// Hide a number of items from the view
	// Valid values: toc, options, header
	function hideItems( $items ) {
		$this->mShowItems = array_diff( $this->mShowItems, (array)$items );
	}

	// Show a number of items in the view
	// Valid values: toc, options, header
	function showItems( $items ) {
		$this->mShowItems = array_merge( $this->mShowItems, (array)$items );
	}

	// Whether or not to show an item
	function shouldShow( $item ) {
		return in_array( $item, $this->mShowItems );
	}

	// Set the items shown
	function setShownItems( $items ) {
		$this->mShowItems = $items;
	}
}

class LqtDiscussionPager extends IndexPager {
	function __construct( $article, $orderType ) {
		$this->article = $article;
		$this->orderType = $orderType;

		parent::__construct();

		$this->mLimit = $this->getPageLimit();
	}

	function getPageLimit() {
		$article = $this->article;

		global $wgRequest;
		$requestedLimit = $wgRequest->getVal( 'limit', null );
		if ( $requestedLimit ) {
			return $requestedLimit;
		}

		if ( $article->exists() ) {
			$pout = $article->getParserOutput();
			$setLimit = $pout->getProperty( 'lqt-page-limit' );
			if ( $setLimit ) return $setLimit;
		}

		global $wgLiquidThreadsDefaultPageLimit;
		return $wgLiquidThreadsDefaultPageLimit;
	}

	function getQueryInfo() {
		$queryInfo = array(
			'tables' => array( 'thread' ),
			'fields' => '*',
			'conds' => array(
				Threads::articleClause( $this->article ),
				Threads::topLevelClause(),
				'thread_type != ' . $this->mDb->addQuotes( Threads::TYPE_DELETED ),
			),
		);

		return $queryInfo;
	}

	// Adapted from getBody().
	function getRows() {
		if ( !$this->mQueryDone ) {
			$this->doQuery();
		}

		# Don't use any extra rows returned by the query
		$numRows = min( $this->mResult->numRows(), $this->mLimit );

		$rows = array();

		if ( $numRows ) {
			if ( $this->mIsBackwards ) {
				for ( $i = $numRows - 1; $i >= 0; $i-- ) {
					$this->mResult->seek( $i );
					$row = $this->mResult->fetchObject();
					$rows[] = $row;
				}
			} else {
				$this->mResult->seek( 0 );
				for ( $i = 0; $i < $numRows; $i++ ) {
					$row = $this->mResult->fetchObject();
					$rows[] = $row;
				}
			}
		}

		return $rows;
	}

	function formatRow( $row ) {
		// No-op, we get the list of rows from getRows()
	}

	function getIndexField() {
		switch( $this->orderType ) {
			case LQT_NEWEST_CHANGES:
				return 'thread_sortkey';
			case LQT_OLDEST_THREADS:
			case LQT_NEWEST_THREADS:
				return 'thread_created';
			default:
				throw new Exception( "Unknown sort order " . $this->orderType );
		}
	}

	function getDefaultDirections() {
		switch( $this->orderType ) {
			case LQT_NEWEST_CHANGES:
			case LQT_NEWEST_THREADS:
				return true; // Descending
			case LQT_OLDEST_THREADS:
				return false; // Ascending
			default:
				throw new Exception( "Unknown sort order " . $this->orderType );
		}
	}

	/**
	 * A navigation bar with images
	 * Stolen from TablePager because it's pretty.
	 */
	function getNavigationBar() {
		if ( method_exists( $this, 'isNavigationBarShown' ) &&
				!$this->isNavigationBarShown() ) {
			return '';
		}
		global $wgExtensionAssetsPath, $wgLang;

		$path = "$wgExtensionAssetsPath/LiquidThreads/images";
		$labels = array(
			'first' => 'table_pager_first',
			'prev' => 'table_pager_prev',
			'next' => 'table_pager_next',
			'last' => 'table_pager_last',
		);
		$images = array(
			'first' => 'arrow_first_25.png',
			'prev' => 'arrow_left_25.png',
			'next' => 'arrow_right_25.png',
			'last' => 'arrow_last_25.png',
		);
		$disabledImages = array(
			'first' => 'arrow_disabled_first_25.png',
			'prev' => 'arrow_disabled_left_25.png',
			'next' => 'arrow_disabled_right_25.png',
			'last' => 'arrow_disabled_last_25.png',
		);
		if ( $wgLang->isRTL() ) {
			$keys = array_keys( $labels );
			$images = array_combine( $keys, array_reverse( $images ) );
			$disabledImages = array_combine( $keys, array_reverse( $disabledImages ) );
		}

		$linkTexts = array();
		$disabledTexts = array();
		foreach ( $labels as $type => $label ) {
			$msgLabel = wfMessage( $label )->escaped();
			$linkTexts[$type] = "<img src=\"$path/{$images[$type]}\" alt=\"$msgLabel\"/><br />$msgLabel";
			$disabledTexts[$type] = "<img src=\"$path/{$disabledImages[$type]}\" alt=\"$msgLabel\"/><br />$msgLabel";
		}
		$links = $this->getPagingLinks( $linkTexts, $disabledTexts );

		$navClass = htmlspecialchars( $this->getNavClass() );
		$s = "<table class=\"$navClass\"><tr>\n";
		$cellAttrs = 'width: ' . 100 / count( $links ) . '%';
		foreach ( $labels as $type => $label ) {
			$s .= "<td style='$cellAttrs'>{$links[$type]}</td>\n";
		}
		$s .= "</tr></table>\n";
		return $s;
	}

	function getNavClass() {
		return 'TalkpagePager_nav';
	}
}
