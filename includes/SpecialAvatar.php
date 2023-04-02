<?php
namespace MediaWiki\Extension\Avatar;

use MediaWiki\MediaWikiServices;
use UnlistedSpecialPage;
class SpecialAvatar extends UnlistedSpecialPage {
    public function __construct()
    {
        parent::__construct('Avatar');
    }
    public function execute($par)
    {
        global $wgOut, $wgRequest;
		$wgOut->disable();
        $query = $wgRequest->getQueryValues();
        if (isset($query['wpUsername'])) {
            $username = $query['wpUsername'];
        
            if (isset($query['wpRes'])) {
                $res = Avatar::normalizeResolution($query['wpRes']);
            } else {
                global $wgDefaultAvatarRes;
                $res = $wgDefaultAvatarRes;
            }
        
            $user = MediaWikiServices::getInstance()->getUserFactory()->newFromName($username);
            if ($user) {
                $path = Avatar::getAvatar($user, $res);
            }
        }
        $response = $wgRequest->response();
        if ($path === null) {
            // We use send custom header, in order to control cache
            $response->statusHeader('302');
        
            if (!isset($query['wpNocache'])) {
                // Cache longer time if it is not the default avatar
                // As it is unlikely to be deleted
                $response->header('Cache-Control: public, max-age=3600');
            }
        
            global $wgDefaultAvatar;
            $response->header('Location: ' . $wgDefaultAvatar);
        }
        global $wgAvatarServingMethod;
        switch($wgAvatarServingMethod) {
            case 'readfile':
                global $wgAvatarUploadDirectory;
                $response->header('Cache-Control: public, max-age=86400');
                $response->header('Content-Type: image/png');
                readfile($wgAvatarUploadDirectory . $path);
                break;
            case 'accel':
                global $wgAvatarUploadPath;
                $response->header('Cache-Control: public, max-age=86400');
                $response->header('Content-Type: image/png');
                $response->header('X-Accel-Redirect: ' . $wgAvatarUploadPath . $path);
                break;
            case 'sendfile':
                global $wgAvatarUploadDirectory;
                $response->header('Cache-Control: public, max-age=86400');
                $response->header('Content-Type: image/png');
                $response->header('X-SendFile: ' . $wgAvatarUploadDirectory . $path);
                break;
            case 'redirect':
            default:
                $ver = '';
            
                // ver will be propagated to the relocated image
                if (isset($query['wpVer'])) {
                    $ver = $query['wpVer'];
                } else {
                    global $wgVersionAvatar;
                    if ($wgVersionAvatar) {
                        global $wgAvatarUploadDirectory;
                        $ver = filemtime($wgAvatarUploadDirectory . $path);
                    }
                }
            
                if ($ver) {
                    if (strpos($path, '?') !== false) {
                        $path .= '&wpVer=' . $ver;
                    } else {
                        $path .= '?wpVer=' . $ver;
                    }
                }
            
                // We use send custom header, in order to control cache
                $response->statusHeader('302');
            
                if (!isset($query['wpNocache'])) {
                    // Cache longer time if it is not the default avatar
                    // As it is unlikely to be deleted
                    $response->header('Cache-Control: public, max-age=86400');
                }
            
                global $wgAvatarUploadPath;
                $response->header('Location: ' . $wgAvatarUploadPath . $path);
                break;
            }
    }
}