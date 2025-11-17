<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\DataExport;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;

class ExportService
{
    public function queue(DataExport $export): void
    {
        // Jobs dispatch ExportJob which calls buildCsv.
    }

    public function buildCsv(DataExport $export): void
    {
        $filename = "exports/{$export->id}.csv";
        $filters = $export->filters ?? [];

        $handle = fopen('php://temp', 'w+');
        if ($export->type === 'projects') {
            fputcsv($handle, ['Deal Name', 'Status', 'Owner']);
            Project::query()->with('status')->each(function (Project $project) use ($handle) {
                fputcsv($handle, [$project->deal_name, optional($project->status)->name, $project->deal_owner_id]);
            });
        } else {
            fputcsv($handle, ['Name', 'Email', 'Phone']);
            Contact::query()->each(function (Contact $contact) use ($handle) {
                fputcsv($handle, [$contact->name, $contact->email, $contact->phone]);
            });
        }

        rewind($handle);
        Storage::disk(config('filesystems.default'))->put($filename, stream_get_contents($handle));
        fclose($handle);

        $export->forceFill([
            'status' => 'ready',
            'storage_ref' => $filename,
            'download_url' => $filename,
            'expires_at' => now()->addDay(),
        ])->save();
    }
}
