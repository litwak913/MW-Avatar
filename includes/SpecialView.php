<?php
namespace MediaWiki\Extension\Avatar;

use FormOptions;
use HTMLForm;
use ManualLogEntry;
use MediaWiki\MediaWikiServices;
use OOUI\MessageWidget;
use PermissionsError;
use SpecialPage;
use Xml;

class SpecialView extends SpecialPage {

	public function __construct() {
		parent::__construct( 'ViewAvatar' );
	}

	public function execute( $par ) {
		// Shortcut by using $par
		if ( $par ) {
			$this->getOutput()->redirect( $this->getPageTitle()->getLinkURL( [
				'wpUsername' => $par,
			] ) );
			return;
		}

		$this->setHeaders();
		$this->outputHeader();

		// Parse options
		$opt = new FormOptions;
		$opt->add( 'wpUsername', '' );
		$opt->add( 'wpDeleteReason', '' );
		$opt->fetchValuesFromRequest( $this->getRequest() );

		// Parse user
		$user = $opt->getValue( 'wpUsername' );
		$userObj = MediaWikiServices::getInstance()->getUserFactory()->newFromName( $user );
		// $userObj = \User::newFromName($user);
		$this->showForm( $user );
		$userExists = $userObj && $userObj->getId() !== 0;

		// If current task is delete and user is not allowed
		$canDoAdmin = MediaWikiServices::getInstance()->getPermissionManager()
					->userHasRight( $this->getUser(), 'avataradmin' );
		if ( !empty( $opt->getValue( 'wpDeleteReason' ) ) ) {
			if ( !$canDoAdmin ) {
				throw new PermissionsError( 'avataradmin' );
			}
			// Delete avatar if the user exists
			if ( $userExists ) {
				if ( Avatar::deleteAvatar( $userObj ) ) {
					$this->displayMessage( $this->msg( 'viewavatar-delete-done', $userObj->getName() ), 'success' );
					$logInRC = $this->getConfig()->get( 'AvatarLogInRC' );
					$logEntry = new ManualLogEntry( 'avatar', 'delete' );
					$logEntry->setPerformer( $this->getUser() );
					$logEntry->setTarget( $userObj->getUserPage() );
					$logEntry->setComment( $opt->getValue( 'wpDeleteReason' ) );
					$logId = $logEntry->insert();
					$logEntry->publish( $logId, $logInRC ? 'rcandudp' : 'udp' );
					return;
				}
			}
		}

		if ( $userExists ) {
			$haveAvatar = Avatar::hasAvatar( $userObj );

			if ( $haveAvatar ) {
				$html = Xml::tags( 'img', [
					'src' => Avatar::getLinkFor( $user, 'original' ) . '&wpNocache&wpVer=' . dechex( time() ),
					'height' => 400,
				], '' );
				$html = Xml::tags( 'p', [], $html );
				$this->getOutput()->addHTML( $html );

				// Add a delete button
				if ( $canDoAdmin && !MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
					$this->showDeleteForm( $user );
				}
			} else {
				$this->displayMessage( $this->msg( 'viewavatar-noavatar' )->text(), 'warning' );
			}
		} elseif ( $user ) {
			$this->displayMessage( $this->msg( 'viewavatar-nouser' )->text(), 'warning' );
		}
	}

	private function showForm( $user ) {
		$formDescriptor = [
			'user' => [
				'label-message' => 'viewavatar-username',
				'type' => 'user',
				'name' => 'wpUsername',
				'default' => $user
			],
		];
		$form = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$form
			->setMethod( 'get' )
			->setWrapperLegendMsg( "viewavatar-legend" )
			->prepareForm()
			->displayForm( false );
	}

	private function showDeleteForm( $user ) {
		$formDescriptor = [
			'user' => [
				'type' => 'hidden',
				'name' => 'wpUsername',
				'default' => $user
			],
			'reason' => [
				'label-message' => 'viewavatar-delete-reason',
				'type' => 'text',
				'name' => 'wpDeleteReason'
			]
		];
		$deleteForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$deleteForm
			->setWrapperLegendMsg( "viewavatar-delete-legend" )
			->prepareForm()
			->displayForm( false );
	}

	private function displayMessage( $msg, $type ) {
		$message = new MessageWidget( [ 'type' => $type,'label' => $msg ] );
		$this->getOutput()->addHTML( $message );
	}
}
