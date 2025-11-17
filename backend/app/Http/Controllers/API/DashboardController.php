<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardSummaryResource;
use App\Http\Resources\EmailResource;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function summary()
    {
        $projectsByStatus = DB::table('projects')
            ->join('deal_statuses', 'projects.deal_status_id', '=', 'deal_statuses.id')
            ->select('deal_statuses.name as status', DB::raw('count(*) as total'))
            ->groupBy('deal_statuses.name')
            ->get()
            ->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->total]);

        $topContacts = Contact::withCount('projects')
            ->orderByDesc('projects_count')
            ->take(5)
            ->get()
            ->map(fn (Contact $contact) => [
                'contact_id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'email_count' => $contact->projects_count,
            ]);

        $latestEmails = Email::query()
            ->with(['participants', 'attachments'])
            ->latest('received_at')
            ->take(10)
            ->get();

        return new DashboardSummaryResource([
            'total_projects' => Project::count(),
            'projects_by_status' => $projectsByStatus,
            'top_contacts' => $topContacts,
            'latest_emails' => $latestEmails,
        ]);
    }
}
