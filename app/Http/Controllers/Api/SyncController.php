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
use Illuminate\Support\Facades\Schema;


class SyncController extends Controller
{
    public function fullSync(Request $request)
    {
        // Log do que chegou na VM
        \Log::info('SYNC ← VM RECEBEU PAYLOAD', [
            'users_count'               => count($request->input('users', [])),
            'events_count'              => count($request->input('events', [])),
            'event_registrations_count' => count($request->input('event_registrations', [])),
            'certificates_count'        => count($request->input('certificates', [])),
        ]);

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
            'certificates.*.user_id' => 'nullable|integer',
            'certificates.*.user_name' => 'nullable|string',
            'certificates.*.user_cpf' => 'nullable|string',
            'certificates.*.event_id' => 'nullable|integer',
            'certificates.*.event_title' => 'nullable|string',
            'certificates.*.event_start_at' => 'nullable|string',
            'certificates.*.code' => 'nullable|string',
            'certificates.*.issued_at' => 'nullable|string',
            'certificates.*.pdf_url' => 'nullable|string',
            'certificates.*.pdf_path' => 'nullable|string',
            'certificates.*.metadata' => 'nullable',
            'certificates.*.deleted_at' => 'nullable|string',
            'certificates.*.created_at' => 'nullable|string',
            'certificates.*.updated_at' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $this->syncUsers($payload['users'] ?? []);
            $this->syncEvents($payload['events'] ?? []);
            $this->syncEventRegistrations($payload['event_registrations'] ?? []);
            $this->syncCertificates($payload['certificates'] ?? []);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('SYNC ← ERRO NO SERVIDOR VM', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Sync failed on server side',
                'error'   => $e->getMessage(),
            ], 500);
        }

        // Devolver estado completo da VM
        $result = [
            'users' => DB::table('users')->get()->map(fn ($r) => (array) $r)->toArray(),
            'events' => DB::table('events')->get()->map(fn ($r) => (array) $r)->toArray(),
            'event_registrations' => DB::table('event_registrations')->get()->map(fn ($r) => (array) $r)->toArray(),
            'certificates' => DB::table('certificates')->get()->map(fn ($r) => (array) $r)->toArray(),
            'server_time' => now()->toIso8601String(),
        ];

        \Log::info('SYNC ← VM RESPONDENDO COM ESTADO COMPLETO', [
            'users_count'               => count($result['users']),
            'events_count'              => count($result['events']),
            'event_registrations_count' => count($result['event_registrations']),
            'certificates_count'        => count($result['certificates']),
        ]);

        return response()->json($result);
    }

    // ----------------------------------------------------------------
    // Funções helpers
    // ----------------------------------------------------------------

    private function parseIncomingUpdatedAt($value): ?Carbon
    {
        if (empty($value)) return null;

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---------------- USERS ----------------

    private function syncUsers(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['cpf'])) continue;

            $incomingUpdatedAt = $this->parseIncomingUpdatedAt($data['updated_at'] ?? null);

            $user = User::withTrashed()
                ->where('cpf', $data['cpf'])
                ->first();

            $allowed = Arr::only($data, [
                'id',
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

            if (!$user) {
                $user = new User();
                $user->forceFill($allowed);
                $user->save();
                continue;
            }

            if (!$incomingUpdatedAt) continue;

            $currentUpdatedAt = $user->updated_at ?? $user->created_at;

            if ($incomingUpdatedAt->gt($currentUpdatedAt)) {
                $user->forceFill($allowed);
                $user->save();
            }
        }
    }

    // ---------------- EVENTS (AQUI ESTÁ A MUDANÇA PRINCIPAL) ----------------

    private function syncEvents(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) {
                continue;
            }

            // Busca o registro existente na VM (se tiver)
            $existing = DB::table('events')
                ->where('id', $data['id'])
                ->first();

            // Título: usa o do payload, senão o existente, senão fallback
            $title = $data['title']
                ?? ($existing->title ?? 'Evento sem título');

            // start_at: NUNCA pode ser null
            // prioridade: payload > existente > now()
            $startAt = $data['start_at']
                ?? ($existing->start_at ?? now());

            // Monta o "row" completo
            $row = [
                'id'          => $data['id'],
                'title'       => $title,
                'description' => $data['description'] ?? ($existing->description ?? null),
                'location'    => $data['location'] ?? ($existing->location ?? null),
                'start_at'    => $startAt,
                'end_at'      => $data['end_at'] ?? ($existing->end_at ?? null),
                'is_all_day'  => $data['is_all_day'] ?? ($existing->is_all_day ?? 0),
                'is_public'   => $data['is_public'] ?? ($existing->is_public ?? 0),
                'capacity'    => $data['capacity'] ?? ($existing->capacity ?? null),
                'deleted_at'  => $data['deleted_at'] ?? ($existing->deleted_at ?? null),
                'created_at'  => $data['created_at'] ?? ($existing->created_at ?? now()),
                'updated_at'  => $data['updated_at'] ?? now(),
            ];

            // Log opcional pra ver esse evento problemático
            if ($data['id'] == 5) {
                \Log::info('SYNC EVENTS (DEBUG id=5)', [
                    'payload'  => $data,
                    'existing' => $existing,
                    'row'      => $row,
                ]);
            }

            // upsert direto via Query Builder
            DB::table('events')->updateOrInsert(
                ['id' => $data['id']],
                $row
            );
        }
    }

    // ---------------- EVENT REGISTRATIONS ----------------

    private function syncEventRegistrations(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) continue;

            if (empty($data['event_id']) || empty($data['user_id'])) {
                \Log::warning('SYNC EVENT_REG: sem event_id ou user_id', [
                    'payload' => $data,
                ]);
                continue;
            }

            $existing = DB::table('event_registrations')
                ->where('event_id', $data['event_id'])
                ->where('user_id', $data['user_id'])
                ->first();

            $row = [
                'id'          => $data['id'],
                'event_id'    => $data['event_id'],
                'user_id'     => $data['user_id'],
                'status'      => $data['status']      ?? ($existing->status      ?? 'pending'),
                'presence_at' => $data['presence_at'] ?? ($existing->presence_at ?? null),
                'deleted_at'  => $data['deleted_at']  ?? ($existing->deleted_at  ?? null),
                'created_at'  => $data['created_at']  ?? ($existing->created_at  ?? now()),
                'updated_at'  => $data['updated_at']  ?? now(),
            ];

            DB::table('event_registrations')->updateOrInsert(
                [
                    'event_id' => $data['event_id'],
                    'user_id'  => $data['user_id'],
                ],
                $row
            );
        }
    }

    // ---------------- CERTIFICATES ----------------

    private function syncCertificates(array $items): void
    {
        $hasPdfPath  = Schema::hasColumn('certificates', 'pdf_path');
        $hasMetadata = Schema::hasColumn('certificates', 'metadata');

        foreach ($items as $data) {
            if (empty($data['id'])) continue;

            if ($data['id'] == 4) {
                \Log::info('SYNC CERTIFICATE ID=4 (DEBUG)', [
                    'payload_user_id' => $data['user_id'] ?? 'NÃO EXISTE NO PAYLOAD',
                    'payload_completo' => $data,
                    'existing' => $existing ?? 'NÃO EXISTE NA VM',
                ]);
            }

            $incomingUpdatedAt = $this->parseIncomingUpdatedAt($data['updated_at'] ?? null);

            $existing = DB::table('certificates')
                ->where('id', $data['id'])
                ->first();

            $row = [
                'id'             => $data['id'],
                'user_id'        => $data['user_id']        ?? ($existing->user_id        ?? null),
                'user_name'      => $data['user_name']      ?? ($existing->user_name      ?? null),
                'user_cpf'       => $data['user_cpf']       ?? ($existing->user_cpf       ?? null),
                'event_id'       => $data['event_id']       ?? ($existing->event_id       ?? null),
                'event_title'    => $data['event_title']    ?? ($existing->event_title    ?? null),
                'event_start_at' => $data['event_start_at'] ?? ($existing->event_start_at ?? null),
                'code'           => $data['code']           ?? ($existing->code           ?? null),
                'issued_at'      => $data['issued_at']      ?? ($existing->issued_at      ?? null),
                'pdf_url'        => $data['pdf_url']        ?? ($existing->pdf_url        ?? null),
                'deleted_at'     => $data['deleted_at']     ?? ($existing->deleted_at     ?? null),
                'created_at'     => $data['created_at']     ?? ($existing->created_at     ?? now()),
                'updated_at'     => $data['updated_at']     ?? now(),
            ];

            if ($hasPdfPath) {
                $row['pdf_path'] = $data['pdf_path'] ?? ($existing->pdf_path ?? null);
            }

            if ($hasMetadata) {
                $row['metadata'] = $data['metadata'] ?? ($existing->metadata ?? null);
            }

            if (!$existing) {
                DB::table('certificates')->insert($row);
                continue;
            }

            if ($incomingUpdatedAt) {
                $currentUpdatedAt = $existing->updated_at
                    ? Carbon::parse($existing->updated_at)
                    : null;

                if (!$currentUpdatedAt || $incomingUpdatedAt->gt($currentUpdatedAt)) {
                    DB::table('certificates')
                        ->where('id', $data['id'])
                        ->update($row);
                }
            } else {
                // Se não veio updated_at, atualiza assim mesmo
                DB::table('certificates')
                    ->where('id', $data['id'])
                    ->update($row);
            }
        }
    }

}
