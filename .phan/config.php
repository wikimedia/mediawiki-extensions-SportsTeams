<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/BlogPage',
		'../../extensions/SocialProfile',
		'../../extensions/UserStatus',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/BlogPage',
		'../../extensions/SocialProfile',
		'../../extensions/UserStatus',
	]
);

$cfg['suppress_issue_types'] = array_merge( $cfg['suppress_issue_types'], [
	# False positive due to the way HTML is constructed in some places
	'PhanPluginDuplicateAdjacentStatement',
	# Required parameter follows optional, so fucking what?
	'PhanParamReqAfterOpt',
	# Tracked as T198154 (wfImageArchiveDir)
	'PhanUndeclaredFunction',
	# Noise
	'PhanPluginDuplicateConditionalTernaryDuplication',
	'PhanTypeMismatchArgumentNullableInternal',
	'PhanTypeSuspiciousStringExpression',
	'PhanPossiblyUndeclaredVariable',
	'PhanPluginDuplicateConditionalNullCoalescing',
	'PhanTypeMismatchArgumentInternal',
	'PhanTypeMismatchReturn',
	'PhanRedundantCondition',
	# Technically accurate
	'PhanUndeclaredVariable'
] );

return $cfg;
