<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvalidPlan extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $validity;
    public $views;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($name, $validity, $views)
    {
        $this->name = $name;
        $this->validity = $validity;
        $this->views = $views;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('no-reply@wizzer.com.br', 'Wizzer')
            ->subject('Seu anúncio venceu hoje!')
            ->view('emails.invalidplan');
    }
}
