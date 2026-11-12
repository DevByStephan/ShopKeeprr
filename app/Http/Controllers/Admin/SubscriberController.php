<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriberController extends Controller
{
    public function index(Request $request): View
    {
        $query = NewsletterSubscriber::query()->latest('subscribed_at');

        if ($request->filled('q')) {
            $query->where('email', 'like', '%'.$request->string('q')->toString().'%');
        }

        return view('admin.subscribers.index', [
            'subscribers' => $query->paginate(30)->withQueryString(),
            'search' => $request->string('q')->toString(),
        ]);
    }
}
