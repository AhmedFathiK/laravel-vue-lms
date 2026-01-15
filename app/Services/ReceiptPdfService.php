<?php

namespace App\Services;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPDF;

class ReceiptPdfService
{
    /**
     * Generate a PDF for the given receipt.
     *
     * @param Receipt $receipt
     * @return DomPDF
     */
    public function generate(Receipt $receipt): DomPDF
    {
        $data = [
            'receipt' => $receipt,
            'user' => $receipt->user,
            'payment' => $receipt->payment,
        ];

        return Pdf::loadView('pdf.receipt', $data);
    }
}
