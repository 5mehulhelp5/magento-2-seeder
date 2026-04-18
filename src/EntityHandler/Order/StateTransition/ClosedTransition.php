<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Order\StateTransition;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionInterface;

class ClosedTransition implements StateTransitionInterface
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TransactionFactory $transactionFactory,
        private readonly CreditmemoFactory $creditmemoFactory,
        private readonly CreditmemoManagementInterface $creditmemoManagement,
    ) {
    }

    public function getState(): string
    {
        return 'closed';
    }

    public function apply(OrderInterface $order, array $data): void
    {
        if (!method_exists($order, 'canInvoice') || !$order->canInvoice()) {
            return;
        }

        $invoice = $this->invoiceService->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();

        $transaction = $this->transactionFactory->create();
        $transaction->addObject($invoice)->addObject($order)->save();

        $creditmemo = $this->creditmemoFactory->createByOrder($order);
        $this->creditmemoManagement->refund($creditmemo, true);
    }
}
