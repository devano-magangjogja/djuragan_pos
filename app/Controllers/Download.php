<?php

namespace App\Controllers;

use App\Models\InvoiceModel;
use Dompdf\Dompdf;
use Dompdf\Options;

class Download extends BaseController
{
    public function invoice($invoice)
    {
        $invModel = new InvoiceModel();

        $cari = [
            'kolom' => 'faktur',
            'q'     => $invoice,
        ];

        $get_invoice = $invModel->getAll('semua', 0, $cari);

        $filename = time();

        // instantiate and use the dompdf class
        $opsi = new Options();
        $opsi->set('chroot', [FCPATH]);
        $dompdf = new Dompdf($opsi);
        $dompdf->loadHtml(view('template/invoice', [
            'invoice' => $get_invoice['data'][0],
        ]));

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        // $dompdf->stream();

        // Output the generated PDF to Browser
        $dompdf->stream($filename, ['Attachment' => false]);

        exit(0);
    }
}
