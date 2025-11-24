<?php
// app/Http/Controllers/Api/EmailController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Email\SendEmailRequest;
use App\Mail\GenericMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Emails",
 *     description="Envio de e-mails transacionais."
 * )
 */

class EmailController extends Controller
{

    /**
     * Envio de e-mail.
     *
     * @OA\Post(
     *     path="/api/v1/emails",
     *     tags={"Emails"},
     *     summary="Envia um e-mail",
     *     security={{"bearerAuth":{}}},
     *     description="Envia um e-mail utilizando a fila opcionalmente e com anexos em base64.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"to","subject"},
     *             @OA\Property(
     *                 property="to",
     *                 type="string",
     *                 format="email",
     *                 example="elivan.spiecker@universo.univates.br"
     *             ),
     *             @OA\Property(
     *                 property="cc",
     *                 type="array",
     *                 @OA\Items(type="string", format="email")
     *             ),
     *             @OA\Property(
     *                 property="bcc",
     *                 type="array",
     *                 @OA\Items(type="string", format="email")
     *             ),
     *             @OA\Property(
     *                 property="subject",
     *                 type="string",
     *                 example="Confirmação de inscrição no evento"
     *             ),
     *             @OA\Property(
     *                 property="html",
     *                 type="string",
     *                 nullable=true,
     *                 example="<p>Olá, sua inscrição foi confirmada.</p>"
     *             ),
     *             @OA\Property(
     *                 property="text",
     *                 type="string",
     *                 nullable=true,
     *                 example="Olá, sua inscrição foi confirmada."
     *             ),
     *             @OA\Property(
     *                 property="headers",
     *                 type="object",
     *                 example={"X-System": "EventosAPI"}
     *             ),
     *             @OA\Property(
     *                 property="queue",
     *                 type="boolean",
     *                 example=true,
     *                 description="Se true, envia pela fila 'mail'."
     *             ),
     *             @OA\Property(
     *                 property="attachments",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"filename","content"},
     *                     @OA\Property(property="filename", type="string", example="certificado.pdf"),
     *                     @OA\Property(
     *                         property="content",
     *                         type="string",
     *                         description="Arquivo em base64",
     *                         example="JVBERi0xLjQKJc..."
     *                     ),
     *                     @OA\Property(
     *                         property="mime",
     *                         type="string",
     *                         example="application/pdf"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="E-mail aceito para envio.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="E-mail enviado com sucesso."
     *             )
     *         )
     *     )
     * )
     */

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
