<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailLog::query()->latest('id');

        if ($request->filled('email')) {
            $query->where('to_emails', 'like', '%' . $request->email . '%');
        }

        if ($request->filled('subject')) {
            $query->where('subject', 'like', '%' . $request->subject . '%');
        }

        $logs = $query->paginate(25)->withQueryString();

        return view('backend.email-logs.index', compact('logs'));
    }

    public function show(int $id)
    {
        $log = EmailLog::findOrFail($id);

        $decodedPayload = null;
        if (!empty($log->payload)) {
            $parsed = json_decode($log->payload, true);
            $decodedPayload = json_last_error() === JSON_ERROR_NONE ? $parsed : null;
        }

        return view('backend.email-logs.show', compact('log', 'decodedPayload'));
    }
}
