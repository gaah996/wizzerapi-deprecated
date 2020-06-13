<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PlanInformation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $price;
    public $advertsNumber;

    public function __construct($user, $advertsNumber, $price)
    {
        $this->user = $user;
        $this->price = $price;
        $this->advertsNumber = $advertsNumber;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email =  $this->from($this->user->email)
            ->subject('Nova solicitação de plano personalizado')
            ->view('emails.planinformation');

        return $email;
    }
}
