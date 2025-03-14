<?php
namespace MediaWiki\Extension\Avatar;

use Html;
use ManualLogEntry;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use OOUI;
use OOUI\MessageWidget;
use PermissionsError;
use SpecialPage;
use UnlistedSpecialPage;
use UserBlockedError;
use Xml;

class SpecialUpload extends UnlistedSpecialPage {
	private HookContainer $hooks;

	public function __construct( HookContainer $hookContainer ) {
		parent::__construct( 'UploadAvatar' );
		$this->hooks = $hookContainer;
	}

	public function execute( $par ) {
		$this->checkReadOnly();
		$this->requireLogin( 'prefsnologintext2' );
		$config = $this->getConfig();
		$this->setHeaders();
		$this->outputHeader();
		$this->getOutput()->enableOOUI();
		$request = $this->getRequest();

		if ( $this->getUser()->getBlock() ) {
			throw new UserBlockedError( $this->getUser()->getBlock() );
		}

		if ( !MediaWikiServices::getInstance()->getPermissionManager()
					->userHasRight( $this->getUser(), 'avatarupload' ) ) {
			throw new PermissionsError( 'avatarupload' );
		}

		$this->getOutput()->addJsConfigVars( 'wgMaxAvatarResolution', $config->get( 'MaxAvatarResolution' ) );
		$this->getOutput()->addModules( 'ext.avatar.upload' );

		if ( $request->wasPosted() ) {
			if ( $this->processUpload() ) {
				$this->getOutput()->redirect( SpecialPage::getTitleFor( 'Preferences' )->getLinkURL() );
			}
		} else {
			// $this->displayMessage( '' );
		}
		$this->displayNewForm();
	}

	private function displayMessage( $msg ) {
		$message = new MessageWidget( [ 'type' => 'error','label' => $msg ] );
		$this->getOutput()->addHTML( $message );
	}

	private function processUpload() {
		$config = $this->getConfig();
		$request = $this->getRequest();
		$dataurl = $request->getVal( 'wpAvatar' );
		$user = $this->getUser();
		if ( !$dataurl || parse_url( $dataurl, PHP_URL_SCHEME ) !== 'data' ) {
			$this->displayMessage( $this->msg( 'avatar-notuploaded' ) );
			return false;
		}
		$this->hooks->run( 'BeforeUploadAvatar', [ $user,&$dataurl ] );
		$img = AvatarThumbnail::open( $dataurl );

		$maxAvatarResolution = $config->get( 'MaxAvatarResolution' );
		switch ( $img->type ) {
		case IMAGETYPE_GIF:
		case IMAGETYPE_PNG:
		case IMAGETYPE_JPEG:
			break;
		default:
			$this->displayMessage( $this->msg( 'avatar-invalid' ) );
			return false;
		}

		// Must be square
		if ( $img->width !== $img->height ) {
			$this->displayMessage( $this->msg( 'avatar-notsquare' ) );
			return false;
		}

		// Check if image is too small
		if ( $img->width < 32 || $img->height < 32 ) {
			$this->displayMessage( $this->msg( 'avatar-toosmall' ) );
			return false;
		}

		// Check if image is too big
		if ( $img->width > $maxAvatarResolution || $img->height > $maxAvatarResolution ) {
			$this->displayMessage( $this->msg( 'avatar-toolarge' ) );
			return false;
		}

		Avatar::deleteAvatar( $user );

		// Avatar directories
		$uploadDir = $config->get( 'AvatarUploadDirectory' ) . '/' . $this->getUser()->getId() . '/';
		@mkdir( $uploadDir, 0755, true );

		// We do this to convert format to png
		$img->createThumbnail( $maxAvatarResolution, $uploadDir . 'original.png' );

		// We only create thumbnail with default resolution here. Others are generated on demand
		$defaultAvatarRes = $config->get( 'DefaultAvatarRes' );
		$img->createThumbnail( $defaultAvatarRes, $uploadDir . $defaultAvatarRes . '.png' );

		$img->cleanup();
		$this->hooks->run( 'AfterUploadAvatar', [ $user ] );
		$this->displayMessage( $this->msg( 'avatar-saved' ) );

		// global $wgAvatarLogInRC;
		$logInRC = $config->get( 'AvatarLogInRC' );
		$logEntry = new ManualLogEntry( 'avatar', 'upload' );
		$logEntry->setPerformer( $this->getUser() );
		$logEntry->setTarget( $this->getUser()->getUserPage() );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId, $logInRC ? 'rcandudp' : 'udp' );

		return true;
	}

	public function displayNewForm() {
		$btnSelect = new OOUI\ButtonWidget( [
			'infusable' => true,
			'label' => $this->msg( 'uploadavatar-selectfile' )->text(),
			'id' => 'select-button',
			'flags' => [ 'primary', 'progressive' ],
			'icon' => 'upload'
		] );
		$btnSubmit = new OOUI\ButtonInputWidget( [
			'type' => 'submit',
			'infusable' => true,
			'id' => 'submit-button',
			'disabled' => true,
			'label' => $this->msg( 'uploadavatar-submit' )->text(),
		] );
		$html = '<p></p>';
		$html .= Html::hidden( 'wpAvatar', '' );
		$html .= $btnSelect . $btnSubmit;
		$html = Xml::tags( 'form', [ 'action' => $this->getPageTitle()->getLinkURL(), 'method' => 'post' ], $html );
		$this->getOutput()->addWikiMsg( 'clearyourcache' );
		$this->getOutput()->addHTML( $html );
	}
}
