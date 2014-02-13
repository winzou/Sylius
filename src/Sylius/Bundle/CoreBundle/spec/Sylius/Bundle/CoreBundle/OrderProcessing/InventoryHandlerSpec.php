<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\Sylius\Bundle\CoreBundle\OrderProcessing;

use Doctrine\Common\Collections\ArrayCollection;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Sylius\Bundle\CoreBundle\Model\InventoryUnitInterface;
use Sylius\Bundle\CoreBundle\Model\OrderInterface;
use Sylius\Bundle\CoreBundle\Model\OrderItemInterface;
use Sylius\Bundle\CoreBundle\Model\VariantInterface;

/**
 * @author Paweł Jędrzejewski <pjedrzejewski@diweb.pl>
 */
class InventoryHandlerSpec extends ObjectBehavior
{
    /**
     * @param Sylius\Bundle\InventoryBundle\Operator\InventoryOperatorInterface $inventoryOperator
     * @param Sylius\Bundle\InventoryBundle\Factory\InventoryUnitFactory        $inventoryUnitFactory
     */
    function let($inventoryOperator, $inventoryUnitFactory)
    {
        $this->beConstructedWith($inventoryOperator, $inventoryUnitFactory);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Sylius\Bundle\CoreBundle\OrderProcessing\InventoryHandler');
    }

    function it_implements_Sylius_inventory_handler_interface()
    {
        $this->shouldImplement('Sylius\Bundle\CoreBundle\OrderProcessing\InventoryHandlerInterface');
    }

    function it_creates_inventory_units_via_the_factory(
        $inventoryUnitFactory,
        OrderItemInterface $item,
        VariantInterface $variant,
        InventoryUnitInterface $unit1,
        InventoryUnitInterface $unit2
    )
    {
        $item->getVariant()->willReturn($variant);
        $item->getQuantity()->willReturn(2);

        $item->getInventoryUnits()->shouldBeCalled()->willReturn(new ArrayCollection());

        $inventoryUnitFactory->create($variant, 2, InventoryUnitInterface::STATE_CHECKOUT)->shouldBeCalled()->willReturn(array($unit1, $unit2));

        $item->addInventoryUnit($unit1)->shouldBeCalled();
        $item->addInventoryUnit($unit2)->shouldBeCalled();

        $this->processInventoryUnits($item);
    }

    function it_creates_only_missing_inventory_units_via_the_factory(
        $inventoryUnitFactory,
        OrderItemInterface $item,
        VariantInterface $variant,
        InventoryUnitInterface $unit1,
        InventoryUnitInterface $unit2,
        ArrayCollection $units
    )
    {
        $item->getInventoryUnits()->shouldBeCalled()->willReturn(new ArrayCollection(array($unit1)));
        $unit1->getStockable()->willReturn($variant);
        $unit2->getStockable()->willReturn($variant);

        $item->getVariant()->willReturn($variant);
        $item->getQuantity()->willReturn(2);

        $inventoryUnitFactory->create($variant, 1, InventoryUnitInterface::STATE_CHECKOUT)->shouldBeCalled()->willReturn(array($unit2));

        $item->addInventoryUnit($unit1)->shouldNotBeCalled();
        $item->addInventoryUnit($unit2)->shouldBeCalled();

        $this->processInventoryUnits($item);
    }

    /**
     * @param Sylius\Bundle\CoreBundle\Model\OrderInterface $order
     * @param Sylius\Bundle\CoreBundle\Model\OrderItem $item
     * @param Sylius\Bundle\CoreBundle\Model\VariantInterface $variant
     * @param Sylius\Bundle\CoreBundle\Model\InventoryUnitInterface $unit1
     * @param Sylius\Bundle\CoreBundle\Model\InventoryUnitInterface $unit2
     */
    function it_holds_the_variant_stock_via_inventory_operator($inventoryOperator, $order, $item, $variant, $unit1, $unit2)
    {
        $order->getItems()->willReturn(array($item));

        $item->getVariant()->willReturn($variant);
        $item->getQuantity()->willReturn(1);

        $order->getInventoryUnitsByVariant($variant)->shouldBeCalled()->willReturn(array($unit1, $unit2));

        $unit1->getInventoryState()->shouldBeCalled()->willReturn(InventoryUnitInterface::STATE_CHECKOUT);
        $unit2->getInventoryState()->shouldBeCalled()->willReturn(InventoryUnitInterface::STATE_CHECKOUT);
        $unit1->setInventoryState(InventoryUnitInterface::STATE_ONHOLD)->shouldBeCalled();
        $unit2->setInventoryState(InventoryUnitInterface::STATE_ONHOLD)->shouldBeCalled();

        $inventoryOperator->hold($variant, 1)->shouldBeCalled();

        $this->holdInventory($order);
    }

    /**
     * @param Sylius\Bundle\CoreBundle\Model\OrderInterface $order
     * @param Sylius\Bundle\CoreBundle\Model\OrderItem $item
     * @param Sylius\Bundle\CoreBundle\Model\VariantInterface $variant
     * @param Sylius\Bundle\CoreBundle\Model\InventoryUnitInterface $unit1
     * @param Sylius\Bundle\CoreBundle\Model\InventoryUnitInterface $unit2
     */
    function it_releases_the_variant_stock_via_inventory_operator($inventoryOperator, $order, $item, $variant, $unit1, $unit2)
    {
        $order->getItems()->willReturn(array($item));

        $item->getVariant()->willReturn($variant);
        $item->getQuantity()->willReturn(1);

        $order->getInventoryUnitsByVariant($variant)->shouldBeCalled()->willReturn(array($unit1, $unit2));

        $unit1->getInventoryState()->shouldBeCalled()->willReturn(InventoryUnitInterface::STATE_ONHOLD);
        $unit2->getInventoryState()->shouldBeCalled()->willReturn(InventoryUnitInterface::STATE_ONHOLD);
        $unit1->setInventoryState(InventoryUnitInterface::STATE_CHECKOUT)->shouldBeCalled();
        $unit2->setInventoryState(InventoryUnitInterface::STATE_CHECKOUT)->shouldBeCalled();

        $inventoryOperator->release($variant, 1)->shouldBeCalled();

        $this->releaseInventory($order);
    }

    /**
     * @param Sylius\Bundle\CoreBundle\Model\OrderInterface $order
     * @param Sylius\Bundle\CoreBundle\Model\OrderItem $item
     * @param Sylius\Bundle\CoreBundle\Model\VariantInterface $variant
     * @param Sylius\Bundle\CoreBundle\Model\InventoryUnitInterface $unit1
     * @param Sylius\Bundle\CoreBundle\Model\InventoryUnitInterface $unit2
     */
    function it_decreases_the_variant_stock_via_inventory_operator($inventoryOperator, $order, $item, $variant, $unit1, $unit2)
    {
        $order->getItems()->willReturn(array($item));

        $item->getVariant()->willReturn($variant);
        $item->getQuantity()->willReturn(1);

        $order->getInventoryUnitsByVariant($variant)->shouldBeCalled()->willReturn(array($unit1, $unit2));

        $unit1->getInventoryState()->shouldBeCalled()->willReturn(InventoryUnitInterface::STATE_ONHOLD);
        $unit2->getInventoryState()->shouldBeCalled()->willReturn(InventoryUnitInterface::STATE_ONHOLD);
        $unit1->setInventoryState(InventoryUnitInterface::STATE_SOLD)->shouldBeCalled();
        $unit2->setInventoryState(InventoryUnitInterface::STATE_SOLD)->shouldBeCalled();

        $inventoryOperator->decrease(array($unit1, $unit2))->shouldBeCalled();
        $inventoryOperator->release($variant, 1)->shouldBeCalled();

        $this->updateInventory($order);
    }
}
