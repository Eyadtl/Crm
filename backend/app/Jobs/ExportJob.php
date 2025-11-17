<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Services\ExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $exportId)
    {
        $this->onQueue('exports');
    }

    public function handle(ExportService $service): void
    {
        $export = DataExport::findOrFail($this->exportId);
        $service->buildCsv($export);
    }
}
