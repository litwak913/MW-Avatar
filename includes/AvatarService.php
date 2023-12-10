<?php
namespace MediaWiki\Extension\Avatar;

use Config;
use FileBackend;
use FSFileBackend;
use FileBackendGroup;
use MediaWiki\WikiMap\WikiMap;
use NullLockManager;
use MediaWiki\User\UserIdentity;

class AvatarService{
    private FileBackend $backend;
    private Config $cfg;
    public function __construct(FileBackendGroup $fbg,Config $cfg) {
        if ( !empty( $cfg->get( 'AvatarFileBackend' ) ) ) {
			$this->backend = $fbg->get(
				$cfg->get( 'AvatarFileBackend' )
			);
		} else {
            $avatarUpload = $cfg->get( 'AvatarUploadDirectory');
			$this->backend = new FSFileBackend( [
				// We just set the backend name to match the container to
				// avoid having to set another variable of the same value
				'name'           => "avatar-backend",
				'wikiId'         => WikiMap::getCurrentWikiId(),
				'lockManager'    => new NullLockManager( [] ),
				'containerPaths' => [ 'avatar' => $avatarUpload ],
				'fileMode'       => 0777,
				'obResetFunc'    => 'wfResetOutputBuffers',
				'streamMimeFunc' => [ 'StreamFile', 'contentTypeFromPath' ],
				'statusWrapper'  => [ 'Status', 'wrap' ],
			] );
		}
        $this->cfg=$cfg;
    }
    public function isExists(UserIdentity $user){
        if ( $user && $user->getId() ) {
            return $this->backend->fileExists(['src'=>$this->getBackendPath($user->getId())]);
        }
        return false;
    }
    public function getURL(UserIdentity $user,$size='original'){
        if ( $user && $user->getId() ) {
            $url=$this->backend->getFileHttpUrl(['src'=>$this->getBackendPath($user->getId(),$size)]);
            if (!$url){
                //fallback
                return $this->localURL($this->getAvatarPath($user->getId(),$size));
            } else{
                return $url;
            }
        }
        return null;
    }
    public function copyAvatar(string $src,int $id, $size='original'){

    }
    public function prepareAvatarDir(int $id){
        $dir=$this->backend->getContainerStoragePath('avatar') ."/{$id}";
        $this->backend->prepare(['dir'=>$dir]);
    }
    public function deleteAvatarDir(int $id){

    }
    public function normalizeResolution( $res ) {
		if ( $res === 'original' ) {
			return 'original';
		}
		$res = intval( $res );
        $allowedRes=$this->cfg->get("AllowedAvatarRes");
		foreach ( $allowedRes as $r ) {
			if ( $res <= $r ) {
				return $r;
			}
		}

		return 'original';
	}
    public function localURL($path){
        return $this->cfg->get("AvatarUploadPath").$path;
    }
    public function getAvatarPath(int $id, $size='original'){
        return "/{$id}/{$size}.png";
    }
    public function getBackendPath( int $id, $size='original') {
		return $this->backend->normalizeStoragePath(
			$this->backend->getContainerStoragePath('avatar') .$this->getAvatarPath($id,$size)
		);
	}
}