<?php

namespace Gojiraf\Gojiraf\Model\Api;

class Store
{
  protected $objectManager;
  protected $scopeConfig;
  protected $storeManager;
  protected $websiteCollectionFactory;
  
  public function getStoreData()
  {
    $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $this->scopeConfig = $this->objectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface');
    $this->storeManager = $this->objectManager->get('\Magento\Store\Model\StoreManagerInterface');
    $this->websiteCollectionFactory = $this->objectManager->get('Magento\Store\Model\ResourceModel\Website\CollectionFactory');

    $storeEmail = $this->scopeConfig->getValue(
      'trans_email/ident_sales/email',
      \Magento\Store\Model\ScopeInterface::SCOPE_STORE
    );

    $storeName = $this->storeManager->getStore()->getName();

    $storesCollection = $this->storeManager->getStores();
    
    $stores = [];

    foreach($storesCollection as $store){
      array_push($stores, [
        'name' => $store->getBaseUrl()
      ]);
    };

    return json_encode([
      'email' => $storeEmail,
      'name' => $storeName,
      'websites' => $stores
    ]);
  }
}