<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
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
            subject: 'Confirma tu cuenta en Zumifly',
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
        <div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px">
            <h2 style="color:#6340E8">Bienvenido a Zumifly</h2>
            <p>Hola <strong>{$name}</strong>,</p>
            <p>Usa este código para confirmar tu cuenta (válido 30 minutos):</p>
            <p style="font-size:28px;letter-spacing:6px;font-weight:bold;color:#1a1a2e">{$code}</p>
            <p style="color:#5c5c7a;font-size:14px">Si no creaste esta cuenta, ignora este mensaje.</p>
        </div>
        HTML;
    }
}
