<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmailAccountStoreRequest;
use App\Http\Requests\EmailAccountUpdateRequest;
use App\Http\Resources\EmailAccountResource;
use App\Models\EmailAccount;
use App\Services\EmailConnectivityService;
use App\Services\MailboxSyncService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EmailAccountController extends Controller
{
    public function __construct(private readonly EmailConnectivityService $connectivityService) {}

    public function index(Request $request)
    {
        Gate::authorize('manage-email-accounts');

        $accounts = EmailAccount::query()
            ->latest('created_at')
            ->paginate($request->integer('per_page', 20));

        return EmailAccountResource::collection($accounts);
    }

    public function store(EmailAccountStoreRequest $request)
    {
        Gate::authorize('manage-email-accounts');

        $payload = $request->validated();

        $account = EmailAccount::create([
            'email' => $payload['email'],
            'display_name' => $payload['display_name'] ?? null,
            'imap_host' => $payload['imap_host'],
            'imap_port' => $payload['imap_port'],
            'smtp_host' => $payload['smtp_host'],
            'smtp_port' => $payload['smtp_port'],
            'security_type' => $payload['security_type'],
            'auth_type' => $payload['auth_type'],
            'encrypted_credentials' => [
                'username' => Crypt::encryptString($payload['credentials']['username']),
                'password' => Crypt::encryptString($payload['credentials']['password']),
            ],
            'sync_interval_minutes' => $payload['sync_interval_minutes'] ?? 15,
            'created_by' => $request->user()->id,
        ]);

        $testResult = $this->connectivityService->test($account, $payload['credentials']);

        return (new EmailAccountResource($account))
            ->additional(['test' => $testResult])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(EmailAccountUpdateRequest $request, EmailAccount $emailAccount)
    {
        Gate::authorize('manage-email-accounts');

        $payload = $request->validated();

        if (isset($payload['credentials'])) {
            $payload['encrypted_credentials'] = [
                'username' => isset($payload['credentials']['username'])
                    ? Crypt::encryptString($payload['credentials']['username'])
                    : data_get($emailAccount->encrypted_credentials, 'username'),
                'password' => isset($payload['credentials']['password'])
                    ? Crypt::encryptString($payload['credentials']['password'])
                    : data_get($emailAccount->encrypted_credentials, 'password'),
            ];
            unset($payload['credentials']);
        }

        $emailAccount->fill($payload);
        $emailAccount->updated_by = $request->user()->id;
        $emailAccount->save();

        return new EmailAccountResource($emailAccount);
    }

    public function test(EmailAccount $emailAccount)
    {
        Gate::authorize('manage-email-accounts');

        try {
            $credentials = [
                'username' => Crypt::decryptString($emailAccount->encrypted_credentials['username']),
                'password' => Crypt::decryptString($emailAccount->encrypted_credentials['password']),
            ];
        } catch (DecryptException $exception) {
            report($exception);

            return response()->json([
                'status' => 'failed',
                'message' => 'Stored credentials could not be decrypted. Please update the credentials and try again.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->connectivityService->test($emailAccount, $credentials);

        return response()->json($result);
    }

    public function sync(EmailAccount $emailAccount, MailboxSyncService $syncService)
    {
        Gate::authorize('manage-email-accounts');

        try {
            $processed = $syncService->sync($emailAccount);

            return response()->json([
                'status' => 'passed',
                'message' => "Synced {$processed} new message(s).",
                'processed' => $processed,
                'synced_at' => now()->toIso8601String(),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
