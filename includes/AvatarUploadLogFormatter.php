<?php
namespace MediaWiki\Extension\Avatar;

use SpecialPage;
use LogFormatter;
use MediaWiki\MediaWikiServices;

class AvatarUploadLogFormatter extends LogFormatter {

	public function getActionLinks() {
		$user = $this->entry->getPerformerIdentity();
		$view = MediaWikiServices::getInstance()->getLinkRenderer()
			->makeKnownLink(SpecialPage::getTitleFor('ViewAvatar'),
				$this->msg('logentry-avatar-action-view')->escaped(),
				[],
				['wpUsername' => $user->getName()]
			);
		return $this->msg('parentheses')->rawParams($view)->escaped();

	}

}
