<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\Marketing\ProductFeed;
use Illuminate\Http\Response;

/** Flux catalogue lu par Meta Commerce Manager et Google Merchant Center. */
class ProductFeedController extends Controller
{
    public function __invoke(ProductFeed $feed): Response
    {
        return response($feed->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
