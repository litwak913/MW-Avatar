<?php
namespace MediaWiki\Extension\Avatar;

use BaseTemplate;
use Html;
use MediaWiki\MediaWikiServices;
use Parser;
use PPFrame;
use Skin;
use SpecialPage;

class Hooks {

	public static function onGetPreferences( \User $user, &$preferences ) {
		$link = MediaWikiServices::getInstance()->getLinkRenderer()
			->makeLink( SpecialPage::getTitleFor( "UploadAvatar" ), wfMessage( 'uploadavatar' )->text() );
		$html = Html::element( 'img', [ 'src' => Avatar::getLinkFor( $user->getName() ),'width' => '32' ] );
		$preferences['editavatar'] = [
			'type' => 'info',
			'raw' => true,
			'label-message' => 'prefs-editavatar',
			'default' => $html . $link,
			'section' => 'personal/info',
		];

		return true;
	}

	public static function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'avatar', [ self::class,'renderTagAvatar' ] );
	}

	public static function renderTagAvatar( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgDefaultAvatarRes;
		return Html::element( 'img', [
			'src' => Avatar::getLinkFor( $args['username'] ),
			'width' => $args['width'] ?? $wgDefaultAvatarRes
		] );
	}

	public static function onSidebarBeforeOutput( Skin $skin, &$sidebar ) {
		$user = $skin->getRelevantUser();

		if ( $user ) {
			$sidebar['TOOLBOX']['viewavatar'] = [
				'id' => 't-viewavatar',
				'text' => wfMessage( 'sidebar-viewavatar' )->text(),
				'href' => SpecialPage::getTitleFor( 'ViewAvatar' )->getLocalURL( [
					'wpUsername' => $user->getName(),
				] ),
			];
		}
	}

	public static function onSetup() {
		global $wgAvatarUploadPath, $wgAvatarUploadDirectory;

		if ( $wgAvatarUploadPath === false ) {
			global $wgUploadPath;
			$wgAvatarUploadPath = $wgUploadPath . '/avatars';
		}

		if ( $wgAvatarUploadDirectory === false ) {
			global $wgUploadDirectory;
			$wgAvatarUploadDirectory = $wgUploadDirectory . '/avatars';
		}
	}
}
