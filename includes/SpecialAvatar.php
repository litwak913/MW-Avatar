<?php
namespace MediaWiki\Extension\Avatar;

use MediaWiki\User\UserIdentityLookup;
use UnlistedSpecialPage;

class SpecialAvatar extends UnlistedSpecialPage {
	private $uil;
	public function __construct(UserIdentityLookup $uil) {
		parent::__construct( 'Avatar' );
		$this->uil=$uil;
	}

	public function execute( $par ) {
		$config = $this->getConfig();
		$this->getOutput()->disable();
		$query = $this->getRequest()->getQueryValues();
		$response = $this->getRequest()->response();
		if ( isset( $query['wpRes'] ) ) {
			$res = Avatar::normalizeResolution( $query['wpRes'] );
		} else {
			$res = $config->get( 'DefaultAvatarRes' );
		}
		if ( isset( $query['wpUsername'] ) ) {
			$username = $query['wpUsername'];
			$user = $this->uil->getUserIdentityByName( $username );
		} elseif ( isset( $query['wpUserID'] ) ) {
			$uid = $query['wpUserID'];
			$user = $this->uil->getUserIdentityByUserId( $uid );
		} else {
			wfHttpError( 400, 'Bad Request', 'Missing parameter wpUsername or wpUserID.' );
			return;
		}
		if ( $user !== null ) {
			$path = Avatar::getAvatar( $user, $res );
		} else {
			$anonAvatar = $config->get( "AnonymousAvatar" );
			if ( $anonAvatar ) {
				$response->statusHeader( '302' );
				if ( !isset( $query['wpNocache'] ) ) {
					$response->header( 'Cache-Control: public, max-age=3600' );
				}
				$response->header( 'Location: ' . $anonAvatar );
				return;
			} else {
				wfHttpError( 404, 'Not Found', 'No such user.' );
				return;
			}
		}
		if ( $path === null ) {
			$defaultAvatar = $config->get( "DefaultAvatar" );
			if ( $defaultAvatar !== 'IDENTICON' ) {
				// We use send custom header, in order to control cache
				$response->statusHeader( '302' );

				if ( !isset( $query['wpNocache'] ) ) {
					// Cache longer time if it is not the default avatar
					// As it is unlikely to be deleted
					$response->header( 'Cache-Control: public, max-age=3600' );
				}
				$response->header( 'Location: ' . $defaultAvatar );
				return;
			} else {
				$path = Avatar::createIdenticon( $user, $res );
			}
		}
		$uploadDirectory = $config->get( 'AvatarUploadDirectory' );
		$uploadPath = $config->get( 'AvatarUploadPath' );
		switch ( $config->get( 'AvatarServingMethod' ) ) {
			case 'readfile':
				$response->header( 'Cache-Control: public, max-age=86400' );
				$response->header( 'Content-Type: image/png' );
				readfile( $uploadDirectory . $path );
				break;
			case 'accel':
				$response->header( 'Cache-Control: public, max-age=86400' );
				$response->header( 'Content-Type: image/png' );
				$response->header( 'X-Accel-Redirect: ' . $uploadPath . $path );
				break;
			case 'sendfile':
				$response->header( 'Cache-Control: public, max-age=86400' );
				$response->header( 'Content-Type: image/png' );
				$response->header( 'X-SendFile: ' . $uploadDirectory . $path );
				break;
			case 'redirect':
			default:
				$ver = '';

				// ver will be propagated to the relocated image
				if ( isset( $query['wpVer'] ) ) {
					$ver = $query['wpVer'];
				} else {
					if ( $config->get( 'VersionAvatar' ) ) {
						$ver = filemtime( $uploadDirectory . $path );
					}
				}

				if ( $ver ) {
					if ( strpos( $path, '?' ) !== false ) {
						$path .= '&wpVer=' . $ver;
					} else {
						$path .= '?wpVer=' . $ver;
					}
				}

				// We use send custom header, in order to control cache
				$response->statusHeader( '302' );

				if ( !isset( $query['wpNocache'] ) ) {
					// Cache longer time if it is not the default avatar
					// As it is unlikely to be deleted
					$response->header( 'Cache-Control: public, max-age=86400' );
				}
				$response->header( 'Location: ' . $uploadPath . $path );
				break;
		}
	}
}
