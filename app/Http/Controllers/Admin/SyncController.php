<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseSyncService;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function run(Request $request, DatabaseSyncService $service)
    {
        $stats = $service->run();

        return back()->with('status', 'Sincronização concluída com sucesso! ' . json_encode($stats));
    }
}
