<?php
namespace MediaWiki\Extension\Avatar;

use UnlistedSpecialPage;
class SpecialAvatar extends UnlistedSpecialPage {
    public function __construct()
    {
        parent::__construct('Avatar');
    }
    public function execute($par)
    {
        
    }
}