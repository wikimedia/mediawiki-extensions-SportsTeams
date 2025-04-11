<?php

use MediaWiki\Html\Html;
use MediaWiki\Request\WebRequestUpload;
use MediaWiki\Shell\Shell;
use Wikimedia\AtEase\AtEase;

/**
 * A special page to upload images for sport teams.
 * This is mostly copied from an old version of Special:Upload and changed a
 * bit.
 *
 * The images will be stored in $wgUploadDirectory/teams_logos.
 *
 * @file
 * @ingroup Extensions
 */
class SportsTeamsManagerLogo extends UnlistedSpecialPage {
	public $mUploadFile;
	public $mUploadDescription;
	public $mIgnoreWarning;
	public $mUploadSaveName;
	public $mUploadTempName;
	public $mUploadSize;
	public $mUploadOldVersion;
	public $mUploadCopyStatus;
	public $mUploadSource;
	public $mReUpload;
	public $mAction;
	public $mUpload;
	public $mOname;
	public $mSessionKey;
	public $mWatchthis;
	public $mStashed;
	public $mDestFile;
	public $mTokenOk;
	public $teamLogosUploadDirectory;
	public $fileExtensions;
	public $team_id;

	/**
	 * Constructor -- set up the new special page
	 */
	public function __construct() {
		parent::__construct( 'SportsTeamsManagerLogo', 'sportsteamsmanager' );
	}

	/**
	 * Show the special page
	 *
	 * @param int|null $par Parameter (team ID) passed to the special page, if any
	 */
	public function execute( $par ) {
		$this->team_id = $this->getRequest()->getInt( 'id', $par );

		// Don't use setHeaders() b/c this special page has no proper title
		// $this->setHeaders();

		// Set the robot policies, etc.
		$out = $this->getOutput();
		$out->setArticleRelated( false );
		$out->setRobotPolicy( 'noindex,nofollow' );

		$this->checkPermissions();

		$this->initLogo();
		$this->executeLogo();
	}

	function initLogo() {
		$this->fileExtensions = [ 'gif', 'jpg', 'jpeg', 'png' ];

		$request = $this->getRequest();
		if ( !$request->wasPosted() ) {
			# GET requests just give the main form; no data except wpDestfile.
			return;
		}
		$this->team_id            = $request->getInt( 'id' );
		$this->mIgnoreWarning     = $request->getCheck( 'wpIgnoreWarning' );
		$this->mReUpload          = $request->getCheck( 'wpReUpload' );
		$this->mUpload            = $request->getCheck( 'wpUpload' );

		$this->mUploadDescription = $request->getText( 'wpUploadDescription' );
		$this->mUploadCopyStatus  = $request->getText( 'wpUploadCopyStatus' );
		$this->mUploadSource      = $request->getText( 'wpUploadSource' );
		$this->mWatchthis         = $request->getBool( 'wpWatchthis' );
		wfDebug( __METHOD__ . ": watchthis is: '$this->mWatchthis'\n" );

		$this->mAction            = $request->getVal( 'action' );
		$this->mSessionKey        = $request->getInt( 'wpSessionKey' );
		if ( $this->mSessionKey && isset( $_SESSION['wsUploadData'][$this->mSessionKey] ) ) {
			/**
			 * Confirming a temporarily stashed upload.
			 * We don't want path names to be forged, so we keep
			 * them in the session on the server and just give
			 * an opaque key to the user agent.
			 */
			$data = $_SESSION['wsUploadData'][$this->mSessionKey];
			$this->mUploadTempName   = $data['mUploadTempName'];
			$this->mUploadSize       = $data['mUploadSize'];
			$this->mOname            = $data['mOname'];
			$this->mStashed	 	     = true;
		} else {
			/**
			 * Check for a newly uploaded file.
			 */
			$this->mUploadTempName = $request->getFileTempname( 'wpUploadFile' );
			$file = new WebRequestUpload( $request, 'wpUploadFile' );
			$this->mUploadSize     = $file->getSize();
			$this->mOname          = $request->getFileName( 'wpUploadFile' );
			$this->mSessionKey     = false;
			$this->mStashed        = false;
		}

		// If it was posted check for the token (no remote POST'ing with user credentials)
		$token = $request->getVal( 'wpEditToken' );
		$this->mTokenOk = $this->getUser()->matchEditToken( $token );
	}

	/**
	 * Start doing stuff
	 */
	public function executeLogo() {
		global $wgEnableUploads, $wgUploadDirectory;

		$out = $this->getOutput();
		$user = $this->getUser();

		$this->teamLogosUploadDirectory = $wgUploadDirectory . '/team_logos';

		/** Show an error message if file upload is disabled */
		if ( !$wgEnableUploads ) {
			$out->addWikiMsg( 'uploaddisabled' );
			return;
		}

		/** Various rights checks */
		if ( !$user->isAllowed( 'upload' ) ) {
			throw new PermissionsError( 'upload' );
		}

		// Check blocks
		$block = $user->getBlock();
		if ( $block || $user->isBlockedFromUpload() ) {
			throw new UserBlockedError(
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
				$block,
				$user,
				$this->getLanguage(),
				$this->getRequest()->getIP()
			);
		}

		$this->checkReadOnly();

		/** Check if the image directory is writable, this is a common mistake */
		if ( !is_writable( $wgUploadDirectory ) ) {
			$out->addWikiMsg( 'upload_directory_read_only', $wgUploadDirectory );
			return;
		}

		if ( $this->mReUpload ) {
			$this->unsaveUploadedFile();
			$this->mainUploadForm();
		} elseif ( $this->mAction == 'submit' || $this->mUpload ) {
			if ( $this->mTokenOk ) {
				$this->processUpload();
			} else {
				// Possible CSRF attempt or something...
				$this->mainUploadForm( $this->msg( 'session_fail_preview' )->parse() );
			}
		} else {
			$this->mainUploadForm();
		}
	}

	/**
	 * Really do the upload
	 * Checks are made in SpecialUpload::execute()
	 *
	 * @return mixed
	 */
	private function processUpload() {
		/**
		 * If there was no filename or a zero size given, give up quick.
		 */
		if ( trim( $this->mOname ) == '' || !$this->mUploadSize ) {
			return $this->mainUploadForm( '<li>' . $this->msg( 'emptyfile' )->escaped() . '</li>' );
		}

		# Chop off any directories in the given filename
		if ( $this->mDestFile ) {
			$basename = basename( $this->mDestFile );
		} else {
			$basename = basename( $this->mOname );
		}

		/**
		 * We'll want to blacklist against *any* 'extension', and use
		 * only the final one for the whitelist.
		 */
		[ $partname, $ext ] = UploadBase::splitExtensions( $basename );
		if ( count( $ext ) ) {
			$finalExt = $ext[count( $ext ) - 1];
		} else {
			$finalExt = '';
		}
		$fullExt = implode( '.', $ext );

		$this->mUploadSaveName = $basename;
		$filtered = $basename;

		/* Don't allow users to override the blacklist (check file extension) */
		global $wgStrictFileExtensions, $wgFileBlacklist;

		if ( UploadBase::checkFileExtensionList( $ext, $wgFileBlacklist ) ||
			( $wgStrictFileExtensions &&
				!UploadBase::checkFileExtension( $finalExt, $this->fileExtensions ) ) ) {
			return $this->uploadError( $this->msg( 'filetype-banned', htmlspecialchars( $fullExt ) )->escaped() );
		}

		/**
		 * Look at the contents of the file; if we can recognize the
		 * type but it's corrupt or data of the wrong type, we should
		 * probably not accept it.
		 */
		if ( !$this->mStashed ) {
			// @phan-suppress-next-line SecurityCheck-PathTraversal False positive
			$veri = $this->verify( $this->mUploadTempName, $finalExt );

			if ( !$veri->isGood() ) {
				// it's a wiki error...
				return $this->uploadError( $this->getOutput()->parseAsInterface( $veri->getWikiText() ) );
			}
		}

		/**
		 * Check for non-fatal conditions
		 */
		if ( !$this->mIgnoreWarning ) {
			$warning = '';

			global $wgCheckFileExtensions;
			if ( $wgCheckFileExtensions ) {
				if ( !UploadBase::checkFileExtension( $finalExt, $this->fileExtensions ) ) {
					$warning .= '<li>' . $this->msg( 'filetype-banned', htmlspecialchars( $fullExt ) )->escaped() . '</li>';
				}
			}

			global $wgUploadSizeWarning;
			if ( $wgUploadSizeWarning && ( $this->mUploadSize > $wgUploadSizeWarning ) ) {
				$lang = $this->getLanguage();
				$wsize = $lang->formatSize( $wgUploadSizeWarning );
				$asize = $lang->formatSize( $this->mUploadSize );
				$warning .= '<li>' . $this->msg( 'large-file', $wsize, $asize )->escaped() . '</li>';
			}

			if ( $this->mUploadSize == 0 ) {
				$warning .= '<li>' . $this->msg( 'emptyfile' )->escaped() . '</li>';
			}

			if ( $warning != '' ) {
				/**
				 * Stash the file in a temporary location; the user can choose
				 * to let it through and we'll complete the upload then.
				 */
				return $this->uploadWarning( $warning );
			}
		}

		/**
		 * Try actually saving the thing...
		 * It will show an error form on failure.
		 */
		$status = $this->saveUploadedFile(
			$this->mUploadSaveName,
			$this->mUploadTempName,
			strtoupper( $fullExt )
		);

		if ( $status > 0 ) {
			$this->showSuccess( $status );
		}
	}

	/**
	 * Create the team image thumbnails, either with ImageMagick or GD.
	 *
	 * @param string $imageSrc Path to the temporary file
	 * @param string $ext File extension (gif, jpg, png); de facto unused when using GD
	 * @param string $imgDest <team ID>_<size code>, e.g. 20_l for a large image for team ID #20
	 * @param int $thumbWidth Thumbnail image width in pixels
	 */
	function createThumbnail( $imageSrc, $ext, $imgDest, $thumbWidth ) {
		global $wgUseImageMagick, $wgImageMagickConvertCommand;

		[ $origWidth, $origHeight, $typeCode ] = getimagesize( $imageSrc );

		// ImageMagick is enabled
		if ( $wgUseImageMagick ) {
			if ( $origWidth < $thumbWidth ) {
				$thumbWidth = $origWidth;
			}
			$thumbHeight = ( $thumbWidth * $origHeight / $origWidth );
			$border = '';
			if ( $thumbHeight < $thumbWidth ) {
				$border = ' -bordercolor white  -border  0x' . ( ( $thumbWidth - $thumbHeight ) / 2 );
			}
			if ( $typeCode == 2 ) {
				wfShellExec(
					Shell::escape( $wgImageMagickConvertCommand ) . ' -size ' . $thumbWidth .
					'x' . $thumbWidth . ' -resize ' . $thumbWidth .
					'    -quality 100 ' . $border . ' ' . Shell::escape( $imageSrc ) . ' ' .
					$this->teamLogosUploadDirectory . '/' . $imgDest . '.jpg'
				);
			}
			if ( $typeCode == 1 ) {
				wfShellExec(
					Shell::escape( $wgImageMagickConvertCommand ) . ' -size ' . $thumbWidth .
					'x' . $thumbWidth . ' -resize ' . $thumbWidth . '  ' .
					Shell::escape( $imageSrc ) . ' ' . $border . ' ' .
					$this->teamLogosUploadDirectory . '/' . $imgDest . '.gif'
				);
			}
			if ( $typeCode == 3 ) {
				wfShellExec(
					Shell::escape( $wgImageMagickConvertCommand ) . ' -size ' . $thumbWidth .
					'x' . $thumbWidth . ' -resize ' . $thumbWidth . '   ' .
					Shell::escape( $imageSrc ) . ' ' .
					$this->teamLogosUploadDirectory . '/' . $imgDest . '.png'
				);
			}
			// ImageMagick is not enabled, so fall back to PHP's GD library
		} else {
			// Get the image size, used in calculations later.
			switch ( $typeCode ) {
				case '1':
					$fullImage = imagecreatefromgif( $imageSrc );
					$ext = 'gif';
					break;
				case '2':
					$fullImage = imagecreatefromjpeg( $imageSrc );
					$ext = 'jpg';
					break;
				case '3':
					$fullImage = imagecreatefrompng( $imageSrc );
					$ext = 'png';
					break;
			}

			$scale = ( $thumbWidth / $origWidth );

			// Create our thumbnail size, so we can resize to this, and save it.
			$tnImage = imagecreatetruecolor(
				$origWidth * $scale,
				$origHeight * $scale
			);

			// Resize the image.
			imagecopyresampled(
				$tnImage,
				$fullImage,
				0, 0, 0, 0,
				$origWidth * $scale,
				$origHeight * $scale,
				$origWidth,
				$origHeight
			);

			// Create a new image thumbnail.
			if ( $typeCode == 1 ) {
				imagegif( $tnImage, $imageSrc );
			} elseif ( $typeCode == 2 ) {
				imagejpeg( $tnImage, $imageSrc );
			} elseif ( $typeCode == 3 ) {
				imagepng( $tnImage, $imageSrc );
			}

			// Clean up.
			imagedestroy( $fullImage );
			imagedestroy( $tnImage );

			// Copy the thumb
			copy(
				$imageSrc,
				$this->teamLogosUploadDirectory . '/' . $imgDest . '.' . $ext
			);
		}
	}

	/**
	 * Move the uploaded file from its temporary location to the final
	 * destination. If a previous version of the file exists, move
	 * it into the archive subdirectory.
	 *
	 * @todo If the later save fails, we may have disappeared the original file.
	 *
	 * @param string $saveName Unused
	 * @param string $tempName Full path to the temporary file
	 * @param string $ext File extension
	 * @return int
	 */
	function saveUploadedFile( $saveName, $tempName, $ext ) {
		$this->createThumbnail( $tempName, $ext, $this->team_id . '_l', 100 );
		$this->createThumbnail( $tempName, $ext, $this->team_id . '_m', 50 );
		$this->createThumbnail( $tempName, $ext, $this->team_id . '_s', 25 );

		$type = 0;
		if ( $ext == 'JPG' && is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_l.jpg' ) ) {
			$type = 2;
		}
		if ( $ext == 'GIF' && is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_l.gif' ) ) {
			$type = 1;
		}
		if ( $ext == 'PNG' && is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_l.png' ) ) {
			$type = 3;
		}

		if ( $ext != 'JPG' ) {
			if ( is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_s.jpg' ) ) {
				unlink( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_s.jpg' );
			}
			if ( is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_m.jpg' ) ) {
				unlink( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_m.jpg' );
			}
			if ( is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_l.jpg' ) ) {
				unlink( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_l.jpg' );
			}
		}
		if ( $ext != 'GIF' ) {
			if ( is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_s.gif' ) ) {
				unlink( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_s.gif' );
			}
			if ( is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_m.gif' ) ) {
				unlink( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_m.gif' );
			}
			if ( is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_l.gif' ) ) {
				unlink( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_l.gif' );
			}
		}
		if ( $ext != 'PNG' ) {
			if ( is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_s.png' ) ) {
				unlink( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_s.png' );
			}
			if ( is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_m.png' ) ) {
				unlink( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_m.png' );
			}
			if ( is_file( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_l.png' ) ) {
				unlink( $this->teamLogosUploadDirectory . '/' . $this->team_id . '_l.png' );
			}
		}

		if ( $type <= 0 ) {
			$stash = $this->teamLogosUploadDirectory;
			throw new FatalError( $this->msg( 'filecopyerror', $tempName, $stash )->escaped() );
		}

		return $type;
	}

	/**
	 * Stash a file in a temporary directory for later processing
	 * after the user has confirmed it.
	 *
	 * If the user doesn't explicitly cancel or accept, these files
	 * can accumulate in the temp directory.
	 *
	 * @param string $saveName - the destination filename
	 * @param string $tempName - the source temporary file to save
	 * @return string - full path the stashed file, or false on failure
	 */
	private function saveTempUploadedFile( $saveName, $tempName ) {
		$uploadPath = $this->getConfig()->get( 'UploadPath' );
		$stash = $uploadPath . '/temp/' . gmdate( 'YmdHis' ) . '!' . $saveName;

		if ( !move_uploaded_file( $tempName, $stash ) ) {
			throw new FatalError( $this->msg( 'filecopyerror', $tempName, $stash )->escaped() );
		}

		return $stash;
	}

	/**
	 * Stash a file in a temporary directory for later processing,
	 * and save the necessary descriptive info into the session.
	 * Returns a key value which will be passed through a form
	 * to pick up the path info on a later invocation.
	 *
	 * @return int
	 */
	private function stashSession() {
		$stash = $this->saveTempUploadedFile(
			$this->mUploadSaveName,
			$this->mUploadTempName
		);

		if ( !$stash ) {
			# Couldn't save the file.
			return false;
		}

		$key = mt_rand( 0, 0x7fffffff );
		$_SESSION['wsUploadData'][$key] = [
			'mUploadTempName' => $stash,
			'mUploadSize'     => $this->mUploadSize,
			'mOname'          => $this->mOname
		];
		return $key;
	}

	/**
	 * Remove a temporarily kept file stashed by saveTempUploadedFile().
	 */
	private function unsaveUploadedFile() {
		AtEase::suppressWarnings();
		$success = unlink( $this->mUploadTempName );
		AtEase::restoreWarnings();
		if ( !$success ) {
			throw new FatalError( $this->msg( 'filedeleteerror', $this->mUploadTempName )->escaped() );
		}
	}

	/**
	 * Show some text and linkage on successful upload.
	 *
	 * @param int $status
	 */
	private function showSuccess( $status ) {
		global $wgUploadPath;

		$ext = 'jpg';

		$output = '<h2>' . $this->msg( 'sportsteams-logo-upload-success' )->escaped() . '</h2>';
		$output .= '<h5>' . $this->msg( 'sportsteams-logo-images-below' )->escaped() . '</h5>';
		if ( $status == 1 ) {
			$ext = 'gif';
		}
		if ( $status == 2 ) {
			$ext = 'jpg';
		}
		if ( $status == 3 ) {
			$ext = 'png';
		}

		$output .= '<table cellspacing="0" cellpadding="5">';
		$output .= '<tr>
			<td valign="top" style="color: #666666; font-weight: 800">' .
			$this->msg( 'sportsteams-logo-size-large' )->escaped() . '</td>
			<td>
				<img src="' . $wgUploadPath . '/team_logos/' . $this->team_id . '_l.' . $ext . '?ts=' . rand() . ' alt="" />
			</td>
		</tr>
		<tr>
			<td valign="top" style="color: #666666; font-weight: 800">' .
				$this->msg( 'sportsteams-logo-size-medium' )->escaped() .
			'</td>
			<td>
				<img src="' . $wgUploadPath . '/team_logos/' . $this->team_id . '_m.' . $ext . '?ts=' . rand() . '" alt="" />
			</td>
		</tr>
		<tr>
			<td valign="top" style="color: #666666; font-weight: 800">' .
				$this->msg( 'sportsteams-logo-size-small' )->escaped() .
			'</td>
			<td>
				<img src="' . $wgUploadPath . '/team_logos/' . $this->team_id . '_s.' . $ext . '?ts=' . rand() . '" alt="" />
			</td>
		</tr>
		<tr>
			<td>
				<input type="button" onclick="javascript:history.go(-1)" value="' .
					$this->msg( 'sportsteams-logo-go-back' )->escaped() . '" />
			</td>
		</tr>';

		$stm = SpecialPage::getTitleFor( 'SportsTeamsManager' );
		$output .= '<tr><td><a href="' . htmlspecialchars( $stm->getFullURL() ) . '">' .
			$this->msg( 'sportsteams-logo-back-to-list' )->escaped() . '</a> |';
		$output .= ' <a href="' . htmlspecialchars( $stm->getFullURL( "id={$this->team_id}" ) ) . '">' .
			$this->msg( 'sportsteams-logo-back-to-team' )->escaped() . '</a></td></tr>';
		$output .= '</table>';

		$this->getOutput()->addHTML( $output );
	}

	/**
	 * @param string $error as HTML
	 */
	private function uploadError( $error ) {
		$out = $this->getOutput();
		$sub = $this->msg( 'uploadwarning' )->escaped();

		$out->addHTML( "<h2>{$sub}</h2>\n" );
		$out->addHTML( "<h4 class=\"error\">{$error}</h4>\n" );

		$action = htmlspecialchars( $this->getPageTitle()->getLocalURL( 'action=submit' ) );

		$out->addHTML(
			'<br />
			<form id="uploaderror" method="post" enctype="multipart/form-data" action="' . $action . '">
				<input type="submit" onclick="javascript:history.go(-1)" value="' .
				$this->msg( 'sportsteams-logo-go-back' )->escaped() . '" />
			</form>'
		);
	}

	/**
	 * There's something wrong with this file, not enough to reject it
	 * totally but we require manual intervention to save it for real.
	 * Stash it away, then present a form asking to confirm or cancel.
	 *
	 * @param string $warning as HTML
	 */
	private function uploadWarning( $warning ) {
		global $wgUseCopyrightUpload;

		$out = $this->getOutput();

		$this->mSessionKey = $this->stashSession();
		if ( !$this->mSessionKey ) {
			# Couldn't save file; an error has been displayed so let's go.
			return;
		}

		$sub = $this->msg( 'uploadwarning' )->escaped();
		$out->addHTML( "<h2>{$sub}</h2>\n" );
		$out->addHTML( "<ul class=\"warning\">{$warning}</ul><br />\n" );

		$action = htmlspecialchars( $this->getPageTitle()->getLocalURL( 'action=submit' ) );

		if ( $wgUseCopyrightUpload ) {
			$copyright = "
	<input type='hidden' name='wpUploadCopyStatus' value=\"" . htmlspecialchars( $this->mUploadCopyStatus ) . "\" />
	<input type='hidden' name='wpUploadSource' value=\"" . htmlspecialchars( $this->mUploadSource ) . "\" />
	";
		} else {
			$copyright = '';
		}

		$out->addHTML( "
	<form id='uploadwarning' method='post' enctype='multipart/form-data' action='$action'>
		<input type='hidden' name='team_id' value=\"" . ( $this->team_id ) . "\" />
		<input type='hidden' name='wpIgnoreWarning' value='1' />
		<input type='hidden' name='wpSessionKey' value=\"" . htmlspecialchars( $this->mSessionKey ) . "\" />
		<input type='hidden' name='wpUploadDescription' value=\"" . htmlspecialchars( $this->mUploadDescription ) . "\" />
		<input type='hidden' name='wpDestFile' value=\"" . htmlspecialchars( $this->mDestFile ) . "\" />
		<input type='hidden' name='wpWatchthis' value=\"" . intval( $this->mWatchthis ) . "\" />
	{$copyright}
	<table border=\"0\">
		<tr>

			<tr>
				<td align=\"right\">
					<input tabindex=\"2\" type=\"submit\" onclick=\"javascript:history.go(-1)\" value=\"" . $this->msg( 'sportsteams-logo-back' )->escaped() . '" />
				</td>

			</tr>
		</tr>
	</table></form>' . "\n" );
	}

	/**
	 * Displays the main upload form, optionally with a highlighted
	 * error message up at the top.
	 *
	 * @param string $msg as HTML
	 */
	private function mainUploadForm( $msg = '' ) {
		global $wgUseCopyrightUpload, $wgUploadPath;

		$out = $this->getOutput();

		if ( $msg != '' ) {
			$sub = $this->msg( 'uploaderror' )->escaped();
			$out->addHTML( "<h2>{$sub}</h2>\n" .
				"<h4 class='error'>{$msg}</h4>\n" );
		}

		$ulb = $this->msg( 'uploadbtn' )->escaped();

		$source = null;

		if ( $wgUseCopyrightUpload ) {
			$source = "
	<td align='right' nowrap='nowrap'>" . $this->msg( 'filestatus' )->escaped() . "</td>
	<td><input tabindex='3' type='text' name=\"wpUploadCopyStatus\" value=\"" .
			htmlspecialchars( $this->mUploadCopyStatus ) . "\" size='40' /></td>
	</tr><tr>
	<td align='right'>" . $this->msg( 'filesource' )->escaped() . "</td>
	<td><input tabindex='4' type='text' name='wpUploadSource' value=\"" .
			htmlspecialchars( $this->mUploadSource ) . "\" style='width:100px' /></td>
	";
		}

		$team_logo = SportsTeams::getTeamLogo( $this->team_id, 'l' );
		if ( $team_logo != '' ) {
			$output = '<table>
				<tr>
					<td style="color: #666666; font-weight: 800">' .
						$this->msg( 'sportsteams-logo-current-image' )->escaped() .
					'</td>
				</tr>
				<tr>
					<td>
						<img src="' . $wgUploadPath . '/team_logos/' . $team_logo . '" border="0" alt="no logo" />
					</td>
				</tr>
			</table>
			<br />';
			$out->addHTML( $output );
		}

		$token = Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );

		$out->addHTML( "
	<form id='upload' method='post' enctype='multipart/form-data' action=\"\">
	<table border='0'><tr>

	<td style='color:#666666;font-weight:800'>" . $this->msg( 'sportsteams-logo-image-instructions' )->parseAsBlock() . "<br />
	<input tabindex='1' type='file' name='wpUploadFile' id='wpUploadFile' style='width:100px' />
	</td></tr><tr>
	{$source}
	</tr>
	<tr><td>
	{$token}
	<input tabindex='5' type='submit' name='wpUpload' value=\"{$ulb}\" />
	</td></tr></table></form>\n" );
	}

	/**
	 * Verifies that it's ok to include the uploaded file
	 *
	 * @param string $tmpfile the full path opf the temporary file to verify
	 * @param string $extension The filename extension that the file is to be served with
	 * @return Status object
	 */
	function verify( $tmpfile, $extension ) {
		# magically determine mime type
		$magic = \MediaWiki\MediaWikiServices::getInstance()->getMimeAnalyzer();
		$mime = $magic->guessMimeType( $tmpfile, false );

		# check mime type, if desired
		global $wgVerifyMimeType;
		if ( $wgVerifyMimeType ) {
			# check mime type against file extension
			if ( !UploadBase::verifyExtension( $mime, $extension ) ) {
				return Status::newFatal( 'filetype-mime-mismatch', $extension, $mime );
			}

			# check mime type blacklist
			global $wgMimeTypeBlacklist;
			if ( $wgMimeTypeBlacklist !== null
				&& UploadBase::checkFileExtension( $mime, $wgMimeTypeBlacklist ) ) {
				return Status::newFatal( 'badfiletype', htmlspecialchars( $mime ) );
			}
		}

		# check for HTML-ish code and JavaScript
		if ( UploadBase::detectScript( $tmpfile, $mime, $extension ) ) {
			return Status::newFatal( 'uploadscripted' );
		}

		/**
		 * Scan the uploaded file for viruses
		 */
		$virus = UploadBase::detectVirus( $tmpfile );
		if ( $virus ) {
			return Status::newFatal( 'uploadvirus', htmlspecialchars( $virus ) );
		}

		wfDebug( __METHOD__ . ": all clear; passing.\n" );
		return Status::newGood();
	}
}
