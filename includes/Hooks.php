<?php
namespace MediaWiki\Extension\Avatar;

use Skin;
use SpecialPage;
use BaseTemplate;
use MediaWiki\MediaWikiServices;

class Hooks {

	public static function onGetPreferences(\User $user, &$preferences) {
		$link = MediaWikiServices::getInstance()->getLinkRenderer()
			->makeLink(SpecialPage::getTitleFor("UploadAvatar"), wfMessage('uploadavatar')->text());

		$preferences['editavatar'] = array(
			'type' => 'info',
			'raw' => true,
			'label-message' => 'prefs-editavatar',
			'default' => '<img src="' . Avatar::getLinkForNew($user->getName()) . '" width="32"></img> ' . $link,
			'section' => 'personal/info',
		);

		return true;
	}
	
	public static function onSidebarBeforeOutput(Skin $skin, &$sidebar) {
		$user = $skin->getRelevantUser();
		
		if ($user) {
			$sidebar['TOOLBOX'][] = [
				'text' => wfMessage('sidebar-viewavatar')->text(),
				'href' => SpecialPage::getTitleFor('ViewAvatar')->getLocalURL([
					'wpUsername' => $user->getName(),
				]),
			];
		}
	}

	public static function onBaseTemplateToolbox(BaseTemplate &$baseTemplate, array &$toolbox) {
		if (isset($baseTemplate->data['nav_urls']['viewavatar'])
			&& $baseTemplate->data['nav_urls']['viewavatar']) {
			$toolbox['viewavatar'] = $baseTemplate->data['nav_urls']['viewavatar'];
			$toolbox['viewavatar']['id'] = 't-viewavatar';
		}
	}

	public static function onSetup() {
		global $wgAvatarUploadPath, $wgAvatarUploadDirectory;

		if ($wgAvatarUploadPath === false) {
			global $wgUploadPath;
			$wgAvatarUploadPath = $wgUploadPath . '/avatars';
		}

		if ($wgAvatarUploadDirectory === false) {
			global $wgUploadDirectory;
			$wgAvatarUploadDirectory = $wgUploadDirectory . '/avatars';
		}
	}
}
