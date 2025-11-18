<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Services\SystemHealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response;

class SystemController extends Controller
{
    public function __construct(private readonly SystemHealthService $healthService) {}

    public function health()
    {
        return response()->json($this->healthService->getSummary());
    }

    public function syncLogs(Request $request)
    {
        $query = SyncLog::query()->latest('created_at');

        if ($request->filled('email_account_id')) {
            $query->where('email_account_id', $request->input('email_account_id'));
        }

        return response()->json($query->limit(200)->get());
    }

    public function cronRun()
    {
        Artisan::call('schedule:run');

        return response()->json(['status' => 'schedule triggered'], Response::HTTP_ACCEPTED);
    }
}
