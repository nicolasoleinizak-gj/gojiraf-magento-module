<?php 

namespace Gojiraf\Gojiraf\Model\Api\Catalog;

use Zend_Db_Expr;
use Gojiraf\Gojiraf\Model\Api\Catalog\Product\SimpleProductBuilder;
use Gojiraf\Gojiraf\Model\Api\Catalog\Product\ConfigurableProductBuilder;

class Catalog
{
    protected $isDefaultStock;

    protected $objectManager;
    protected $productCollectionFactory;
    protected $productVisibility;
    protected $productStatus;

    protected $catalogVersion = "V.2.0.0";

    public function getCatalogVersion(){
        return $this->catalogVersion;
    }

    // /rest/V1/gojiraf/productlist/page/1?searchTerm=Camisa&limit=10&ids=23,31
    public function getProductList($page = 1, $limit = 10, $searchTerm = NULL, $ids = "", $filterByStock = true)
    {

        $this->objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        $this->productCollectionFactory = $this->objectManager->get('\Magento\Catalog\Model\ResourceModel\Product\CollectionFactory');
        $this->productStatus = $this->objectManager->get('\Magento\Catalog\Model\Product\Attribute\Source\Status');
        $this->productVisibility = $this->objectManager->get('\Magento\Catalog\Model\Product\Visibility');
        
        $this->isDefaultStock = $this->isDefaultStock();

        $productCollection = $this->prepareCollection($filterByStock);
        $filteredCollection = $this->filterCollection($productCollection, $page, $limit, $searchTerm, $ids);

        if (empty($filteredCollection->getData())){
            return [];
        }

        $productList = array();
        foreach ($filteredCollection as $productModel) {
            $productType = $productModel->getTypeId();
            $productBuilder = $this->getProductBuilder($productType);
            $productData = $productBuilder->getProductData($productModel);
            array_push($productList, $productData);
        }
        return $productList;
    }

    protected function prepareCollection($filterByStock)
    {
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addAttributeToSelect('*');
        $productCollection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
        $productCollection->setVisibility($this->productVisibility->getVisibleInSiteIds());
        
        if($filterByStock){
            if($this->isDefaultStock){
                $productCollection->setFlag('has_stock_status_filter', false);
                $productCollection->joinField(
                    'stock_item', 
                    'cataloginventory_stock_item', 
                    'is_in_stock', 
                    'product_id=entity_id', 
                    'is_in_stock=1'
                );
            } else {
                $getStockIdForCurrentWebsite = $this->objectManager->get('Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite');
                $stockId = $getStockIdForCurrentWebsite->execute();
                $productCollection
                ->getSelect()
                ->join(
                    array('stock' => new Zend_Db_Expr("(
                        SELECT 
                            sku,
                            quantity AS initial_qty
                        FROM
                            inventory_stock_".$stockId."
                    )"
                )),
                    'e.sku = stock.sku',
                    array('stock.initial_qty')
                )
                ->joinLeft(
                    array('reservation' => new Zend_Db_Expr("(
                        SELECT
                            sku,
                            SUM(quantity) as reservation_qty
                        FROM
                            inventory_reservation
                        WHERE
                            stock_id = ".$stockId."
                    )"
                    )),
                        'e.sku = reservation.sku',
                        array('reservation.reservation_qty')
                )
                ->where('(initial_qty + IFNULL(reservation_qty,0)) > ?', 0)
                ;
            }
        }
        return $productCollection;
    }

    protected function filterCollection($productCollection, $page, $limit, $searchTerm, $ids)
    {
        $offset = ($page == 0) ? 0 : $page * ($limit);

        // Si pide IDs de productos especificos, los filtramos.
        if (!empty($ids) && $ids != "undefined") {
            $productCollection->addAttributeToFilter('entity_id', array('in' => explode(",", $ids)));
        }
        if (!empty($searchTerm) && $searchTerm != "undefined") {
            $productCollection->addAttributeToFilter('name', array('like' => "%" .$searchTerm. "%"));
        }

        $productCollection->getSelect()
            ->limit($limit, $offset);

        return $productCollection;
    }

    
    protected function isDefaultStock()
    {
        $productMetadata = $this->objectManager->get('\Magento\Framework\App\ProductMetadataInterface');
        $magentoVersion = $productMetadata->getVersion();
        if(version_compare($magentoVersion, "2.3", '<')){
            return true;
        }
        
        $getStockIdForCurrentWebsite = $this->objectManager->get('Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite');
        $stockId = $getStockIdForCurrentWebsite->execute();
        $getSources = $this->objectManager->get('Magento\Inventory\Model\Source\Command\GetSourcesAssignedToStockOrderedByPriority');
        $sources = $getSources->execute($stockId);

        // If there are more than 1 source on active stock, is multisource
        if(count($sources) > 1){
            return false;
        }
        // If the active stock is not default, then is multisource
        if($sources[0]->getSourceCode() != 'default'){
            return false;
        }

        return true;
    }

    protected function getProductBuilder($productType){
        switch($productType){
            case 'simple':
               return new SimpleProductBuilder($this->isDefaultStock());
                break;
            case 'configurable':
                return new ConfigurableProductBuilder($this->isDefaultStock());
                break;
            default:
                break;
        }
    }
    
}
