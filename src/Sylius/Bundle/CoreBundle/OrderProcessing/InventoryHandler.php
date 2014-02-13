<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\CoreBundle\OrderProcessing;

use Sylius\Bundle\CoreBundle\Model\OrderInterface;
use Sylius\Bundle\CoreBundle\Model\OrderItemInterface;
use Sylius\Bundle\CoreBundle\Model\VariantInterface;
use Sylius\Bundle\CoreBundle\Model\InventoryUnitInterface;
use Sylius\Bundle\InventoryBundle\Factory\InventoryUnitFactoryInterface;
use Sylius\Bundle\InventoryBundle\Operator\InventoryOperatorInterface;

/**
 * Order inventory handler.
 *
 * @author Paweł Jędrzejewski <pjedrzejewski@diweb.pl>
 * @author Saša Stamenković <umpirsky@gmail.com>
 */
class InventoryHandler implements InventoryHandlerInterface
{
    /**
     * Inventory operator.
     *
     * @var InventoryOperatorInterface
     */
    protected $inventoryOperator;

    /**
     * Inventory unit factory.
     *
     * @var InventoryUnitFactoryInterface
     */
    protected $inventoryUnitFactory;

    /**
     * Constructor.
     *
     * @param InventoryOperatorInterface    $inventoryOperator
     * @param InventoryUnitFactoryInterface $inventoryUnitFactory
     */
    public function __construct(InventoryOperatorInterface $inventoryOperator, InventoryUnitFactoryInterface $inventoryUnitFactory)
    {
        $this->inventoryOperator = $inventoryOperator;
        $this->inventoryUnitFactory = $inventoryUnitFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function processInventoryUnits(OrderItemInterface $item)
    {
        $nbUnits = $item->getInventoryUnits()->count();

        if ($item->getQuantity() > $nbUnits) {
            $this->createInventoryUnits($item, $item->getQuantity() - $nbUnits);
        } elseif ($item->getQuantity() < $nbUnits) {
            foreach ($item->getInventoryUnits()->slice(0, $nbUnits - $item->getQuantity()) as $unit) {
                $item->removeInventoryUnit($unit);
            }
        }

        if ($nbUnits > 0 && $item->getInventoryUnits()->first()->getStockable() !== $item->getVariant()) {
            foreach ($item->getInventoryUnits() as $unit) {
                $unit->setStockable($item->getVariant());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function holdInventory(OrderInterface $order)
    {
        foreach ($order->getItems() as $item) {
            $units = $order->getInventoryUnitsByVariant($item->getVariant());

            $quantity = $item->getQuantity();
            foreach ($units as $unit) {
                if (InventoryUnitInterface::STATE_CHECKOUT !== $unit->getInventoryState()) {
                    $quantity--;
                } else {
                    $unit->setInventoryState(InventoryUnitInterface::STATE_ONHOLD);
                }
            }

            $this->inventoryOperator->hold($item->getVariant(), $quantity);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function releaseInventory(OrderInterface $order)
    {
        foreach ($order->getItems() as $item) {
            $units = $order->getInventoryUnitsByVariant($item->getVariant());

            $quantity = $item->getQuantity();
            foreach ($units as $unit) {
                if (InventoryUnitInterface::STATE_ONHOLD !== $unit->getInventoryState()) {
                    $quantity--;
                    continue;
                }

                $unit->setInventoryState(InventoryUnitInterface::STATE_CHECKOUT);
            }

            $this->inventoryOperator->release($item->getVariant(), $quantity);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function updateInventory(OrderInterface $order)
    {
        foreach ($order->getItems() as $item) {
            $units = $order->getInventoryUnitsByVariant($item->getVariant());

            $quantity = $item->getQuantity();
            foreach ($units as $unit) {
                if (InventoryUnitInterface::STATE_ONHOLD !== $unit->getInventoryState()) {
                    $quantity--;
                }

                if (in_array($unit->getInventoryState(), array(InventoryUnitInterface::STATE_ONHOLD, InventoryUnitInterface::STATE_CHECKOUT))) {
                    $unit->setInventoryState(InventoryUnitInterface::STATE_SOLD);
                }
            }

            $this->inventoryOperator->decrease($units);
            $this->inventoryOperator->release($item->getVariant(), $quantity);
        }
    }

    protected function createInventoryUnits(OrderItemInterface $item, $quantity, $state = InventoryUnitInterface::STATE_CHECKOUT)
    {
        $units = $this->inventoryUnitFactory->create($item->getVariant(), $quantity, $state);

        foreach ($units as $unit) {
            $item->addInventoryUnit($unit);
        }
    }
}
