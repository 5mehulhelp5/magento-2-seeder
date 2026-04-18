<?php

declare(strict_types=1);

namespace RunAsRoot\Seeder\EntityHandler\Order\StateTransition;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use RunAsRoot\Seeder\EntityHandler\Order\StateTransitionInterface;

class ProcessingTransition implements StateTransitionInterface
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TransactionFactory $transactionFactory,
    ) {
    }

    public function getState(): string
    {
        return 'processing';
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
    }
}
