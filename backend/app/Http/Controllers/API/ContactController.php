<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactUpdateRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::query()->latest('updated_at');

        if ($request->filled('email')) {
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('email', $operator, '%'.$request->input('email').'%');
        }

        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->input('tag'));
        }

        return ContactResource::collection(
            $query->paginate($request->integer('per_page', 20))
        );
    }

    public function show(Contact $contact)
    {
        $contact->load('projects');
        return new ContactResource($contact);
    }

    public function update(ContactUpdateRequest $request, Contact $contact)
    {
        $contact->fill($request->validated());
        $contact->save();

        return new ContactResource($contact);
    }
}
