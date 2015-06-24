<?php
/**
 * SportsTeams extension -- provides networking functionality
 *
 * @file
 * @ingroup Extensions
 * @author Aaron Wright <aaron.wright@gmail.com>
 * @author Ashish Datta <ashish@setfive.com>
 * @author David Pean <david.pean@gmail.com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:SportsTeams Documentation
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'This is not a valid entry point to MediaWiki.' );
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'SportsTeams',
	'version' => '3.2',
	'author' => array(
		'Aaron Wright', 'Ashish Datta', 'David Pean', 'Jack Phoenix'
	),
	'description' => 'Networking functionality',
	'url' => 'https://www.mediawiki.org/wiki/Extension:SportsTeams',
);

// Google Maps API key for the map on Special:FanHome
// localhost key, as per http://snippets.dzone.com/posts/show/3201
$wgSportsTeamsGoogleAPIKey = 'ABQIAAAAnfs7bKE82qgb3Zc2YyS-oBT2yXp_ZAY8_ufC3CFXhHIE1NvwkxSySz_REpPq-4WZA27OwgbtyR3VcA';

// Set up i18n stuff
$wgMessagesDirs['SportsTeams'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['SportsTeamsAlias'] = __DIR__ . '/SportsTeams.alias.php';

// ResourceLoader support for MediaWiki 1.17+
$sportsTeamsResourceTemplate = array(
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'SportsTeams'
);

$wgResourceModules['ext.sportsTeams'] = $sportsTeamsResourceTemplate + array(
	'styles' => 'SportsTeams.css',
	'position' => 'top' // available since r85616
);

$wgResourceModules['ext.sportsTeams.fanHome'] = $sportsTeamsResourceTemplate + array(
	'scripts' => 'FanHome.js',
	'position' => 'top' // available since r85616
);

// DoubleCombo.js for the signup page hook (SportsTeamsHook::addSportsTeamsToSignupPage)
$wgResourceModules['ext.sportsTeams.doubleCombo'] = $sportsTeamsResourceTemplate + array(
	'scripts' => 'DoubleCombo.js',
);

$wgResourceModules['ext.sportsTeams.manager'] = $sportsTeamsResourceTemplate + array(
	'styles' => 'SportsTeamsManager.css',
	'position' => 'top' // available since r85616
);

$wgResourceModules['ext.sportsTeams.userProfile'] = $sportsTeamsResourceTemplate + array(
	'scripts' => 'SportsTeamsUserProfile.js',
	'messages' => array(
		'sportsteams-profile-button-add', 'sportsteams-profile-button-cancel',
		'sportsteams-profile-latest-thought', 'sportsteams-profile-view-all',
		'sportsteams-profile-characters-remaining',
		'sportsteams-profile-characters-remaining-hack'
	)
);

$wgResourceModules['ext.sportsTeams.updateFavoriteTeams'] = $sportsTeamsResourceTemplate + array(
	'scripts' => array( 'DoubleCombo.js', 'UpdateFavoriteTeams.js' )
);

// Autoload the classes
$wgAutoloadClasses['SportsTeams'] = __DIR__ . '/SportsTeamsClass.php';

// Special pages
$wgAutoloadClasses['AddFan'] = __DIR__ . '/SpecialAddFan.php';
$wgSpecialPages['AddFan'] = 'AddFan';
$wgAutoloadClasses['FanHome'] = __DIR__ . '/SpecialFanHome.php';
$wgSpecialPages['FanHome'] = 'FanHome';
$wgAutoloadClasses['RemoveFan'] = __DIR__ . '/SpecialRemoveFan.php';
$wgSpecialPages['RemoveFan'] = 'RemoveFan';
$wgAutoloadClasses['SimilarFans'] = __DIR__ . '/SpecialSimilarFans.php';
$wgSpecialPages['SimilarFans'] = 'SimilarFans';
$wgAutoloadClasses['SportsManagerLogo'] = __DIR__ . '/SpecialSportsManagerLogo.php';
$wgSpecialPages['SportsManagerLogo'] = 'SportsManagerLogo';
$wgAutoloadClasses['SportsTeamsManager'] = __DIR__ . '/SpecialSportsTeamsManager.php';
$wgSpecialPages['SportsTeamsManager'] = 'SportsTeamsManager';
$wgAutoloadClasses['SportsTeamsManagerLogo'] = __DIR__ . '/SpecialSportsTeamsManagerLogo.php';
$wgSpecialPages['SportsTeamsManagerLogo'] = 'SportsTeamsManagerLogo';
$wgAutoloadClasses['TopNetworks'] = __DIR__ . '/SpecialTopNetworks.php';
$wgSpecialPages['TopNetworks'] = 'TopNetworks';
// This special page was originally bundled with UserProfile
$wgAutoloadClasses['UpdateFavoriteTeams'] = __DIR__ . '/SpecialUpdateFavoriteTeams.php';
$wgSpecialPages['UpdateFavoriteTeams'] = 'UpdateFavoriteTeams';
$wgAutoloadClasses['ViewFans'] = __DIR__ . '/SpecialViewFans.php';
$wgSpecialPages['ViewFans'] = 'ViewFans';

// API module used by Special:UpdateFavoriteTeams
$wgAutoloadClasses['ApiSportsTeams'] = __DIR__ . '/ApiSportsTeams.php';
$wgAPIModules['sportsteams'] = 'ApiSportsTeams';

// New user right, required to edit sports teams via Special:SportsTeamsManager
$wgAvailableRights[] = 'sportsteamsmanager';
$wgGroupPermissions['sysop']['sportsteamsmanager'] = true;
$wgGroupPermissions['staff']['sportsteamsmanager'] = true;

// Hooked functions
// The functions in this file show the networks the user is in and their latest
// status update on their profile page
include( 'SportsTeamsUserProfile.php' );

// Database updater, etc.
$wgAutoloadClasses['SportsTeamsHooks'] = __DIR__ . '/SportsTeamsHooks.php';

// New stuff on the signup page
$wgHooks['AddNewAccount'][] = 'SportsTeamsHooks::addFavoriteTeam';
$wgHooks['BeforePageDisplay'][] = 'SportsTeamsHooks::addSportsTeamsToSignupPage';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'SportsTeamsHooks::onLoadExtensionSchemaUpdates';
$wgHooks['RenameUserSQL'][] = 'SportsTeamsHooks::onRenameUserSQL';
