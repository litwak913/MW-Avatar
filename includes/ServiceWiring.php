<?php
use MediaWiki\Extension\Avatar\AvatarService;
use MediaWiki\MediaWikiServices;

return [
	'Avatar.AvatarService' => function ( MediaWikiServices $services ) : AvatarService {
		return new AvatarService(
            $services->getFileBackendGroup(),
            $services->getMainConfig()
        );
	}
];