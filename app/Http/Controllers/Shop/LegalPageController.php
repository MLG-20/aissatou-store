<?php

namespace App\Http\Controllers\Shop;

use App\Enums\Content\LegalDocument;
use App\Http\Controllers\Controller;
use App\Models\Content\LegalPage;
use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function __invoke(LegalDocument $document): View
    {
        $page = LegalPage::for($document);

        // Brouillon : 404, comme un article du Journal non publié.
        abort_unless($page?->is_published, 404);

        return view('shop.legal', ['page' => $page]);
    }
}
