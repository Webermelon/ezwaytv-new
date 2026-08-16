@php
    $formatLabel = function (string $key): string {
        $label = preg_replace('/(?<!^)[A-Z]/', ' $0', $key);
        $label = str_replace(['_', '-'], ' ', $label);

        return ucwords($label);
    };

    $formatValue = function ($value, ?string $key = null) use (&$formatValue): string {
        if (in_array($key, ['screenerPassword', 'downloadPassword'], true)) {
            return trim((string) $value) === '' ? 'Not provided' : 'Captured and hidden from email';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn ($item, $itemKey) => is_array($item)
                    ? collect($item)->map(fn ($nestedValue, $nestedKey) => $formatValue($nestedValue, (string) $nestedKey))->implode(' / ')
                    : $formatValue($item, is_string($itemKey) ? $itemKey : null)
                )
                ->filter()
                ->implode('<br>');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 'Not provided';
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $escaped = e($value);
            return '<a href="' . $escaped . '" style="color:#b68419;text-decoration:underline;">' . $escaped . '</a>';
        }

        return nl2br(e($value));
    };

    $sections = [
        'Licensor Information' => $submission->licensor_data ?? [],
        'Banking Information' => array_merge($submission->maskedBankingData(), [
            'Security Note' => 'Full banking values are stored in the protected database field and are not exposed in email HTML.',
        ]),
        'Title Information' => $submission->title_data ?? [],
        'Credits and Cast' => $submission->credits_data ?? [],
        'Public and Review Links' => $submission->links_data ?? [],
        'Delivery Method' => $submission->delivery_data ?? [],
        'Feature Film Master' => $submission->master_data ?? [],
        'Audio Specifications' => $submission->audio_data ?? [],
        'Captions and Subtitles' => $submission->captions_data ?? [],
        'Trailer Delivery' => $submission->trailer_data ?? [],
        'Poster and Key Artwork' => $submission->artwork_data ?? [],
        'Final Review and Agreement' => $submission->final_review_data ?? [],
    ];
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tribeca One Distribution Submission</title>
</head>
<body style="margin:0;background:#f4f1ea;color:#171717;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f1ea;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:920px;background:#ffffff;border:1px solid #ded8cc;">
                    <tr>
                        <td style="background:#090909;color:#ffffff;padding:28px 32px;">
                            <div style="font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#d4a843;">New Onboarding Submission</div>
                            <h1 style="margin:10px 0 0;font-size:28px;line-height:1.2;">Tribeca One Distribution</h1>
                            <p style="margin:12px 0 0;color:#cfc7ba;font-size:14px;line-height:1.6;">
                                {{ $submission->title_name ?: 'Untitled submission' }} from {{ $submission->client_company_name ?: 'Unknown company' }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:10px;border:1px solid #ebe5db;background:#faf8f3;font-size:12px;color:#6b6257;">Submission ID</td>
                                    <td style="padding:10px;border:1px solid #ebe5db;font-size:13px;font-weight:700;">#{{ $submission->id }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px;border:1px solid #ebe5db;background:#faf8f3;font-size:12px;color:#6b6257;">Submitted At</td>
                                    <td style="padding:10px;border:1px solid #ebe5db;font-size:13px;font-weight:700;">{{ optional($submission->submitted_at)->format('M j, Y g:i A') ?: 'Not recorded' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px;border:1px solid #ebe5db;background:#faf8f3;font-size:12px;color:#6b6257;">Submitter</td>
                                    <td style="padding:10px;border:1px solid #ebe5db;font-size:13px;font-weight:700;">{{ $submission->submitted_by ?: 'Not provided' }}{{ $submission->submitter_role ? ' - ' . $submission->submitter_role : '' }}</td>
                                </tr>
                            </table>

                            @foreach ($sections as $sectionTitle => $sectionRows)
                                <h2 style="margin:26px 0 10px;font-size:18px;line-height:1.3;color:#111111;border-bottom:2px solid #d4a843;padding-bottom:8px;">{{ $sectionTitle }}</h2>
                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
                                    @forelse ($sectionRows as $key => $value)
                                        <tr>
                                            <td valign="top" style="width:34%;padding:10px;border:1px solid #ebe5db;background:#faf8f3;font-size:12px;font-weight:700;color:#6b6257;">
                                                {{ is_string($key) ? $formatLabel($key) : 'Item ' . ($key + 1) }}
                                            </td>
                                            <td valign="top" style="padding:10px;border:1px solid #ebe5db;font-size:13px;line-height:1.6;color:#171717;">
                                                {!! $formatValue($value, is_string($key) ? $key : null) !!}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td style="padding:10px;border:1px solid #ebe5db;font-size:13px;color:#6b6257;">No data provided.</td>
                                        </tr>
                                    @endforelse
                                </table>
                            @endforeach

                            <h2 style="margin:26px 0 10px;font-size:18px;line-height:1.3;color:#111111;border-bottom:2px solid #d4a843;padding-bottom:8px;">Additional Notes</h2>
                            <div style="padding:14px;border:1px solid #ebe5db;background:#faf8f3;font-size:13px;line-height:1.6;">
                                {!! $formatValue($submission->additional_notes) !!}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#111111;color:#cfc7ba;padding:18px 32px;font-size:12px;line-height:1.6;">
                            This HTML was generated for a future email workflow. No email was sent when the submission was saved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
