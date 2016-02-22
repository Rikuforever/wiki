<?php

class SimRate {
	public $ThreadID = 0;
	public $Userid = 0;
	public $Username = null;


	public function __construct( $inputId ) {
		global $wgUser;

		$this->ThreadID = $inputId;
		$this->Username = $wgUser->getName();
		$this->Userid = $wgUser->getID();
	}

	// HJ : 좋아요/싫어요 총 개수 반환
	function getCount( $rateValue ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();

		// HJ : 알맞은 thread DB 필드 선택
		if($rateValue == SIM_LIKE){
			$targetField = 'thread_score2';
		} else if($rateValue == SIM_DISLIKE){
			$targetField = 'thread_score3';
		}

		$countArray = $dbw->selectFieldValues(
				'thread',
				$targetField,
				array( 'thread_id' => $this->ThreadID ),
				__METHOD__
			);

		$dbw->commit();
		$count = $countArray[0];

		return $count;	
	}

	// HJ : 평가 값을 취소 한다.
	function delete( $rateValue ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();

		// HJ : 알맞은 thread DB 필드 선택
		if($rateValue == SIM_LIKE){
			$targetField = 'thread_score2';
		} else if($rateValue == SIM_DISLIKE){
			$targetField = 'thread_score3';
		}

		// HJ : thread 에서 알맞은 필드 값을 받아오고 1 빼기
		$oldCountArray = $dbw->selectFieldValues(
				'thread',
				$targetField,
				array( 'thread_id' => $this->ThreadID ),
				__METHOD__
			);		
		$oldCount = $oldCountArray[0];
		$newCount = $oldCount - 1;

		// HJ : thread DB에 update()
		$dbw->update(
				'thread',
				array( $targetField => $newCount ),
				array( 'thread_id' => $this->ThreadID ),
				__METHOD__
			);

		// HJ : sim_rate 에 Row 지우기
		$dbw->delete(
				'sim_rate',
				array(
					'rate_thread_id' => $this->ThreadID,
					'username' => $this->Username
				),
				__METHOD__
			);
		$dbw->commit();

		return $newCount;
		//$this->clearCache();
	}

	// HJ : 평가 값을 DB에 저장
	function insert( $rateValue ) {
		global $wgRequest;
		$dbw = wfGetDB( DB_MASTER );
		wfSuppressWarnings(); // E_STRICT whining
		$rateDate = date( 'Y-m-d H:i:s' );
		wfRestoreWarnings();
		if ( $this->UserAlreadyRated() == false ) {
			
			// HJ : 알맞은 thread DB 필드 선택
			if($rateValue == SIM_LIKE){
				$targetField = 'thread_score2';
			} else if($rateValue == SIM_DISLIKE){
				$targetField = 'thread_score3';
			}

			$dbw->begin();

			// HJ : thread DB 에서 알맞은 필드 값을 받아오고 1 더하기
			$oldCountArray = $dbw->selectFieldValues(
					'thread',
					$targetField,
					array( 'thread_id' => $this->ThreadID ),
					__METHOD__
				);		
			$oldCount = $oldCountArray[0];
			$newCount = 1 + $oldCount;

			// HJ : thread DB에 insert()
			$dbw->update(
					'thread',
					array( $targetField => $newCount ),
					array( 'thread_id' => $this->ThreadID ),
					__METHOD__
				);

			// HJ : sim_rate DB에 insert() 
			$dbw->insert(
					'sim_rate',
					array(
						'username' => $this->Username,
						'rate_user_id' => $this->Userid,
						'rate_thread_id' => $this->ThreadID,
						'rate_value' => $rateValue,
						'rate_date' => $rateDate,
						'rate_ip' => $wgRequest->getIP(),
					),
					__METHOD__
				);

			$dbw->commit();

			return $newCount;
			//$this->clearCache();

		}
	}

	// HJ : 유저가 이미 해당 페이지를 평가 했는지 판단
	function UserAlreadyRated() {
		$dbr = wfGetDB( DB_SLAVE );
		$s = $dbr->selectRow(
			'sim_rate',
			array( 'rate_value' ),
			array(
				'rate_thread_id' => $this->ThreadID,
				'username' => $this->Username
			),
			__METHOD__
		);
		if ( $s === false ) {
			return false;
		} else {
			return $s->rate_value;
		}
	}

}
