<?php
namespace MediaWiki\Extension\Avatar;

use Identicon;
use MediaWiki\User\UserIdentity;
use SpecialPage;
use User;

class Avatar {

	public static function getLinkFor( $username, $res = false ) {
		$args = [
			'wpUsername' => $username
		];
		if ( $res !== false ) {
			$args['wpRes'] = $res;
		}
		return SpecialPage::getTitleFor( 'Avatar' )->getLocalURL( $args );
	}

	public static function normalizeResolution( $res ) {
		if ( $res === 'original' ) {
			return 'original';
		}
		$res = intval( $res );

		global $wgAllowedAvatarRes;
		foreach ( $wgAllowedAvatarRes as $r ) {
			if ( $res <= $r ) {
				return $r;
			}
		}

		return 'original';
	}

	public static function getAvatar( UserIdentity $user, $res ) {
		$path = null;

		// If user exists
		if ( $user && $user->getId() ) {
			global $wgAvatarUploadDirectory;
			$avatarPath = "/{$user->getId()}/$res.png";

			// Check if requested avatar thumbnail exists
			if ( file_exists( $wgAvatarUploadDirectory . $avatarPath ) ) {
				$path = $avatarPath;
			} elseif ( $res !== 'original' ) {
				// Dynamically generate upon request
				$originalAvatarPath = "/{$user->getId()}/original.png";
				if ( file_exists( $wgAvatarUploadDirectory . $originalAvatarPath ) ) {
					$image = AvatarThumbnail::open( $wgAvatarUploadDirectory . $originalAvatarPath );
					$image->createThumbnail( $res, $wgAvatarUploadDirectory . $avatarPath );
					$image->cleanup();
					$path = $avatarPath;
				}
			}
		}

		return $path;
	}

	public static function hasAvatar( UserIdentity $user ) {
		return self::getAvatar( $user, 'original' ) !== null;
	}

	public static function createIdenticon( UserIdentity $user, $res ) {
		global $wgMaxAvatarResolution;
		global $wgAvatarUploadDirectory;
		$uploadDir = $wgAvatarUploadDirectory . '/' . $user->getId() . '/';
		@mkdir( $uploadDir, 0755, true );
		$identicon = new Identicon\Identicon( new Identicon\Generator\GdGenerator() );
		$dataurl = $identicon->getImageDataUri( $user->getName(), $wgMaxAvatarResolution, null, '#ffffff' );
		$img = AvatarThumbnail::open( $dataurl );
		$img->createThumbnail( $wgMaxAvatarResolution, $uploadDir . 'original.png' );
		$img->createThumbnail( $res, $uploadDir . $res . '.png' );
		return "/{$user->getId()}/$res.png";
	}

	public static function deleteAvatar( UserIdentity $user ) {
		global $wgAvatarUploadDirectory;
		$dirPath = $wgAvatarUploadDirectory . "/{$user->getId()}/";
		if ( !is_dir( $dirPath ) ) {
			return false;
		}
		$files = glob( $dirPath . '*', GLOB_MARK );
		foreach ( $files as $file ) {
			unlink( $file );
		}
		rmdir( $dirPath );
		return true;
	}

}
