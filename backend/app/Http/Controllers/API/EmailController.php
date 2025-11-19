<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ComposeEmailRequest;
use App\Http\Resources\EmailResource;
use App\Jobs\FetchBodyJob;
use App\Jobs\SendEmailJob;
use App\Models\Email;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EmailController extends Controller
{
    public function index(Request $request)
    {
        $query = Email::query()->with(['participants', 'attachments', 'account'])
            ->orderBy('received_at', 'desc')
            ->orderBy('created_at', 'desc');

        if ($request->filled('account_id')) {
            $query->where('email_account_id', $request->input('account_id'));
        }

        if ($request->filled('project_id')) {
            $query->whereHas('projects', fn (Builder $builder) => $builder->where('projects.id', $request->input('project_id')));
        }

        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->betweenDates($request->input('date_from'), $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $builder) use ($search) {
                $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
                $builder->where('subject', $operator, $search)
                    ->orWhere('snippet', $operator, $search);
            });
        }

        // Debug logging
        \Log::info('=== EMAIL QUERY DEBUG ===');
        \Log::info('Request params:', [
            'account_id' => $request->input('account_id'),
            'search' => $request->input('search'),
            'page' => $request->input('page', 1),
            'per_page' => $request->integer('per_page', 20)
        ]);
        \Log::info('SQL Query: ' . $query->toSql());
        \Log::info('Bindings: ' . json_encode($query->getBindings()));

        $emails = $query->paginate($request->integer('per_page', 20));

        // Log first few emails with their received_at values
        \Log::info("Total emails found: {$emails->total()}");
        \Log::info('First 10 emails retrieved (showing received_at):');
        foreach ($emails->take(10) as $idx => $email) {
            \Log::info(($idx + 1) . ". Subject: \"{$email->subject}\", received_at: {$email->received_at}, account: {$email->account->email}");
        }

        // Verify the actual order by checking if sorted correctly
        $firstEmail = $emails->first();
        $secondEmail = $emails->skip(1)->first();
        if ($firstEmail && $secondEmail) {
            $isCorrectOrder = $firstEmail->received_at >= $secondEmail->received_at;
            \Log::info("Sort order correct (newest first): " . ($isCorrectOrder ? 'YES' : 'NO'));
        }

        return EmailResource::collection($emails);
    }

    public function show(Email $email)
    {
        $email->load(['participants', 'attachments', 'projects']);

        return new EmailResource($email);
    }

    public function fetchBody(Email $email)
    {
        FetchBodyJob::dispatch($email->id);

        return response()->json([
            'job_id' => $email->id,
            'status' => 'queued',
        ], Response::HTTP_ACCEPTED);
    }

    public function reply(ComposeEmailRequest $request, Email $email)
    {
        $payload = $request->validated();
        $payload['parent_email_id'] = $email->id;
        $payload['user_id'] = $request->user()->id;
        SendEmailJob::dispatch($payload);

        return response()->json(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }

    public function forward(ComposeEmailRequest $request, Email $email)
    {
        $payload = $request->validated();
        $payload['parent_email_id'] = $email->id;
        $payload['user_id'] = $request->user()->id;
        SendEmailJob::dispatch($payload);

        return response()->json(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
