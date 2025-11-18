<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;


class DatabaseSyncService
{
    public function run(): array
    {
        $remoteUrl = config('services.sync.remote_url');
        $apiKey    = config('services.sync.api_key');

        // Logs para debug
        \Log::info('SYNC → Iniciando sincronização', [
            'remote_url' => $remoteUrl,
            'api_key_ok' => (bool) $apiKey,
        ]);

        if (empty($remoteUrl)) {
            throw new \RuntimeException('SYNC_REMOTE_URL não configurada. Verifique .env e config/services.php.');
        }

        if (empty($apiKey)) {
            throw new \RuntimeException('SYNC_API_KEY não configurada. Verifique .env e config/services.php.');
        }

        // --------------------------------------------------------------------
        // Usando DB::table pra mandar EXATAMENTE o que está no banco
        // --------------------------------------------------------------------
        $payload = [
            'users'               => DB::table('users')->get()->map(fn ($r) => (array) $r)->toArray(),
            'events'              => DB::table('events')->get()->map(fn ($r) => (array) $r)->toArray(),
            'event_registrations' => DB::table('event_registrations')->get()->map(fn ($r) => (array) $r)->toArray(),
            'certificates'        => DB::table('certificates')->get()->map(fn ($r) => (array) $r)->toArray(),
        ];

        \Log::info('SYNC → Payload preparado', [
            'users_count'               => count($payload['users']),
            'events_count'              => count($payload['events']),
            'event_registrations_count' => count($payload['event_registrations']),
            'certificates_count'        => count($payload['certificates']),
        ]);

        // Se quiser logar TUDO (cuidado que pode ficar grande), descomenta:
        // \Log::info('SYNC → PAYLOAD COMPLETO', $payload);

        // --------------------------------------------------------------------
        // Chamada HTTP para o outro ambiente
        // --------------------------------------------------------------------
        $response = Http::withHeaders([
            'X-API-Key' => $apiKey,
        ])->post($remoteUrl, $payload);

        \Log::info('SYNC → Resposta da VM', [
            'status' => $response->status(),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Erro ao sincronizar com a VM (status ' . $response->status() . ', url ' . $remoteUrl . '): ' .
                $response->body()
            );
        }

        $data = $response->json() ?? [];

        // --------------------------------------------------------------------
        // Aplicar o estado retornado no banco local
        // --------------------------------------------------------------------
        DB::beginTransaction();

        try {
            $this->applyUsers($data['users'] ?? []);
            $this->applyEvents($data['events'] ?? []);
            $this->applyEventRegistrations($data['event_registrations'] ?? []);
            $this->applyCertificates($data['certificates'] ?? []);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('SYNC → ERRO ao aplicar dados localmente', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            throw new \RuntimeException('Falha ao aplicar dados locais: ' . $e->getMessage());
        }

        \Log::info('SYNC → Concluído com sucesso', [
            'synced_users'               => count($data['users'] ?? []),
            'synced_events'              => count($data['events'] ?? []),
            'synced_event_registrations' => count($data['event_registrations'] ?? []),
            'synced_certificates'        => count($data['certificates'] ?? []),
        ]);

        return [
            'synced_users'               => count($data['users'] ?? []),
            'synced_events'              => count($data['events'] ?? []),
            'synced_event_registrations' => count($data['event_registrations'] ?? []),
            'synced_certificates'        => count($data['certificates'] ?? []),
        ];
    }

    // ---------------------------------------------------------------------
    // Aplicar dados no banco local
    // ---------------------------------------------------------------------

    private function applyUsers(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['cpf'])) {
                continue;
            }

            // Monta o row completo (garante consistência)
            $row = [
                'cpf'               => $data['cpf'],
                'name'              => $data['name'] ?? null,
                'email'             => $data['email'] ?? null,
                'phone'             => $data['phone'] ?? null,
                'email_verified_at' => $data['email_verified_at'] ?? null,
                'password'          => $data['password'] ?? null,
                'completed'         => $data['completed'] ?? 0,
                'remember_token'    => $data['remember_token'] ?? null,
                'deleted_at'        => $data['deleted_at'] ?? null,
                'created_at'        => $data['created_at'] ?? now(),
                'updated_at'        => $data['updated_at'] ?? now(),
            ];

            // Atualiza ou insere com base no CPF (chave global)
            DB::table('users')->updateOrInsert(
                ['cpf' => $data['cpf']],
                $row
            );
        }
    }


    private function applyEvents(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) continue;

            $event = Event::withTrashed()->find($data['id'])
                ?? new Event();

            $event->forceFill($data);
            $event->save();
        }
    }

    private function applyEventRegistrations(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['id'])) continue;

            $reg = EventRegistration::withTrashed()->find($data['id'])
                ?? new EventRegistration();

            $reg->forceFill($data);
            $reg->save();
        }
    }

    private function applyCertificates(array $items): void
    {
        // Descobre quais colunas existem neste ambiente
        $hasPdfPath  = Schema::hasColumn('certificates', 'pdf_path');
        $hasMetadata = Schema::hasColumn('certificates', 'metadata');

        foreach ($items as $data) {
            if (empty($data['id'])) {
                continue;
            }

            $existing = DB::table('certificates')
                ->where('id', $data['id'])
                ->first();

            // Monta a linha básica, só com as colunas que existem em ambos
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

            // Só adiciona pdf_path se a coluna existir neste banco
            if ($hasPdfPath) {
                $row['pdf_path'] = $data['pdf_path'] ?? ($existing->pdf_path ?? null);
            }

            // Só adiciona metadata se a coluna existir neste banco
            if ($hasMetadata) {
                $row['metadata'] = $data['metadata'] ?? ($existing->metadata ?? null);
            }

            if (!$existing) {
                DB::table('certificates')->insert($row);
            } else {
                DB::table('certificates')
                    ->where('id', $data['id'])
                    ->update($row);
            }
        }
    }
}
