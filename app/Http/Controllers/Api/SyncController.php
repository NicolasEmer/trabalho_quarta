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
        // -------------------------------
        // 1) Validar payload básico
        // -------------------------------
        $payload = $request->validate([
            'users' => 'array',
            'users.*.cpf' => 'required|string',
            'users.*.updated_at' => 'nullable|string',

            'events' => 'array',
            'events.*.id' => 'required|integer',
            'events.*.updated_at' => 'nullable|string',

            'event_registrations' => 'array',
            'event_registrations.*.id' => 'required|integer',
            'event_registrations.*.updated_at' => 'nullable|string',

            'certificates' => 'array',
            'certificates.*.id' => 'required|integer',
            'certificates.*.updated_at' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            // -------------------------------
            // 2) Aplicar dados recebidos AO banco da VM
            //    (ganha quem tiver updated_at mais recente)
            // -------------------------------
            $this->syncUsers($payload['users'] ?? []);
            $this->syncEvents($payload['events'] ?? []);
            $this->syncEventRegistrations($payload['event_registrations'] ?? []);
            $this->syncCertificates($payload['certificates'] ?? []);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Sync failed on server side',
                'error'   => $e->getMessage(),
            ], 500);
        }

        // -------------------------------
        // 3) Devolver ESTADO COMPLETO do banco da VM
        // -------------------------------
        return response()->json([
            'users' => User::withTrashed()->get()->toArray(),
            'events' => Event::withTrashed()->get()->toArray(),
            'event_registrations' => EventRegistration::withTrashed()->get()->toArray(),
            'certificates' => DB::table('certificates')->get()->toArray(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    // ----------------------------------------------------------------
    // Funções de merge (lado servidor / VM)
    // ----------------------------------------------------------------

    private function parseIncomingUpdatedAt($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function syncUsers(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['cpf'])) {
                continue;
            }

            $incomingUpdatedAt = $this->parseIncomingUpdatedAt($data['updated_at'] ?? null);

            $user = User::withTrashed()
                ->where('cpf', $data['cpf'])
                ->first();

            // Campos permitidos vindos do outro lado
            $allowed = Arr::only($data, [
                'name',
                'email',
                'cpf',
                'password',
                'completed',
                'phone',
                'deleted_at',
                'created_at',
                'updated_at',
            ]);

            // Se não existe, cria direto
            if (!$user) {
                $user = new User();
                $user->forceFill($allowed);
                $user->save();
                continue;
            }

            // Se não tem updated_at vindo, ignora (não sobrescreve)
            if (!$incomingUpdatedAt) {
                continue;
            }

            // Decide se deve sobrescrever pelo updated_at
            $currentUpdatedAt = $user->updated_at ?? $user->created_at;
            if ($incomingUpdatedAt->gt($currentUpdatedAt)) {
                $user->forceFill($allowed);
                $user->save();
            }
        }
    }

    private function syncEvents(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) {
                continue;
            }

            $incomingUpdatedAt = $this->parseIncomingUpdatedAt($data['updated_at'] ?? null);

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
                'created_at',
                'updated_at',
            ]);

            if (!$event) {
                $event = new Event();
                $event->forceFill($allowed);
                $event->save();
                continue;
            }

            if (!$incomingUpdatedAt) {
                continue;
            }

            $currentUpdatedAt = $event->updated_at ?? $event->created_at;
            if ($incomingUpdatedAt->gt($currentUpdatedAt)) {
                $event->forceFill($allowed);
                $event->save();
            }
        }
    }

    private function syncEventRegistrations(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) {
                continue;
            }

            $incomingUpdatedAt = $this->parseIncomingUpdatedAt($data['updated_at'] ?? null);

            $reg = EventRegistration::withTrashed()->find($data['id']);

            $allowed = Arr::only($data, [
                'id',
                'event_id',
                'user_id',
                'status',
                'presence_at',
                'deleted_at',
                'created_at',
                'updated_at',
            ]);

            if (!$reg) {
                $reg = new EventRegistration();
                $reg->forceFill($allowed);
                $reg->save();
                continue;
            }

            if (!$incomingUpdatedAt) {
                continue;
            }

            $currentUpdatedAt = $reg->updated_at ?? $reg->created_at;
            if ($incomingUpdatedAt->gt($currentUpdatedAt)) {
                $reg->forceFill($allowed);
                $reg->save();
            }
        }
    }

    private function syncCertificates(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) {
                continue;
            }

            $incomingUpdatedAt = $this->parseIncomingUpdatedAt($data['updated_at'] ?? null);

            $existing = DB::table('certificates')
                ->where('id', $data['id'])
                ->first();

            $row = [
                'id'         => $data['id'],
                'user_id'    => $data['user_id'] ?? null,
                'event_id'   => $data['event_id'] ?? null,
                'cpf'        => $data['cpf'] ?? null,
                'code'       => $data['code'] ?? null,
                'issued_at'  => $data['issued_at'] ?? null,
                'pdf_path'   => $data['pdf_path'] ?? null,
                'pdf_url'    => $data['pdf_url'] ?? null,
                'deleted_at' => $data['deleted_at'] ?? null,
                'created_at' => $data['created_at'] ?? null,
                'updated_at' => $data['updated_at'] ?? null,
            ];

            // Não existe ainda? insere
            if (!$existing) {
                DB::table('certificates')->insert($row);
                continue;
            }

            if (!$incomingUpdatedAt) {
                continue;
            }

            $currentUpdatedAt = $existing->updated_at
                ? Carbon::parse($existing->updated_at)
                : null;

            if (!$currentUpdatedAt || $incomingUpdatedAt->gt($currentUpdatedAt)) {
                DB::table('certificates')
                    ->where('id', $data['id'])
                    ->update($row);
            }
        }
    }
}
