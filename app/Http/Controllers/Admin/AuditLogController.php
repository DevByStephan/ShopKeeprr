<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = AuditLog::query()->with('user')->latest('id');

        if ($request->filled('q')) {
            $query->where('action', 'like', '%'.$request->string('q')->toString().'%');
        }

        if ($request->filled('user')) {
            $query->where('user_id', $request->integer('user'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }
}
