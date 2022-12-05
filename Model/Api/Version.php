<?php 

namespace Gojiraf\Gojiraf\Model\Api;


class Version{

    // /rest/V1/gojiraf/version
    public function getVersion(){
        $moduleResource = \Magento\Framework\App\ObjectManager::getInstance()->get('Gojiraf\Gojiraf\Model\Api\Catalog');
        $moduleVersion = $moduleResource->getCatalogVersion();
        return $moduleVersion;
    }

}