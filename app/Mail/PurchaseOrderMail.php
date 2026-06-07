<?php

declare(strict_types=1);

namespace App\Mail;

use Domain\Order\Data\OrderData;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public OrderData $order,
        public string $pdf,
        public ?string $supplierName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Purchase Order from CellarOS');
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.purchase-order',
            with: ['order' => $this->order, 'supplierName' => $this->supplierName],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, 'purchase-order.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
