<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return nexus_view('page', compact('page'));
    }
}
