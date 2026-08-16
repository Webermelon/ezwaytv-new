<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class TribecaOneDistributionSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'title_name',
        'client_company_name',
        'main_contact_person',
        'contact_email',
        'submitted_by',
        'submitter_role',
        'submitted_at',
        'licensor_data',
        'banking_data',
        'title_data',
        'credits_data',
        'links_data',
        'delivery_data',
        'master_data',
        'audio_data',
        'captions_data',
        'trailer_data',
        'artwork_data',
        'final_review_data',
        'additional_notes',
        'email_html',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'licensor_data' => 'array',
        'title_data' => 'array',
        'credits_data' => 'array',
        'links_data' => 'array',
        'delivery_data' => 'array',
        'master_data' => 'array',
        'audio_data' => 'array',
        'captions_data' => 'array',
        'trailer_data' => 'array',
        'artwork_data' => 'array',
        'final_review_data' => 'array',
    ];

    public function setBankingDataAttribute($value): void
    {
        $this->attributes['banking_data'] = empty($value)
            ? null
            : Crypt::encryptString(json_encode($value));
    }

    public function getBankingDataAttribute($value): ?array
    {
        if (empty($value)) {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($value), true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function maskedBankingData(): array
    {
        $banking = $this->banking_data ?? [];

        return [
            'Name' => $banking['bankingName'] ?? null,
            'Address' => $banking['bankingAddress'] ?? null,
            'ABA Number' => $this->maskValue($banking['abaNumber'] ?? null),
            'Account Number' => $this->maskValue($banking['accountNumber'] ?? null),
        ];
    }

    private function maskValue(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return str_repeat('*', max(strlen($value) - 4, 0)) . substr($value, -4);
    }
}
