<?php

use Wikimedia\AtEase\AtEase;

/**
 * SportsTeams API module
 *
 * @file
 * @ingroup API
 * @see https://www.mediawiki.org/wiki/API:Extensions#ApiSampleApiExtension.php
 */
class ApiSportsTeams extends ApiBase {

	/**
	 * The original incarnation of this code was an AJAX function called
	 * wfGetSportsTeams in SportsTeams_AjaxFunctions.php.
	 * That function was referenced by ../LoginReg/SpecialUserRegister.php and
	 * SpecialUpdateProfile_Sports.php.
	 * Said function was originally located in UserProfile/UserProfile_AjaxFunctions.php
	 *
	 * @return bool
	 */
	public function execute() {
		$user = $this->getUser();

		// Get the request parameters
		$params = $this->extractRequestParams();

		AtEase::suppressWarnings();
		$sportId = $params['sportId'];
		AtEase::restoreWarnings();

		// You only had one job...
		// @phan-suppress-next-line PhanImpossibleTypeComparison
		if ( !$sportId || $sportId === null || !is_numeric( $sportId ) ) {
			$this->dieWithError( [ 'apierror-missingparam', 'sportId' ], 'missingparam' );
		}

		$dbr = $this->getDB();

		$res = $dbr->select(
			'sport_team',
			[ 'team_id', 'team_name' ],
			[ 'team_sport_id' => intval( $sportId ) ],
			__METHOD__,
			[ 'ORDER BY' => 'team_name' ]
		);

		$output = [];
		$output['options'] = [];

		foreach ( $res as $row ) {
			$output['options'][] = [
				'id' => $row->team_id,
				'name' => $row->team_name
			];
		}

		// Top level
		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'result' => $output ]
		);

		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'sportId' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	/** @inheritDoc */
	public function getExamplesMessages() {
		return [
			'action=sportsteams&sportId=3' => 'apihelp-sportsteams-example-1'
		];
	}
}
