<?php

namespace App\Services;

use App\Models\User;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DatabaseSyncService
{
    public function run(): array
    {
        // -----------------------------------------
        // Carregar configs de sync
        // -----------------------------------------
        $remoteUrl = config('services.sync.remote_url');
        $apiKey    = config('services.sync.api_key');

        // Logar para debug (remove depois se quiser)
        \Log::info('SYNC REMOTE URL = ' . $remoteUrl);
        \Log::info('SYNC API KEY = ' . ($apiKey ? 'OK' : 'MISSING'));

        // -----------------------------------------
        // Validar configurações
        // -----------------------------------------
        if (empty($remoteUrl)) {
            throw new \RuntimeException(
                'SYNC_REMOTE_URL não configurada. Verifique .env e config/services.php.'
            );
        }

        if (empty($apiKey)) {
            throw new \RuntimeException(
                'SYNC_API_KEY não configurada. Verifique .env e config/services.php.'
            );
        }

        // -----------------------------------------
        // Coletar dados locais para enviar
        // -----------------------------------------
        $payload = [
            'users'                => User::withTrashed()->get()->toArray(),
            'events'               => Event::withTrashed()->get()->toArray(),
            'event_registrations'  => EventRegistration::withTrashed()->get()->toArray(),
            'certificates'         => DB::table('certificates')->get()->toArray(),
        ];

        // -----------------------------------------
        // Enviar dados para o servidor remoto
        // -----------------------------------------
        $response = Http::withHeaders([
            'X-API-Key' => $apiKey,
        ])->post($remoteUrl, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'Erro ao sincronizar com a VM (status ' . $response->status() . ', url ' . $remoteUrl . '): ' .
                $response->body()
            );
        }

        // -----------------------------------------
        // Dados retornados do servidor remoto
        // -----------------------------------------
        $data = $response->json();

        // -----------------------------------------
        // Aplicar merge no banco local
        // -----------------------------------------
        DB::beginTransaction();

        try {
            $this->applyUsers($data['users'] ?? []);
            $this->applyEvents($data['events'] ?? []);
            $this->applyEventRegistrations($data['event_registrations'] ?? []);
            $this->applyCertificates($data['certificates'] ?? []);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw new \RuntimeException('Falha ao aplicar dados locais: ' . $e->getMessage());
        }

        // -----------------------------------------
        // Resumo da sincronização
        // -----------------------------------------
        return [
            'synced_users'               => count($data['users'] ?? []),
            'synced_events'              => count($data['events'] ?? []),
            'synced_event_registrations' => count($data['event_registrations'] ?? []),
            'synced_certificates'        => count($data['certificates'] ?? []),
        ];
    }

    // ---------------------------------------------------------
    // APLICAÇÃO DOS DADOS
    // ---------------------------------------------------------

    private function applyUsers(array $items): void
    {
        foreach ($items as $data) {
            if (empty($data['cpf'])) continue;

            $user = User::withTrashed()->where('cpf', $data['cpf'])->first()
                ?? new User();

            $user->forceFill($data);
            $user->save();
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
        foreach ($items as $data) {
            if (empty($data['id'])) continue;

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
