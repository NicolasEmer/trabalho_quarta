<?php
// app/Http/Controllers/Api/EmailController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\SendEmailRequest;
use App\Mail\GenericMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

class EmailController extends Controller
{
    public function store(SendEmailRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Monta o Mailable
        $mailable = (new GenericMail(
            subject: $data['subject'],
            html: $data['html'] ?? null,
            text: $data['text'] ?? null,
            headers: $data['headers'] ?? []
        ))
            ->onQueue($data['queue'] ?? false ? 'mail' : null);

        $to = $data['to'];
        $cc = $data['cc'] ?? [];
        $bcc = $data['bcc'] ?? [];

        foreach ($data['attachments'] ?? [] as $att) {
            $content = base64_decode($att['content']);
            $mailable->attachData($content, $att['filename'], [
                'mime' => $att['mime'] ?? 'application/octet-stream'
            ]);
        }

        // Envio
        Mail::to($to)
            ->cc($cc)
            ->bcc($bcc)
            ->send($mailable);

        return response()->json([
            'message' => 'E-mail enviado com sucesso.',
        ], 202);
    }
}
