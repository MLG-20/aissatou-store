<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Admin\AdminPermission;
use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Facture PDF d'une commande (phase 3). Route sous guard `admin` : jamais
 * accessible à un client, même en devinant l'URL.
 */
class OrderInvoiceController extends Controller
{
    public function __invoke(Request $request, Order $order): BinaryFileResponse
    {
        // Route hors Filament : la permission est revérifiée ici (Phase 6A),
        // pas seulement par la présence du bouton sur la fiche commande.
        abort_unless($request->user('admin')?->hasAdminPermission(AdminPermission::Orders), 403);

        $order->load(['items', 'payment', 'user']);

        $directory = storage_path('app/invoices');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory.'/facture-'.$order->number.'.pdf';

        Pdf::loadView('pdfs.invoice', ['order' => $order])
            ->setPaper('a4')
            ->save($path);

        return response()->download($path, 'facture-'.$order->number.'.pdf')
            ->deleteFileAfterSend();
    }
}
