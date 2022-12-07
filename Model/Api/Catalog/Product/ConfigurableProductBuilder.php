<?php

namespace Gojiraf\Gojiraf\Model\Api\Catalog\Product;

use Gojiraf\Gojiraf\Model\Api\Catalog\Product\ProductBuilder;
use Gojiraf\Gojiraf\Model\Api\Catalog\Product\ProductBuilderInterface;

class ConfigurableProductBuilder extends ProductBuilder implements ProductBuilderInterface{

  public function getProductData($productModel)
  {
    $productArray = array(
      "id" => $productModel->getId(),
      "sku" => $productModel->getSku(),
      "description" => $productModel->getName(),
      "price" => "",
      "imageUrl" => "",
      "variants" => array(),
      "variantOptions" => array()
    );

    $this->variantAttributes = $productModel
      ->getTypeInstance()
      ->getUsedProductAttributes($productModel);

    $highestPrice = 0;
    $variantsArray = array();
    $optionsArray = array();
    $childProducts = $productModel->getTypeInstance()
        ->getUsedProducts($productModel);
    foreach ($childProducts as $child)
    {
        //Si la variante no tiene stock, la ignoramos.
        $stockStatus = $this->stockRegistry->getStockItem($child->getId());
        
        if($this->isDefaultStock){
            if($stockStatus->getData('is_in_stock') == 0 || $stockStatus->getQty() == 0){
                continue;
            }
        } else {
            if($this->getStock($child) == 0){
                continue;
            }
        }

        //Acomodamos datos de las posibles variantes
        $option = array();
        foreach ($this->variantAttributes as $attribute)
        {
            $attributeValue = $child->getResource()
            ->getAttribute($attribute->getAttributeCode())
            ->getFrontend()
            ->getValue($child);
            array_push($option, $attributeValue);
            if (!isset($variantsArray[$attribute->getFrontendLabel() ]))
            {
                $variantsArray[$attribute->getFrontendLabel() ] = array();
                array_push($variantsArray[$attribute->getFrontendLabel() ], $attributeValue);
            }
            else
            {
                if (!in_array($attributeValue, $variantsArray[$attribute->getFrontendLabel() ]))
                {
                    array_push($variantsArray[$attribute->getFrontendLabel() ], $attributeValue);
                }
            }
        }
        $imageUrl = $this->getProductImage($child);
        $childPrice = (float)number_format($child->getFinalPrice() , 2, ",", ""); 
        $childOriginalPrice = (float)number_format($child->getPriceInfo()->getPrice('regular_price')->getValue() , 2, ",", "");
        if ($childOriginalPrice > $highestPrice) {
            $highestPrice = $childOriginalPrice;
        }
        //Acomodamos datos del producto simple
        array_push($optionsArray, array(
            "option" => $option,
            "sku" => $child->getSku() ,
            "price" => $childPrice ,
            "imageUrl" => $imageUrl,
            "originalPrice" => $childOriginalPrice,
            "description" => $child->getName(),
            "stock" => $this->getStock($child)
        ));
    }

    foreach ($variantsArray as $key => $variants)
    {
        array_push($productArray["variants"], array(
            "name" => $key,
            "options" => $variants
        ));
    }

    //aca las variantOptions
    if (empty($optionsArray)) {
        return array();
    }
    $productArray["variantOptions"] = $optionsArray;
    $configProductPrice = (float)number_format($child->getPriceInfo()->getPrice('regular_price')->getValue() , 2, ",", "");
    $productArray["price"] =  ($configProductPrice == 0 ) ? $highestPrice : $configProductPrice ;
    $productArray["imageUrl"] = $this->getProductImage($productModel);

    return $productArray;
  }

}