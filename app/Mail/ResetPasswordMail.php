<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $userName,
        public readonly string $code,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recupera tu contraseña en Zumifly',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $name = e($this->userName);
        $code = e($this->code);

        return <<<HTML
        <div style="font-family: sans-serif; max-width: 480px; margin: 0 auto;">
            <h2>Hola, {$name}</h2>
            <p>Recibimos una solicitud para restablecer tu contraseña en Zumifly.</p>
            <p style="font-size: 28px; letter-spacing: 6px; font-weight: bold;">{$code}</p>
            <p>Este código expira en 30 minutos. Si no solicitaste el cambio, ignora este correo.</p>
        </div>
        HTML;
    }
}
