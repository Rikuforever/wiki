<?php

class ApiThreadAction extends ApiEditPage {
	public function execute() {
		$params = $this->extractRequestParams();

		if ( !count( $params['threadaction'] ) ) {
			$this->dieUsageMsg( array( 'missingparam', 'action' ) );
		}

		$allowedAllActions = array( 'markread' );
		$actionsAllowedOnNonLqtPage = array( 'markread', 'markunread' );
		$action = $params['threadaction'];

		// Pull the threads from the parameters
		$threads = array();
		if ( !empty( $params['thread'] ) ) {
			foreach ( $params['thread'] as $thread ) {
				$threadObj = null;
				if ( is_numeric( $thread ) ) {
					$threadObj = Threads::withId( $thread );
				} elseif ( $thread == 'all' &&
						in_array( $action, $allowedAllActions ) ) {
					$threads = array( 'all' );
				} else {
					$title = Title::newFromText( $thread );
					$article = new Article( $title, 0 );
					$threadObj = Threads::withRoot( $article );
				}

				if ( $threadObj instanceof Thread ) {
					$threads[] = $threadObj;

					if ( !in_array( $action, $actionsAllowedOnNonLqtPage ) && !LqtDispatch::isLqtPage( $threadObj->getTitle() ) ) {
						$articleTitleDBKey = $threadObj->getTitle()->getDBkey();
						$message = wfMessage( 'lqt-not-a-liquidthreads-page', $articleTitleDBKey )->text();
						$this->dieUsageMsg( $message );
					}
				}
			}
		}

		// HACK: Somewhere $wgOut->parse() is called, which breaks
		// if a Title isn't set. So set one. See bug 71081.
		global $wgTitle;
		if ( !$wgTitle instanceof Title ) {
			$wgTitle = Title::newFromText( 'LiquidThreads has a bug' );
		}

		// Find the appropriate module
		$actions = $this->getActions();

		$method = $actions[$action];

		call_user_func_array( array( $this, $method ), array( $threads, $params ) );
	}

	public function actionMarkRead( $threads, $params ) {
		$user = $this->getUser();

		$result = array();

		if ( in_array( 'all', $threads ) ) {
			NewMessages::markAllReadByUser( $user );
			$result[] = array(
				'result' => 'Success',
				'action' => 'markread',
				'threads' => 'all',
				'unreadlink' => array(
					'href' => SpecialPage::getTitleFor( 'NewMessages' )->getLocalURL(),
					'text' => wfMessage( 'lqt_newmessages' )->text(),
					'active' => false,
				)
			);
		} else {
			foreach ( $threads as $t ) {
				NewMessages::markThreadAsReadByUser( $t, $user );
				$result[] = array(
					'result' => 'Success',
					'action' => 'markread',
					'id' => $t->id(),
					'title' => $t->title()->getPrefixedText()
				);
			}
			$newMessagesCount = NewMessages::newMessageCount( $user, DB_MASTER );
			$result[count( $result ) - 1]['unreadlink'] = array( // Only bother to put this on the last threadaction
				'href' => SpecialPage::getTitleFor( 'NewMessages' )->getLocalURL(),
				'text' => wfMessage( $newMessagesCount ? 'lqt-newmessages-n' : 'lqt_newmessages' )->numParams( $newMessagesCount )->text(),
				'active' => $newMessagesCount > 0,
			);
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadactions', $result );
	}

	public function actionMarkUnread( $threads, $params ) {
		$result = array();

		$user = $this->getUser();
		foreach ( $threads as $t ) {
			NewMessages::markThreadAsUnreadByUser( $t, $user );

			$result[] = array(
				'result' => 'Success',
				'action' => 'markunread',
				'id' => $t->id(),
				'title' => $t->title()->getPrefixedText()
			);
		}


		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	public function actionSplit( $threads, $params ) {
		if ( count( $threads ) > 1 ) {
			$this->dieUsage( 'You may only split one thread at a time',
					'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieUsage( 'You must specify a thread to split',
					'no-specified-threads' );
		}

		$thread = array_pop( $threads );

		$errors = $thread->title()->getUserPermissionsErrors( 'lqt-split', $this->getUser() );
		if ( $errors ) {
			// We don't care about multiple errors, just report one of them
			$this->dieUsageMsg( reset( $errors ) );
		}

		if ( $thread->isTopmostThread() ) {
			$this->dieUsage( 'This thread is already a top-level thread.',
				'already-top-level' );
		}

		$title = null;
		$article = $thread->article();
		if ( empty( $params['subject'] ) ||
			! Thread::validateSubject( $params['subject'], $title, null, $article ) ) {

			$this->dieUsage( 'No subject, or an invalid subject, was specified',
				'no-valid-subject' );
		}

		$subject = $params['subject'];

		// Pull a reason, if applicable.
		$reason = '';
		if ( !empty( $params['reason'] ) ) {
			$reason = $params['reason'];
		}

		// Check if they specified a sortkey
		$sortkey = null;
		if ( !empty( $params['sortkey'] ) ) {
			$ts = $params['sortkey'];
			$ts = wfTimestamp( TS_MW, $ts );

			$sortkey = $ts;
		}

		// Do the split
		$thread->split( $subject, $reason, $sortkey );

		$result = array();
		$result[] = array(
			'result' => 'Success',
			'action' => 'split',
			'id' => $thread->id(),
			'title' => $thread->title()->getPrefixedText(),
			'newsubject' => $subject,
		);

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	public function actionMerge( $threads, $params ) {
		if ( count( $threads ) < 1 ) {
			$this->dieUsage( 'You must specify a thread to merge',
				'no-specified-threads' );
		}

		if ( empty( $params['newparent'] ) ) {
			$this->dieUsage( 'You must specify a new parent thread to merge beneath',
				'no-parent-thread' );
		}

		$newParent = $params['newparent'];
		if ( is_numeric( $newParent ) ) {
			$newParent = Threads::withId( $newParent );
		} else {
			$title = Title::newFromText( $newParent );
			$article = new Article( $title, 0 );
			$newParent = Threads::withRoot( $article );
		}

		$errors = $newParent->title()->getUserPermissionsErrors( 'lqt-merge', $this->getUser() );
		if ( $errors ) {
			// We don't care about multiple errors, just report one of them
			$this->dieUsageMsg( reset( $errors ) );
		}

		if ( !$newParent ) {
			$this->dieUsage( 'The parent thread you specified was neither the title ' .
					'of a thread, nor a thread ID.', 'invalid-parent-thread' );
		}

		// Pull a reason, if applicable.
		$reason = '';
		if ( !empty( $params['reason'] ) ) {
			$reason = $params['reason'];
		}

		$result = array();

		foreach ( $threads as $thread ) {
			$thread->moveToParent( $newParent, $reason );
			$result[] = array(
				'result' => 'Success',
				'action' => 'merge',
				'id' => $thread->id(),
				'title' => $thread->title()->getPrefixedText(),
				'new-parent-id' => $newParent->id(),
				'new-parent-title' => $newParent->title()->getPrefixedText(),
				'new-ancestor-id' => $newParent->topmostThread()->id(),
				'new-ancestor-title' => $newParent->topmostThread()->title()->getPrefixedText(),
			);
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	public function actionNewThread( $threads, $params ) {
		// Validate talkpage parameters
		if ( !count( $params['talkpage'] ) ) {
			$this->dieUsageMsg( array( 'missingparam', 'talkpage' ) );
		}

		$talkpageTitle = Title::newFromText( $params['talkpage'] );

		if ( !$talkpageTitle || !LqtDispatch::isLqtPage( $talkpageTitle ) ) {
			$this->dieUsage( 'The talkpage you specified is invalid, or does not ' .
				'have discussion threading enabled.', 'invalid-talkpage' );
		}
		$talkpage = new Article( $talkpageTitle, 0 );

		// Check if we can post.
		$user = $this->getUser();
		if ( Thread::canUserPost( $user, $talkpage ) !== true ) {
			$this->dieUsage( 'You cannot post to the specified talkpage, ' .
				'because it is protected from new posts', 'talkpage-protected' );
		}

		// Validate subject, generate a title
		if ( empty( $params['subject'] ) ) {
			$this->dieUsageMsg( array( 'missingparam', 'subject' ) );
		}

		$subject = $params['subject'];
		$title = null;
		$subjectOk = Thread::validateSubject( $subject, $title, null, $talkpage );

		if ( !$subjectOk ) {
			$this->dieUsage( 'The subject you specified is not valid',
				'invalid-subject' );
		}
		$article = new Article( $title, 0 );

		// Check for text
		if ( empty( $params['text'] ) ) {
			$this->dieUsage( 'You must include text in your post', 'no-text' );
		}
		$text = $params['text'];

		// Generate or pull summary
		$summary = wfMessage( 'lqt-newpost-summary', $subject )->inContentLanguage()->text();
		if ( !empty( $params['reason'] ) ) {
			$summary = $params['reason'];
		}

		$signature = null;
		if ( isset( $params['signature'] ) ) {
			$signature = $params['signature'];
		}

		// Inform hooks what we're doing
		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = null;
		LqtHooks::$editType = 'new';
		LqtHooks::$editAppliesTo = null;

		$token = $params['token'];

		// All seems in order. Construct an API edit request
		$requestData = array(
			'action' => 'edit',
			'title' => $title->getPrefixedText(),
			'text' => $text,
			'summary' => $summary,
			'token' => $token,
			'basetimestamp' => wfTimestampNow(),
			'minor' => 0,
			'format' => 'json',
		);

		if ( $user->isAllowed( 'bot' ) ) {
			$requestData['bot'] = true;
		}
		$editReq = new DerivativeRequest( $this->getRequest(), $requestData, true );
		$internalApi = new ApiMain( $editReq, true );
		$internalApi->execute();

		if ( defined( 'ApiResult::META_CONTENT' ) ) {
			$editResult = $internalApi->getResult()->getResultData();
		} else {
			$editResult = $internalApi->getResultData();
		}

		if ( $editResult['edit']['result'] != 'Success' ) {
			$result = array( 'result' => 'EditFailure', 'details' => $editResult );
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
			return;
		}

		$articleId = $editResult['edit']['pageid'];

		$article->getTitle()->resetArticleID( $articleId );
		$title->resetArticleID( $articleId );

		$thread = LqtView::newPostMetadataUpdates(
			array(
				'root' => $article,
				'talkpage' => $talkpage,
				'subject' => $subject,
				'signature' => $signature,
				'summary' => $summary,
				'text' => $text,
			) );

		$result = array(
			'result' => 'Success',
			'thread-id' => $thread->id(),
			'thread-title' => $title->getPrefixedText(),
			'modified' => $thread->modified(),
		);

		if ( !empty( $params['render'] ) ) {
			$result['html'] = $this->renderThreadPostAction( $thread );
		}

		$result = array( 'thread' => $result );

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function actionEdit( $threads, $params ) {
		if ( count( $threads ) > 1 ) {
			$this->dieUsage( 'You may only edit one thread at a time',
					'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieUsage( 'You must specify a thread to edit',
					'no-specified-threads' );
		}

		$thread = array_pop( $threads );
		$talkpage = $thread->article();

		$bump = isset( $params['bump'] ) ? $params['bump'] : null;

		// Validate subject
		$subjectOk = true;
		if ( !empty( $params['subject'] ) ) {
			$subject = $params['subject'];
			$title = null;
			$subjectOk = empty( $subject ) ||
				Thread::validateSubject( $subject, $title, null, $talkpage );
		} else {
			$subject = $thread->subject();
		}

		if ( !$subjectOk ) {
			$this->dieUsage( 'The subject you specified is not valid',
				'invalid-subject' );
		}

		// Check for text
		if ( empty( $params['text'] ) ) {
			$this->dieUsage( 'You must include text in your post', 'no-text' );
		}
		$text = $params['text'];

		$summary = '';
		if ( !empty( $params['reason'] ) ) {
			$summary = $params['reason'];
		}

		$article = $thread->root();
		$title = $article->getTitle();

		$signature = null;
		if ( isset( $params['signature'] ) ) {
			$signature = $params['signature'];
		}

		// Inform hooks what we're doing
		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = $thread;
		LqtHooks::$editType = 'edit';
		LqtHooks::$editAppliesTo = null;

		$token = $params['token'];

		// All seems in order. Construct an API edit request
		$requestData = array(
			'action' => 'edit',
			'title' => $title->getPrefixedText(),
			'text' => $text,
			'summary' => $summary,
			'token' => $token,
			'minor' => 0,
			'basetimestamp' => wfTimestampNow(),
			'format' => 'json',
		);

		if ( $this->getUser()->isAllowed( 'bot' ) ) {
			$requestData['bot'] = true;
		}

		$editReq = new DerivativeRequest( $this->getRequest(), $requestData, true );
		$internalApi = new ApiMain( $editReq, true );
		$internalApi->execute();

		if ( defined( 'ApiResult::META_CONTENT' ) ) {
			$editResult = $internalApi->getResult()->getResultData();
		} else {
			$editResult = $internalApi->getResultData();
		}

		if ( $editResult['edit']['result'] != 'Success' ) {
			$result = array( 'result' => 'EditFailure', 'details' => $editResult );
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
			return;
		}

		$thread = LqtView::editMetadataUpdates(
			array(
				'root' => $article,
				'thread' => $thread,
				'subject' => $subject,
				'signature' => $signature,
				'summary' => $summary,
				'text' => $text,
				'bump' => $bump,
			) );

		$result = array(
			'result' => 'Success',
			'thread-id' => $thread->id(),
			'thread-title' => $title->getPrefixedText(),
			'modified' => $thread->modified(),
		);

		if ( !empty( $params['render'] ) ) {
			$result['html'] = $this->renderThreadPostAction( $thread );
		}

		$result = array( 'thread' => $result );

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function actionReply( $threads, $params ) {
		// Validate thread parameter
		if ( count( $threads ) > 1 ) {
			$this->dieUsage( 'You may only reply to one thread at a time',
					'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieUsage( 'You must specify a thread to reply to',
					'no-specified-threads' );
		}
		$replyTo = array_pop( $threads );

		// Check if we can reply to that thread.
		$user = $this->getUser();
		$perm_result = $replyTo->canUserReply( $user );
		if ( $perm_result !== true ) {
			$this->dieUsage( "You cannot reply to this thread, because the " .
				$perm_result . " is protected from replies.",
				$perm_result . '-protected' );
		}

		// Validate text parameter
		if ( empty( $params['text'] ) ) {
			$this->dieUsage( 'You must include text in your post', 'no-text' );
		}

		$text = $params['text'];

		$bump = isset( $params['bump'] ) ? $params['bump'] : null;

		// Generate/pull summary
		$summary = wfMessage( 'lqt-reply-summary', $replyTo->subject(),
				$replyTo->title()->getPrefixedText() )->inContentLanguage()->text();

		if ( !empty( $params['reason'] ) ) {
			$summary = $params['reason'];
		}

		$signature = null;
		if ( isset( $params['signature'] ) ) {
			$signature = $params['signature'];
		}

		// Grab data from parent
		$talkpage = $replyTo->article();

		// Generate a reply title.
		$title = Threads::newReplyTitle( $replyTo, $user );
		$article = new Article( $title, 0 );

		// Inform hooks what we're doing
		LqtHooks::$editTalkpage = $talkpage;
		LqtHooks::$editArticle = $article;
		LqtHooks::$editThread = null;
		LqtHooks::$editType = 'reply';
		LqtHooks::$editAppliesTo = $replyTo;

		// Pull token in
		$token = $params['token'];

		// All seems in order. Construct an API edit request
		$requestData = array(
			'action' => 'edit',
			'title' => $title->getPrefixedText(),
			'text' => $text,
			'summary' => $summary,
			'token' => $token,
			'basetimestamp' => wfTimestampNow(),
			'minor' => 0,
			'format' => 'json',
		);

		if ( $user->isAllowed( 'bot' ) ) {
			$requestData['bot'] = true;
		}

		$editReq = new DerivativeRequest( $this->getRequest(), $requestData, true );
		$internalApi = new ApiMain( $editReq, true );
		$internalApi->execute();

		if ( defined( 'ApiResult::META_CONTENT' ) ) {
			$editResult = $internalApi->getResult()->getResultData();
		} else {
			$editResult = $internalApi->getResultData();
		}

		if ( $editResult['edit']['result'] != 'Success' ) {
			$result = array( 'result' => 'EditFailure', 'details' => $editResult );
			$this->getResult()->addValue( null, $this->getModuleName(), $result );
			return;
		}

		$articleId = $editResult['edit']['pageid'];
		$article->getTitle()->resetArticleID( $articleId );
		$title->resetArticleID( $articleId );

		$thread = LqtView::replyMetadataUpdates(
			array(
				'root' => $article,
				'replyTo' => $replyTo,
				'signature' => $signature,
				'summary' => $summary,
				'text' => $text,
				'bump' => $bump,
			) );

		$result = array(
			'action' => 'reply',
			'result' => 'Success',
			'thread-id' => $thread->id(),
			'thread-title' => $title->getPrefixedText(),
			'parent-id' => $replyTo->id(),
			'parent-title' => $replyTo->title()->getPrefixedText(),
			'ancestor-id' => $replyTo->topmostThread()->id(),
			'ancestor-title' => $replyTo->topmostThread()->title()->getPrefixedText(),
			'modified' => $thread->modified(),
		);

		if ( !empty( $params['render'] ) ) {
			$result['html'] = $this->renderThreadPostAction( $thread );
		}

		$result = array( 'thread' => $result );

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @param $thread Thread
	 * @return String
	 */
	protected function renderThreadPostAction( $thread ) {
		$thread = $thread->topmostThread();

		// Set up OutputPage
		$out = $this->getOutput();
		$oldOutputText = $out->getHTML();
		$out->clearHTML();

		// Setup
		$article = $thread->root();
		$title = $article->getTitle();
		$user = $this->getUser();
		$request = $this->getRequest();
		$view = new LqtView( $out, $article, $title, $user , $request );

		$view->showThread( $thread );

		$result = $out->getHTML();
		$out->clearHTML();
		$out->addHTML( $oldOutputText );

		return $result;
	}

	public function actionSetSubject( $threads, $params ) {
		// Validate thread parameter
		if ( count( $threads ) > 1 ) {
			$this->dieUsage( 'You may only change the subject of one thread at a time',
					'too-many-threads' );
		} elseif ( count( $threads ) < 1 ) {
			$this->dieUsage( 'You must specify a thread to change the subject of',
					'no-specified-threads' );
		}
		$thread = array_pop( $threads );

		$errors = $thread->title()->getUserPermissionsErrors( 'edit', $this->getUser() );
		if ( $errors ) {
			// We don't care about multiple errors, just report one of them
			$this->dieUsageMsg( reset( $errors ) );
		}

		// Validate subject
		if ( empty( $params['subject'] ) ) {
			$this->dieUsageMsg( array( 'missingparam', 'subject' ) );
		}

		$talkpage = $thread->article();

		$subject = $params['subject'];
		$title = null;
		$subjectOk = Thread::validateSubject( $subject, $title, null, $talkpage );

		if ( !$subjectOk ) {
			$this->dieUsage( 'The subject you specified is not valid',
				'invalid-subject' );
		}

		$reason = null;

		if ( isset( $params['reason'] ) ) {
			$reason = $params['reason'];
		}

		if ( $thread->dbVersion->subject() !== $subject ) {
			$thread->setSubject( $subject );
			$thread->commitRevision( Threads::CHANGE_EDITED_SUBJECT, $thread, $reason );
		}

		$result = array(
			'action' => 'setsubject',
			'result' => 'success',
			'thread-id' => $thread->id(),
			'thread-title' => $thread->title()->getPrefixedText(),
			'new-subject' => $subject,
		);

		$result = array( 'thread' => $result );

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function actionSetSortkey( $threads, $params ) {
		// First check for threads
		if ( !count( $threads ) ) {
			$this->dieUsage( 'You must specify a thread to set the sortkey of',
					'no-specified-threads' );
		}

		// Validate timestamp
		if ( empty( $params['sortkey'] ) ) {
			$this->dieUsage( 'You must specify a valid timestamp for the sortkey ' .
				'parameter. It should be in the form YYYYMMddhhmmss, a ' .
				'unix timestamp or "now".', 'invalid-sortkey' );
		}

		$ts = $params['sortkey'];

		if ( $ts == 'now' ) $ts = wfTimestampNow();

		$ts = wfTimestamp( TS_MW, $ts );

		if ( !$ts ) {
			$this->dieUsage( 'You must specify a valid timestamp for the sortkey' .
				'parameter. It should be in the form YYYYMMddhhmmss, a ' .
				'unix timestamp or "now".', 'invalid-sortkey' );
		}

		$reason = null;

		if ( isset( $params['reason'] ) ) {
			$reason = $params['reason'];
		}

		$thread = array_pop( $threads );

		$errors = $thread->title()->getUserPermissionsErrors( 'edit', $this->getUser() );
		if ( $errors ) {
			// We don't care about multiple errors, just report one of them
			$this->dieUsageMsg( reset( $errors ) );
		}

		$thread->setSortkey( $ts );
		$thread->commitRevision( Threads::CHANGE_ADJUSTED_SORTKEY, null, $reason );

		$result = array(
			'action' => 'setsortkey',
			'result' => 'success',
			'thread-id' => $thread->id(),
			'thread-title' => $thread->title()->getPrefixedText(),
			'new-sortkey' => $ts,
		);

		$result = array( 'thread' => $result );

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	public function actionAddReaction( $threads, $params ) {
		if ( !count( $threads ) ) {
			$this->dieUsage( 'You must specify a thread to add a reaction for',
					'no-specified-threads' );
		}

		if ( ! $this->getUser()->isAllowed( 'lqt-react' ) ) {
			$this->dieUsage( 'You are not allowed to react to threads.', 'permission-denied' );
		}

		$required = array( 'type', 'value' );

		if ( count( array_diff( $required, array_keys( $params ) ) ) ) {
			$this->dieUsage( 'You must specify both a type and a value for the reaction',
						'missing-parameter' );
		}

		$result = array();

		foreach ( $threads as $thread ) {
			$thread->addReaction( $this->getUser(), $params['type'], $params['value'] );

			$result[] = array(
				'result' => 'Success',
				'action' => 'addreaction',
				'id' => $thread->id(),
			);
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	public function actionDeleteReaction( $threads, $params ) {
		if ( !count( $threads ) ) {
			$this->dieUsage( 'You must specify a thread to delete a reaction for',
					'no-specified-threads' );
		}

		$user = $this->getUser();
		if ( ! $user->isAllowed( 'lqt-react' ) ) {
			$this->dieUsage( 'You are not allowed to react to threads.', 'permission-denied' );
		}

		$required = array( 'type', 'value' );

		if ( count( array_diff( $required, array_keys( $params ) ) ) ) {
			$this->dieUsage( 'You must specify both a type for the reaction',
						'missing-parameter' );
		}

		$result = array();

		foreach ( $threads as $thread ) {
			$thread->deleteReaction( $user, $params['type'] );

			$result[] = array(
				'result' => 'Success',
				'action' => 'deletereaction',
				'id' => $thread->id(),
			);
		}

		$this->getResult()->setIndexedTagName( $result, 'thread' );
		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	public function actionInlineEditForm( $threads, $params ) {
		$method = $talkpage = $operand = null;

		if ( isset( $params['method'] ) ) {
			$method = $params['method'];
		}

		if ( isset( $params['talkpage'] ) ) {
			$talkpage = $params['talkpage'];
		}

		if ( $talkpage ) {
			$talkpage = new Article( Title::newFromText( $talkpage ), 0 );
		} else {
			$talkpage = null;
		}

		if ( count( $threads ) ) {
			$operand = $threads[0];
			$operand = $operand->id();
		}

		$output = LqtView::getInlineEditForm( $talkpage, $method, $operand );

		$result = array( 'inlineeditform' => array( 'html' => $output ) );

		/* FIXME
		$result['resources'] = LqtView::getJSandCSS();
		$result['resources']['messages'] = LqtView::exportJSLocalisation();
		*/

		$this->getResult()->addValue( null, 'threadaction', $result );
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getDescription() {
		return 'Allows actions to be taken on threads and posts in threaded discussions.';
	}

	public function getActions() {
		return array(
			'markread' => 'actionMarkRead',
			'markunread' => 'actionMarkUnread',
			'split' => 'actionSplit',
			'merge' => 'actionMerge',
			'reply' => 'actionReply',
			'newthread' => 'actionNewThread',
			'setsubject' => 'actionSetSubject',
			'setsortkey' => 'actionSetSortkey',
			'edit' => 'actionEdit',
			'addreaction' => 'actionAddReaction',
			'deletereaction' => 'actionDeleteReaction',
			'inlineeditform' => 'actionInlineEditForm',
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getParamDescription() {
		return array(
			'thread' => 'A list (pipe-separated) of thread IDs or titles to act on',
			'threadaction' => 'The action to take',
			'token' => 'An edit token (from ?action=query&prop=info&intoken=edit)',
			'talkpage' => 'The talkpage to act on (if applicable)',
			'subject' => 'The subject to set for the new or split thread',
			'reason' => 'If applicable, the reason/summary for the action',
			'newparent' => 'If merging a thread, the ID or title for its new parent',
			'text' => 'The text of the post to create',
			'render' => 'If set, on post/reply methods, the top-level thread ' .
				'after the change will be rendered and returned in the result.',
			'bump' => 'If set, overrides default behaviour as to whether or not to ',
				"increase the thread's sort key. If true, sets it to current " .
				"timestamp. If false, does not set it. Default depends on " .
				"the action being taken. Presently only works for newthread " .
				"and reply actions.",
			'sortkey' => "Specifies the timestamp to which to set a thread's " .
					"sort key. Must be in the form YYYYMMddhhmmss, " .
					"a unix timestamp or 'now'.",
			'signature' => 'Specifies the signature to use for that post. Can be ' .
					'NULL to specify the default signature',
			'type' => 'Specifies the type of reaction to add',
			'value' => 'Specifies the value associated with the reaction to add',
			'method' => 'For getting inline edit forms, the method to get a form for',
			'operand' => '',
		);
	}

	/**
	 * @deprecated since MediaWiki core 1.25
	 */
	public function getExamples() {
		return array(
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
		);
	}

	public function needsToken() {
		return 'csrf';
	}

	public function getTokenSalt() {
		return '';
	}

	public function getAllowedParams() {
		return array(
			'thread' => array(
				ApiBase::PARAM_ISMULTI => true,
			),
			'talkpage' => null,
			'threadaction' => array(
				ApiBase::PARAM_TYPE => array_keys( $this->getActions() ),
			),
			'token' => null,
			'subject' => null,
			'reason' => null,
			'newparent' => null,
			'text' => null,
			'render' => null,
			'bump' => null,
			'sortkey' => null,
			'signature' => null,
			'type' => null,
			'value' => null,
			'method' => null,
			'operand' => null,
		);
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}
}