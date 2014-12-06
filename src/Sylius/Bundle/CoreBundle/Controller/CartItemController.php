<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\CoreBundle\Controller;

use Sylius\Bundle\CartBundle\Controller\CartItemController as BaseCartItemController;
use Sylius\Component\Cart\Event\CartItemEvent;
use Sylius\Component\Cart\SyliusCartEvents;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Resource\Event\FlashEvent;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;

class CartItemController extends BaseCartItemController
{
    public function editAction($id, Request $request)
    {
        $cart = $this->getCurrentCart();
        /** @var OrderItem */
        $item = $this->getRepository()->find($id);

        $eventDispatcher = $this->getEventDispatcher();

        if (!$item || false === $cart->hasItem($item)) {
            // Write flash message
            $eventDispatcher->dispatch(SyliusCartEvents::ITEM_EDIT_ERROR, new FlashEvent());

            return $this->redirectToCartSummary();
        }

        $form = $this->get('form.factory')->create('sylius_cart_item', $item, array('product' => $item->getProduct()));

        if ($request->isMethod('POST') && $form->bind($request)->isValid()) {
            $event = new CartItemEvent($cart, $item);
            $event->isFresh(true);

            // Update models
            $eventDispatcher->dispatch(SyliusCartEvents::ITEM_EDIT_INITIALIZE, $event);
            $eventDispatcher->dispatch(SyliusCartEvents::CART_CHANGE, new GenericEvent($cart));
            $eventDispatcher->dispatch(SyliusCartEvents::CART_SAVE_INITIALIZE, $event);

            // Write flash message
            $eventDispatcher->dispatch(SyliusCartEvents::ITEM_EDIT_COMPLETED, new FlashEvent());

            return $this->redirectToCartSummary();
        }

        $view = $this
            ->view()
            ->setTemplate($this->config->getTemplate('editItem.html'))
            ->setData(array(
                'item' => $item,
                'form' => $form->createView()
            ))
        ;

        return $this->handleView($view);
    }
}
