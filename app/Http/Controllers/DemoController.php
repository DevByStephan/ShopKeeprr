<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DemoController extends Controller
{
    public function __invoke(): View
    {
        return view('demo.index');
    }
}