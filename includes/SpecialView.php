<?php
namespace MediaWiki\Extension\Avatar;

use Xml;
use FormOptions;
use HTMLForm;
use PermissionsError;
use ManualLogEntry;
use SpecialPage;
use MediaWiki\MediaWikiServices;

class SpecialView extends SpecialPage {

	public function __construct() {
		parent::__construct('ViewAvatar');
	}

	public function execute($par) {
		// Shortcut by using $par
		if ($par) {
			$this->getOutput()->redirect($this->getPageTitle()->getLinkURL(array(
				'wpUsername' => $par,
			)));
			return;
		}

		$this->setHeaders();
		$this->outputHeader();

		// Parse options
		$opt = new FormOptions;
		$opt->add('wpUsername', '');
		$opt->add('wpDeleteReason', '');
		$opt->fetchValuesFromRequest($this->getRequest());

		// Parse user
		$user = $opt->getValue('wpUsername');
		$userObj = MediaWikiServices::getInstance()->getUserFactory()->newFromName($user);
		//$userObj = \User::newFromName($user);
		$userExists = $userObj && $userObj->getId() !== 0;

		// If current task is delete and user is not allowed
		$canDoAdmin = MediaWikiServices::getInstance()->getPermissionManager()->userHasRight($this->getUser(), 'avataradmin');
		if (!empty($opt->getValue('wpDeleteReason'))) {
			if (!$canDoAdmin) {
				throw new PermissionsError('avataradmin');
			}
			// Delete avatar if the user exists
			if ($userExists) {
				if (Avatar::deleteAvatar($userObj)) {
					global $wgAvatarLogInRC;

					$logEntry = new ManualLogEntry('avatar', 'delete');
					$logEntry->setPerformer($this->getUser());
					$logEntry->setTarget($userObj->getUserPage());
					$logEntry->setComment($opt->getValue('wpDeleteReason'));
					$logId = $logEntry->insert();
					$logEntry->publish($logId, $wgAvatarLogInRC ? 'rcandudp' : 'udp');
				}
			}
		}

		$this->showForm($user);

		if ($userExists) {
			$haveAvatar = Avatar::hasAvatar($userObj);

			if ($haveAvatar) {
				$html = Xml::tags('img', array(
					'src' => Avatar::getLinkFor($user, 'original') . '&nocache&ver=' . dechex(time()),
					'height' => 400,
				), '');
				$html = Xml::tags('p', array(), $html);
				$this->getOutput()->addHTML($html);

				// Add a delete button
				if ($canDoAdmin) {
					$this->showDeleteForm($user);
				}
			} else {
				$this->getOutput()->addWikiMsg('viewavatar-noavatar');
			}
		} else if ($user) {
			$this->getOutput()->addWikiMsg('viewavatar-nouser');
		}
	}
	private function showForm($user){
		$formDescriptor = [
			'user' => [
				'label-message' => 'viewavatar-username',
				'type' => 'user',
				'name' => 'wpUsername',
				'default' => $user
			],
		];
		$form = HTMLForm::factory('ooui',$formDescriptor,$this->getContext());
		$form
			->setMethod('get')
			->setWrapperLegendMsg("viewavatar-legend")
			->prepareForm()
			->displayForm(false);
	}
	private function showDeleteForm($user)
	{
		$formDescriptor = [
			'user' => [
				'type' => 'hidden',
				'name' => 'wpUsername',
				'default' => $user
			],
			'reason' => [
				'label-message' => 'viewavatar-delete-reason',
				'type' => 'text', // Input type
				'name' => 'wpDeleteReason'
			]
		];
		$deleteForm = HTMLForm::factory('ooui',$formDescriptor,$this->getContext());
		$deleteForm
			->setWrapperLegendMsg("viewavatar-delete-legend")
			->prepareForm()
			->displayForm(false);
	}
}
