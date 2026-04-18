<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\Test\Unit\EntityHandler\Order\StateTransition;

use Magento\Framework\DB\Transaction;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use PHPUnit\Framework\TestCase;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransition\ClosedTransition;

final class ClosedTransitionTest extends TestCase
{
    public function test_get_state_returns_closed(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $creditmemoFactory = $this->createMock(CreditmemoFactory::class);
        $creditmemoManagement = $this->createMock(CreditmemoManagementInterface::class);

        $transition = new ClosedTransition(
            $invoiceService,
            $transactionFactory,
            $creditmemoFactory,
            $creditmemoManagement
        );

        $this->assertSame('closed', $transition->getState());
    }

    public function test_apply_invoices_then_creates_creditmemo_and_refunds_offline(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $creditmemoFactory = $this->createMock(CreditmemoFactory::class);
        $creditmemoManagement = $this->createMock(CreditmemoManagementInterface::class);
        $order = $this->createMock(Order::class);
        $invoice = $this->createMock(Invoice::class);
        $transaction = $this->createMock(Transaction::class);
        $creditmemo = $this->createMock(Creditmemo::class);

        $order->expects($this->once())->method('canInvoice')->willReturn(true);

        $invoiceService->expects($this->once())
            ->method('prepareInvoice')
            ->with($order)
            ->willReturn($invoice);

        $invoice->expects($this->once())
            ->method('setRequestedCaptureCase')
            ->with('offline')
            ->willReturnSelf();
        $invoice->expects($this->once())->method('register')->willReturnSelf();

        $transactionFactory->expects($this->once())->method('create')->willReturn($transaction);

        $transaction->expects($this->exactly(2))
            ->method('addObject')
            ->willReturnSelf();
        $transaction->expects($this->once())->method('save')->willReturnSelf();

        $creditmemoFactory->expects($this->once())
            ->method('createByOrder')
            ->with($order)
            ->willReturn($creditmemo);

        $creditmemoManagement->expects($this->once())
            ->method('refund')
            ->with($creditmemo, true)
            ->willReturn($creditmemo);

        $transition = new ClosedTransition(
            $invoiceService,
            $transactionFactory,
            $creditmemoFactory,
            $creditmemoManagement
        );
        $transition->apply($order, []);
    }

    public function test_apply_skips_when_order_cannot_be_invoiced(): void
    {
        $invoiceService = $this->createMock(InvoiceService::class);
        $transactionFactory = $this->createMock(TransactionFactory::class);
        $creditmemoFactory = $this->createMock(CreditmemoFactory::class);
        $creditmemoManagement = $this->createMock(CreditmemoManagementInterface::class);
        $order = $this->createMock(Order::class);

        $order->expects($this->once())->method('canInvoice')->willReturn(false);

        $invoiceService->expects($this->never())->method('prepareInvoice');
        $transactionFactory->expects($this->never())->method('create');
        $creditmemoFactory->expects($this->never())->method('createByOrder');
        $creditmemoManagement->expects($this->never())->method('refund');

        $transition = new ClosedTransition(
            $invoiceService,
            $transactionFactory,
            $creditmemoFactory,
            $creditmemoManagement
        );
        $transition->apply($order, []);
    }
}
