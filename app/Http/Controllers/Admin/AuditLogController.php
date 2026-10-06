<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request)
    {
        $table = $request->query('table');
        $logs = AuditLog::with('user')
            ->when($table, fn ($q) => $q->where('table_name', $table))
            ->latest('id')->paginate(50)->withQueryString();

        return view('admin.audit', [
            'logs' => $logs,
            'table' => $table,
            'tables' => AuditLog::distinct()->orderBy('table_name')->pluck('table_name'),
        ]);
    }
}
