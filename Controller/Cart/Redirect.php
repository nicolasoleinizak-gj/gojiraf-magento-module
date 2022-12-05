<?php
namespace Gojiraf\Gojiraf\Controller\Cart;

use \Magento\Framework\App\Action\Context;

class Redirect extends \Magento\Framework\App\Action\Action
{
    protected $_checkoutSession;
    protected $_cart;
    protected $_quote;
    protected $_productloader;
    protected $_cartRepository;
    protected $_guestCartRepository;
    protected $_messageInterface;
    public function __construct(Context $context,
                                \Magento\Checkout\Model\Session $checkoutSession,
                                \Magento\Checkout\Model\Cart $cart,
                                \Magento\Catalog\Model\ProductFactory $productloader,
                                \Magento\Quote\Api\CartRepositoryInterface $cartRepository,
                                \Magento\Customer\Model\Session $customerSession,
                                \Magento\Quote\Model\GuestCart\GuestCartRepository $guestCartRepository,
                                \Magento\Framework\Message\ManagerInterface $messageInterface
                            )
    {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_cart = $cart;
        $this->_cartRepository = $cartRepository;
        $this->_productloader = $productloader;
        $this->_guestCartRepository = $guestCartRepository;
        $this->_messageInterface = $messageInterface;
    }

    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $cartId = $params["CART_ID"];
        $formKey = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\Data\Form\FormKey');

        $this->_quote = $this->_checkoutSession->getQuote();
        if (empty($cartId)) {
            $this->getResponse()->setStatusCode(\Magento\Framework\App\Response\Http::STATUS_CODE_503);
            return $this->displayError("EMPTY_CART_ID","Ocurrió un error al asociar tu carrito de compras.");
        }
        
        try {
            $guestCart = $this->_guestCartRepository->get($cartId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $this->getResponse()->setStatusCode(\Magento\Framework\App\Response\Http::STATUS_CODE_503);
            return $this->displayError("GET_CART_ID","Ocurrió un error al asociar tu carrito de compras.");
        }

        foreach ($guestCart->getItems() as $item) {
            $request = new \Magento\Framework\DataObject();
            $params = array(
                        'form_key' => $formKey,
                        'product' => $item->getProductId(), 
                        'qty'   => $item->getQty()              
                    ); 
            $request->setData($params);

             
            $product = $this->_productloader->create()->load($item->getProductId());
            if ($product->isSaleable()) {
               $this->_cart->addProduct($product, $request);
            }else{
                $this->_messageInterface->addNotice("El producto {$item->getName()} no tiene stock.");
            }
        }
        $this->_cart->save();
        $this->_quote->save();
        $this->_quote->collectTotals();
        $this->_cartRepository->save($this->_quote);
        $this->_cart->getQuote()->setTotalsCollectedFlag(false)->collectTotals()->save();
        $this->redirectSuccess();
    }
    
    public function redirectSuccess(){
        $this->getResponse()->setRedirect('/checkout/cart/');
    }

    public function displayError($errorCode, $message){
        $jsonFactory = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Framework\Controller\Result\JsonFactory');
        $data = ['error_code' => $errorCode, 'message' => $message];
        $result = $jsonFactory->create();
        $response = $result->setData($data);
        return $response;
    }


}
