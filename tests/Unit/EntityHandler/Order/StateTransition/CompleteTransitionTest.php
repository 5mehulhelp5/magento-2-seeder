<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Order\StateTransition;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\ShipmentFactory;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransition\CompleteTransition;

final class CompleteTransitionTest extends TestCase
{
    public function test_get_state_returns_complete(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $shipmentFactory = $this->createMock(ShipmentFactory::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);

        $transition = new CompleteTransition($invoiceService, $shipmentFactory, $transactionFactory);

        $this->assertSame('complete', $transition->getState());
    }

    public function test_apply_invoices_then_ships_then_commits_transaction(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $shipmentFactory = $this->createMock(ShipmentFactory::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $order = $this->createMock(Order::class);
        $invoice = $this->createMock(Invoice::class);
        $shipment = $this->createMock(Shipment::class);
        $transaction = $this->createMock(Transaction::class);

        $item1 = $this->createMock(Item::class);
        $item1->method('getItemId')->willReturn(101);
        $item1->method('getQtyOrdered')->willReturn(2);

        $item2 = $this->createMock(Item::class);
        $item2->method('getItemId')->willReturn(102);
        $item2->method('getQtyOrdered')->willReturn(1);

        $order->expects($this->once())->method('canInvoice')->willReturn(true);
        $order->expects($this->once())->method('getAllItems')->willReturn([$item1, $item2]);

        $invoiceService->expects($this->once())
            ->method('prepareInvoice')
            ->with($order)
            ->willReturn($invoice);

        $invoice->expects($this->once())
            ->method('setRequestedCaptureCase')
            ->with('offline')
            ->willReturnSelf();
        $invoice->expects($this->once())->method('register')->willReturnSelf();

        $shipmentFactory->expects($this->once())
            ->method('create')
            ->with($order, [101 => 2.0, 102 => 1.0])
            ->willReturn($shipment);

        $shipment->expects($this->once())->method('register')->willReturnSelf();

        $transactionFactory->expects($this->once())->method('create')->willReturn($transaction);

        $transaction->expects($this->exactly(3))
            ->method('addObject')
            ->willReturnSelf();
        $transaction->expects($this->once())->method('save')->willReturnSelf();

        $transition = new CompleteTransition($invoiceService, $shipmentFactory, $transactionFactory);
        $transition->apply($order, []);
    }

    public function test_apply_skips_when_order_cannot_be_invoiced(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $shipmentFactory = $this->createMock(ShipmentFactory::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $order = $this->createMock(Order::class);

        $order->expects($this->once())->method('canInvoice')->willReturn(false);

        $invoiceService->expects($this->never())->method('prepareInvoice');
        $shipmentFactory->expects($this->never())->method('create');
        $transactionFactory->expects($this->never())->method('create');

        $transition = new CompleteTransition($invoiceService, $shipmentFactory, $transactionFactory);
        $transition->apply($order, []);
    }
}
