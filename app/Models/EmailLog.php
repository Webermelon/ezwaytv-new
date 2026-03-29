<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = [
        'mailer',
        'from_email',
        'to_emails',
        'cc_emails',
        'bcc_emails',
        'subject',
        'mailable_class',
        'message_id',
        'status',
        'body',
        'payload',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
