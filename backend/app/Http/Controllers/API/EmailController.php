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
        $query = Email::query()->with(['participants', 'attachments'])
            ->latest('received_at');

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

        $emails = $query->paginate($request->integer('per_page', 20));

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
