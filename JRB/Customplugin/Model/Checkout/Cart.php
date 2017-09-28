<?php

namespace JRB\Customplugin\Model\Checkout;

class Cart extends \Magento\Checkout\Model\Cart
{
    public function addProduct($productInfo, $requestInfo = null)
    {
        
        $requestInfo['qty'] = 10; // increasing quantity to 10
        
        $product = $this->_getProduct($productInfo);
        $request = $this->_getProductRequest($requestInfo);
        $productId = $product->getId();

        if ($productId) {
            $stockItem = $this->stockRegistry->getStockItem($productId, $product->getStore()->getWebsiteId());
            $minimumQty = $stockItem->getMinSaleQty();
            //If product was not found in cart and there is set minimal qty for it
            if ($minimumQty
                && $minimumQty > 0
                && !$request->getQty()
                && !$this->getQuote()->hasProductId($productId)
            ) {
                $request->setQty($minimumQty);
            }
        }

        if ($productId) {
            try {
                $result = $this->getQuote()->addProduct($product, $request);
            } catch (\Magento\Framework\Exception\LocalizedException $e) {
                $this->_checkoutSession->setUseNotice(false);
                $result = $e->getMessage();
            }
            /**
             * String we can get if prepare process has error
             */
            if (is_string($result)) {
                if ($product->hasOptionsValidationFail()) {
                    $redirectUrl = $product->getUrlModel()->getUrl(
                        $product,
                        ['_query' => ['startcustomization' => 1]]
                    );
                } else {
                    $redirectUrl = $product->getProductUrl();
                }
                $this->_checkoutSession->setRedirectUrl($redirectUrl);
                if ($this->_checkoutSession->getUseNotice() === null) {
                    $this->_checkoutSession->setUseNotice(true);
                }
                throw new \Magento\Framework\Exception\LocalizedException(__($result));
            }
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('The product does not exist.'));
        }

        $this->_eventManager->dispatch(
            'checkout_cart_product_add_after',
            ['quote_item' => $result, 'product' => $product]
        );
        $this->_checkoutSession->setLastAddedProductId($productId);
        return $this;
    }

}
