<?php
/**
 * SportsTeams extension -- provides networking functionality
 *
 * @file
 * @ingroup Extensions
 * @version 3.0
 * @date 7 July 2013
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
	'version' => '3.0',
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
$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['SportsTeams'] = $dir . 'SportsTeams.i18n.php';
$wgExtensionMessagesFiles['SportsTeamsAlias'] = $dir . 'SportsTeams.alias.php';

// ResourceLoader support for MediaWiki 1.17+
$sportsTeamsResourceTemplate = array(
	'localBasePath' => dirname( __FILE__ ),
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
$wgAutoloadClasses['SportsTeams'] = $dir . 'SportsTeamsClass.php';

// Special pages
$wgAutoloadClasses['AddFan'] = $dir . 'SpecialAddFan.php';
$wgSpecialPages['AddFan'] = 'AddFan';
$wgAutoloadClasses['FanHome'] = $dir . 'SpecialFanHome.php';
$wgSpecialPages['FanHome'] = 'FanHome';
$wgAutoloadClasses['RemoveFan'] = $dir . 'SpecialRemoveFan.php';
$wgSpecialPages['RemoveFan'] = 'RemoveFan';
$wgAutoloadClasses['SimilarFans'] = $dir . 'SpecialSimilarFans.php';
$wgSpecialPages['SimilarFans'] = 'SimilarFans';
$wgAutoloadClasses['SportsManagerLogo'] = $dir . 'SpecialSportsManagerLogo.php';
$wgSpecialPages['SportsManagerLogo'] = 'SportsManagerLogo';
$wgAutoloadClasses['SportsTeamsManager'] = $dir . 'SpecialSportsTeamsManager.php';
$wgSpecialPages['SportsTeamsManager'] = 'SportsTeamsManager';
$wgAutoloadClasses['SportsTeamsManagerLogo'] = $dir . 'SpecialSportsTeamsManagerLogo.php';
$wgSpecialPages['SportsTeamsManagerLogo'] = 'SportsTeamsManagerLogo';
$wgAutoloadClasses['TopNetworks'] = $dir . 'SpecialTopNetworks.php';
$wgSpecialPages['TopNetworks'] = 'TopNetworks';
// This special page was originally bundled with UserProfile
$wgAutoloadClasses['UpdateFavoriteTeams'] = $dir . 'SpecialUpdateFavoriteTeams.php';
$wgSpecialPages['UpdateFavoriteTeams'] = 'UpdateFavoriteTeams';
$wgAutoloadClasses['ViewFans'] = $dir . 'SpecialViewFans.php';
$wgSpecialPages['ViewFans'] = 'ViewFans';

$wgSpecialPageGroups['SimilarFans'] = 'users';
$wgSpecialPageGroups['TopNetworks'] = 'wiki'; // as per Special:Statistics

// API module used by Special:UpdateFavoriteTeams
$wgAutoloadClasses['ApiSportsTeams'] = $dir . 'ApiSportsTeams.php';
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
$wgAutoloadClasses['SportsTeamsHooks'] = $dir . 'SportsTeamsHooks.php';

// New stuff on the signup page
$wgHooks['AddNewAccount'][] = 'SportsTeamsHooks::addFavoriteTeam';
$wgHooks['BeforePageDisplay'][] = 'SportsTeamsHooks::addSportsTeamsToSignupPage';

$wgHooks['LoadExtensionSchemaUpdates'][] = 'SportsTeamsHooks::onLoadExtensionSchemaUpdates';
$wgHooks['RenameUserSQL'][] = 'SportsTeamsHooks::onRenameUserSQL';