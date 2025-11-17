<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportRequest;
use App\Http\Resources\DataExportResource;
use App\Jobs\ExportJob;
use App\Models\DataExport;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function queueProjects(ExportRequest $request)
    {
        return $this->queueExport('projects', $request);
    }

    public function queueContacts(ExportRequest $request)
    {
        return $this->queueExport('contacts', $request);
    }

    public function show(string $export)
    {
        $export = DataExport::findOrFail($export);

        return new DataExportResource($export);
    }

    protected function queueExport(string $type, ExportRequest $request)
    {
        $export = DataExport::create([
            'type' => $type,
            'filters' => $request->validated('filters') ?? [],
            'requested_by' => $request->user()->id,
            'status' => 'queued',
        ]);

        ExportJob::dispatch($export->id);

        return (new DataExportResource($export))
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
