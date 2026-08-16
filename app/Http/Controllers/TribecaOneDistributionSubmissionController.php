<?php

namespace App\Http\Controllers;

use App\Models\TribecaOneDistributionSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class TribecaOneDistributionSubmissionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.clientCompanyName' => ['required', 'string', 'max:255'],
            'fields.clientAddress' => ['required', 'string', 'max:5000'],
            'fields.copyrightLine' => ['required', 'string', 'max:255'],
            'fields.mainContactPerson' => ['required', 'string', 'max:255'],
            'fields.contactEmail' => ['required', 'email', 'max:255'],
            'fields.titleName' => ['required', 'string', 'max:255'],
            'fields.releaseDate' => ['required', 'date'],
            'fields.runtime' => ['required', 'numeric', 'min:1'],
            'fields.videoDefinition' => ['required', 'string', 'max:50'],
            'fields.soundFormat' => ['required', 'string', 'max:50'],
            'fields.primaryGenre' => ['required', 'string', 'max:255'],
            'fields.rating' => ['required', 'string', 'max:100'],
            'fields.director' => ['required', 'string', 'max:255'],
            'fields.synopsis' => ['required', 'string', 'max:5000'],
            'fields.logline' => ['required', 'string', 'max:1000'],
            'fields.deliveryMethod' => ['required', 'string', 'in:cloud,physical'],
            'fields.syncCheckCompleted' => ['accepted'],
            'fields.audioConfigurationConfirmed' => ['accepted'],
            'fields.pcmLittleEndianConfirmed' => ['accepted'],
            'fields.rgbConfirmed' => ['accepted'],
            'fields.aspectRatioConfirmed' => ['accepted'],
            'fields.layeredPsdConfirmed' => ['accepted'],
            'fields.artworkRestrictionsConfirmed' => ['accepted'],
            'fields.reviewedCompanyBanking' => ['accepted'],
            'fields.verifiedMetadata' => ['accepted'],
            'fields.checkedCreditSpelling' => ['accepted'],
            'fields.verifiedLinks' => ['accepted'],
            'fields.finalSyncCheck' => ['accepted'],
            'fields.finalMasterClean' => ['accepted'],
            'fields.finalTrailerClean' => ['accepted'],
            'fields.finalPosterMeetsRequirements' => ['accepted'],
            'fields.platformChangeAcknowledged' => ['accepted'],
            'fields.accurateSubmissionConfirmed' => ['accepted'],
            'fields.submittedBy' => ['required', 'string', 'max:255'],
            'fields.submitterRole' => ['required', 'string', 'max:255'],
            'fields.electronicSignature' => ['required', 'string', 'max:255'],
            'writers' => ['nullable', 'array'],
            'writers.*.name' => ['nullable', 'string', 'max:255'],
            'producers' => ['nullable', 'array'],
            'producers.*.name' => ['nullable', 'string', 'max:255'],
            'cast' => ['nullable', 'array'],
            'cast.*.characterName' => ['nullable', 'string', 'max:255'],
            'cast.*.creditedName' => ['nullable', 'string', 'max:255'],
            'languages' => ['nullable', 'array'],
            'languages.*.language' => ['nullable', 'string', 'max:255'],
            'languages.*.fileFormat' => ['nullable', 'string', 'max:100'],
        ]);

        $fields = $data['fields'];
        $writers = $this->compactRows($data['writers'] ?? [], ['name']);
        $producers = $this->compactRows($data['producers'] ?? [], ['name']);
        $cast = $this->compactRows($data['cast'] ?? [], ['characterName', 'creditedName']);
        $languages = $this->compactRows($data['languages'] ?? [], ['language', 'fileFormat']);

        $submission = TribecaOneDistributionSubmission::create([
            'user_id' => Auth::id(),
            'status' => 'submitted',
            'title_name' => $fields['titleName'] ?? null,
            'client_company_name' => $fields['clientCompanyName'] ?? null,
            'main_contact_person' => $fields['mainContactPerson'] ?? null,
            'contact_email' => $fields['contactEmail'] ?? null,
            'submitted_by' => $fields['submittedBy'] ?? null,
            'submitter_role' => $fields['submitterRole'] ?? null,
            'submitted_at' => now(),
            'licensor_data' => Arr::only($fields, [
                'clientCompanyName',
                'clientAddress',
                'copyrightLine',
                'mainContactPerson',
                'contactEmail',
                'companyState',
            ]),
            'banking_data' => Arr::only($fields, [
                'bankingName',
                'bankingAddress',
                'abaNumber',
                'accountNumber',
            ]),
            'title_data' => Arr::only($fields, [
                'titleName',
                'releaseDate',
                'runtime',
                'videoDefinition',
                'soundFormat',
                'primaryGenre',
                'secondaryGenre',
                'rating',
                'director',
                'synopsis',
                'logline',
            ]),
            'credits_data' => [
                'writers' => $writers,
                'producers' => $producers,
                'cast' => $cast,
            ],
            'links_data' => Arr::only($fields, [
                'imdbLink',
                'trailerLink',
                'screenerLink',
                'artworkLinkNotFinal',
                'screenerPassword',
            ]),
            'delivery_data' => Arr::only($fields, [
                'deliveryMethod',
                'masterFolderLink',
                'downloadPassword',
                'linkExpirationDate',
                'driveLabeledConfirmed',
            ]),
            'master_data' => Arr::only($fields, [
                'masterDeliveryLink',
                'masterFileType',
                'resolution',
                'bitrate',
                'runtimeConfirmation',
                'finalMixIncluded',
                'syncCheckCompleted',
                'containsBars',
                'technicalNotes',
            ]),
            'audio_data' => Arr::only($fields, [
                'audioFileType',
                'audioMix',
                'audioConfigurationConfirmed',
                'pcmLittleEndianConfirmed',
                'individualWavTracks',
                'audioDeliveryLink',
            ]),
            'captions_data' => array_merge(Arr::only($fields, [
                'captionsAvailable',
                'captionAssetType',
                'captionDeliveryLink',
                'sccNonDropFrame',
                'revAuthorization',
                'revCostAcknowledged',
            ]), [
                'languages' => $languages,
            ]),
            'trailer_data' => Arr::only($fields, [
                'publicTrailerLink',
                'trailerMasterDeliveryLink',
                'trailerFileType',
                'trailerRuntime',
                'trailerNoUrls',
                'trailerNoAvailabilityLanguage',
            ]),
            'artwork_data' => Arr::only($fields, [
                'finalPosterPsdLink',
                'finalPosterPreviewName',
                'landscapeArtworkAvailable',
                'landscapePsdLink',
                'artworkDimensions',
                'rgbConfirmed',
                'aspectRatioConfirmed',
                'layeredPsdConfirmed',
                'artworkRestrictionsConfirmed',
            ]),
            'final_review_data' => Arr::only($fields, [
                'reviewedCompanyBanking',
                'verifiedMetadata',
                'checkedCreditSpelling',
                'verifiedLinks',
                'finalSyncCheck',
                'finalMasterClean',
                'finalTrailerClean',
                'finalPosterMeetsRequirements',
                'platformChangeAcknowledged',
                'accurateSubmissionConfirmed',
                'submittedBy',
                'submitterRole',
                'electronicSignature',
                'submissionDate',
            ]),
            'additional_notes' => $fields['additionalNotes'] ?? null,
        ]);

        $submission->email_html = view('emails.tribeca-one-distribution-submission', [
            'submission' => $submission,
        ])->render();
        $submission->save();

        return response()->json([
            'message' => 'Tribeca One Distribution submission saved.',
            'submission_id' => $submission->id,
        ], 201);
    }

    private function compactRows(array $rows, array $keys): array
    {
        return collect($rows)
            ->map(fn ($row) => Arr::only((array) $row, $keys))
            ->filter(fn ($row) => collect($row)->filter(fn ($value) => trim((string) $value) !== '')->isNotEmpty())
            ->values()
            ->all();
    }
}
