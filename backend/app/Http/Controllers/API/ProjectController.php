<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LinkProjectEmailRequest;
use App\Http\Requests\ProjectFromEmailRequest;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Email;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query()->with(['status'])
            ->latest('updated_at');

        if ($request->filled('status_id')) {
            $query->where('deal_status_id', $request->input('status_id'));
        }

        if ($request->filled('owner_id')) {
            $query->where('deal_owner_id', $request->input('owner_id'));
        }

        if ($request->filled('updated_from')) {
            $query->where('updated_at', '>=', $request->input('updated_from'));
        }

        $projects = $query->paginate($request->integer('per_page', 20));

        return ProjectResource::collection($projects);
    }

    public function store(ProjectStoreRequest $request)
    {
        Gate::authorize('manage-projects');

        $project = Project::create(array_merge(
            $request->validated(),
            [
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]
        ));

        return (new ProjectResource($project->load('status')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Project $project)
    {
        $project->load(['status', 'emails', 'contacts']);
        return new ProjectResource($project);
    }

    public function update(ProjectUpdateRequest $request, Project $project)
    {
        Gate::authorize('manage-projects');

        $project->fill($request->validated());
        $project->updated_by = $request->user()->id;
        $project->save();

        return new ProjectResource($project->load('status'));
    }

    public function createFromEmail(ProjectFromEmailRequest $request, Email $email)
    {
        Gate::authorize('manage-projects');

        $data = $request->validated();
        $projectData = [
            'deal_name' => $data['deal_name'],
            'deal_status_id' => $data['deal_status_id'],
            'deal_owner_id' => $data['owner_id'] ?? null,
            'estimated_value' => $data['estimated_value'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ];

        $project = Project::create($projectData);

        $project->emails()->attach($email->id, [
            'linked_by' => $request->user()->id,
            'linked_at' => now(),
        ]);

        return (new ProjectResource($project->load('status', 'emails')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function linkEmail(LinkProjectEmailRequest $request, Project $project)
    {
        Gate::authorize('manage-projects');

        $project->emails()->syncWithoutDetaching([
            $request->validated('email_id') => [
                'linked_by' => $request->user()->id,
                'linked_at' => now(),
            ],
        ]);

        return response()->json(['message' => 'Email linked'], Response::HTTP_CREATED);
    }
}
