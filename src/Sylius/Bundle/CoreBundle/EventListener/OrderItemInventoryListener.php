<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\CoreBundle\EventListener;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Sylius\Bundle\CoreBundle\Model\OrderItemInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Order item inventory processing listener.
 *
 * @author Alexandre Bacco <alexandre.bacco@gmail.com>
 */
class OrderItemInventoryListener
{
    /**
     * Event Dispatcher
     *
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * Constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $item = $args->getEntity();

        if (!$this->supports($item)) {
            return;
        }

        $this->eventDispatcher->dispatch('sylius.order_item.pre_create', new GenericEvent($item));
    }

    public function preUpdate(LifecycleEventArgs $args)
    {
        $item = $args->getEntity();

        if (!$this->supports($item)) {
            return;
        }

        $this->eventDispatcher->dispatch('sylius.order_item.pre_update', new GenericEvent($item));
    }

    protected function supports($entity)
    {
        if (!$entity instanceof OrderItemInterface) {
            return false;
        }

        return true;
    }
}
