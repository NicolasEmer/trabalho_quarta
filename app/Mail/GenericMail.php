<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public  $subject,
        public $html = null,
        public ?string $text = null,
        public array $headers = []
    ) {}

    public function build()
    {
        $email = $this->subject($this->subject);

        // Headers customizados (Laravel 10/11 usa Symfony Mailer)
        $email->withSymfonyMessage(function ($message) {
            $headers = $message->getHeaders();
            foreach ($this->headers as $name => $value) {
                $headers->addTextHeader($name, $value);
            }
        });

        // Corpo: html e/ou texto
        if ($this->html) {
            $email->html($this->html);
            if ($this->text) {
                $email->text('mail.generic_text_alt')->with(['textAlt' => $this->text]);
            }
        } elseif ($this->text) {
            $email->text('mail.generic_text_alt')->with(['textAlt' => $this->text]);
        } else {
            $email->text('mail.generic_text_alt')->with(['textAlt' => '']);
        }

        return $email;
    }
}
