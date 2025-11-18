<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Models\EventRegistration;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function fullSync(Request $request)
    {
        $payload = $request->validate([
            'users' => 'array',
            'users.*.cpf' => 'required|string',
            'users.*.updated_at' => 'required|string',

            'events' => 'array',
            'events.*.id' => 'required|integer',
            'events.*.updated_at' => 'required|string',

            'event_registrations' => 'array',
            'event_registrations.*.id' => 'required|integer',
            'event_registrations.*.updated_at' => 'required|string',


            'certificates' => 'array',
            'certificates.*.id' => 'required|integer',
            'certificates.*.updated_at' => 'required|string',
        ]);

        DB::beginTransaction();

        try {
            $this->syncUsers($payload['users'] ?? []);
            $this->syncEvents($payload['events'] ?? []);
            $this->syncEventRegistrations($payload['event_registrations'] ?? []);
            $this->syncCertificates($payload['certificates'] ?? []); // agora via DB::table

            DB::commit();


            return response()->json([
                'users' => User::withTrashed()->get(),
                'events' => Event::withTrashed()->get(),
                'event_registrations' => EventRegistration::withTrashed()->get(),

                'certificates' => DB::table('certificates')->get(),
                'server_time' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Sync failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function syncUsers(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['cpf'])) {
                continue;
            }

            $incomingUpdatedAt = Carbon::parse($data['updated_at']);

            $user = User::withTrashed()
                ->where('cpf', $data['cpf'])
                ->first();

            $allowed = Arr::only($data, [
                'name',
                'email',
                'cpf',
                'password',
                'completed',
                'phone',
                'deleted_at',
            ]);

            if (!$user) {
                $user = new User();
                $user->forceFill($allowed);
                $user->save();
                continue;
            }

            if ($incomingUpdatedAt->gt($user->updated_at ?? $user->created_at)) {
                $user->forceFill($allowed);
                $user->save();
            }
        }
    }

    private function syncEvents(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) continue;

            $incomingUpdatedAt = Carbon::parse($data['updated_at']);

            $event = Event::withTrashed()->find($data['id']);

            $allowed = Arr::only($data, [
                'id',
                'title',
                'description',
                'location',
                'start_at',
                'end_at',
                'is_all_day',
                'is_public',
                'capacity',
                'deleted_at',
            ]);

            if (!$event) {
                $event = new Event();
                $event->forceFill($allowed);
                $event->save();
                continue;
            }

            if ($incomingUpdatedAt->gt($event->updated_at ?? $event->created_at)) {
                $event->forceFill($allowed);
                $event->save();
            }
        }
    }

    private function syncEventRegistrations(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) continue;

            $incomingUpdatedAt = Carbon::parse($data['updated_at']);

            $reg = EventRegistration::withTrashed()->find($data['id']);

            $allowed = Arr::only($data, [
                'id',
                'event_id',
                'user_id',
                'status',
                'presence_at',
                'deleted_at',
            ]);

            if (!$reg) {
                $reg = new EventRegistration();
                $reg->forceFill($allowed);
                $reg->save();
                continue;
            }

            if ($incomingUpdatedAt->gt($reg->updated_at ?? $reg->created_at)) {
                $reg->forceFill($allowed);
                $reg->save();
            }
        }
    }

    private function syncCertificates(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) continue;

            $incomingUpdatedAt = Carbon::parse($data['updated_at']);

            $existing = DB::table('certificates')->where('id', $data['id'])->first();

            // Ajuste ESTA lista conforme a tua migration real de certificates
            $allowed = Arr::only($data, [
                'id',
                'user_id',
                'event_id',
                'cpf',
                'code',
                'issued_at',
                'pdf_path',
                'pdf_url',
                'deleted_at',
                'created_at',
                'updated_at',
            ]);

            if (!$existing) {
                DB::table('certificates')->insert($allowed);
                continue;
            }

            $currentUpdatedAt = $existing->updated_at ? Carbon::parse($existing->updated_at) : null;

            if (!$currentUpdatedAt || $incomingUpdatedAt->gt($currentUpdatedAt)) {
                DB::table('certificates')
                    ->where('id', $data['id'])
                    ->update($allowed);
            }
        }
    }
}
