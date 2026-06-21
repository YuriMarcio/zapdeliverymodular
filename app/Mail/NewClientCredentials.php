<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewClientCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $storeName,
        public string $ownerName,
        public string $email,
        public string $password,
    ) {}

    public function build(): self
    {
        return $this->subject("Acesso ao painel SINAL — {$this->storeName}")
            ->view('emails.new-client-credentials');
    }
}
