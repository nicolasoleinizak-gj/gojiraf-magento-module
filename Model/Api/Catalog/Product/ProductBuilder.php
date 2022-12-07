<?php

namespace Gojiraf\Gojiraf\Model\Api\Catalog\Product;

abstract class ProductBuilder
{
  protected $objectManager;
  protected $imageHelper;
  protected $variantAttributes;
  protected $isDefaultStock;
  protected $stockRegistry;
  
  public function __construct ($isDefaultStock)
  {
    $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $this->imageHelper = $this->objectManager->get('\Magento\Catalog\Helper\Image');
    $this->stockRegistry = $this->objectManager->get('\Magento\CatalogInventory\Api\StockRegistryInterface');
    $this->isDefaultStock = $isDefaultStock;
  }

  protected function getProductImage($product)
  {
    $imageUrl = $this
      ->imageHelper
      ->init($product, 'product_page_image')->setImageFile($product->getImage()) // image,small_image,thumbnail
      ->getUrl();
    return $imageUrl;
  }

  protected function getStock($productModel)
  {
    if($this->isDefaultStock){
        return $this->stockRegistry->getStockItem($productModel->getId())->getQty();
    } else {
        $getStockIdForCurrentWebsite = $this->objectManager->get('Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite');
        $getProductSalableQty = $this->objectManager->get('Magento\InventorySales\Model\GetProductSalableQty');
        $stockId = $getStockIdForCurrentWebsite->execute();
        $salableQuantity = $getProductSalableQty->execute($productModel->getSku(), $stockId);
        return $salableQuantity;
    }
  }

}