<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class MailSupport extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $email;
    public $subject;
    public $messages;
    public $attachment;

    public function __construct($name, $email, $subject, $messages, $attachment)
    {
        $this->name = $name;
        $this->email = $email;
        $this->subject = $subject;
        $this->messages = $messages;
        $this->attachment = $attachment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email =  $this->from($this->email)
            ->to('suporte@wizzer.com.br')
            ->subject($this->subject)
            ->view('emails.emailsupport');

        foreach($this->attachment as $attachment){
            $email->attach($attachment->getRealPath(),
                [
                    'as' => $attachment->getClientOriginalName(),
                    'mime' => $attachment->getClientMimeType()
                ]);
        }

        return $email;
    }
}
