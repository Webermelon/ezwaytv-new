import { useEffect, useMemo, useState } from 'react'
import { ArrowLeft, ArrowRight, ClipboardCheck, FileCheck2, Lock, Plus, Save, Send, ShieldCheck, Trash2, Upload } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { api } from '@/lib/api'

type FieldValue = string | boolean
type FormState = Record<string, FieldValue>
type TextFieldType = 'text' | 'email' | 'url' | 'date' | 'number' | 'password'

type CreditRow = { id: string; name: string }
type CastRow = { id: string; characterName: string; creditedName: string }
type LanguageRow = { id: string; language: string; fileFormat: string }

type DraftState = {
  fields: FormState
  writers: CreditRow[]
  producers: CreditRow[]
  cast: CastRow[]
  languages: LanguageRow[]
}

type SubmissionResponse = {
  message: string
  submission_id: number
}

const draftKey = 'tribeca-one-distribution-draft-v1'

const steps = [
  'Company Information',
  'Title Metadata',
  'Cast and Credits',
  'Links and Screener',
  'Feature and Audio Delivery',
  'Captions and Subtitles',
  'Trailer',
  'Artwork',
  'Final Review',
  'Submit',
] as const

const defaultFields: FormState = {
  clientCompanyName: '',
  clientAddress: '',
  copyrightLine: '',
  mainContactPerson: '',
  contactEmail: '',
  companyState: '',
  bankingName: '',
  bankingAddress: '',
  abaNumber: '',
  accountNumber: '',
  titleName: '',
  releaseDate: '',
  runtime: '',
  videoDefinition: 'HD',
  soundFormat: 'Stereo',
  primaryGenre: '',
  secondaryGenre: '',
  rating: '',
  director: '',
  synopsis: '',
  logline: '',
  imdbLink: '',
  trailerLink: '',
  screenerLink: '',
  artworkLinkNotFinal: '',
  screenerPassword: '',
  deliveryMethod: 'cloud',
  masterFolderLink: '',
  downloadPassword: '',
  linkExpirationDate: '',
  driveLabeledConfirmed: false,
  masterDeliveryLink: '',
  masterFileType: 'Apple ProRes 422 HQ',
  resolution: '1920 x 1080',
  bitrate: '',
  runtimeConfirmation: '',
  finalMixIncluded: 'yes',
  syncCheckCompleted: false,
  containsBars: 'no',
  technicalNotes: '',
  audioFileType: 'Attached to Master',
  audioMix: 'Stereo',
  audioConfigurationConfirmed: false,
  pcmLittleEndianConfirmed: false,
  individualWavTracks: 'no',
  audioDeliveryLink: '',
  captionsAvailable: 'yes',
  captionAssetType: 'Closed captions',
  captionDeliveryLink: '',
  sccNonDropFrame: false,
  revAuthorization: false,
  revCostAcknowledged: false,
  publicTrailerLink: '',
  trailerMasterDeliveryLink: '',
  trailerFileType: 'ProRes HQ',
  trailerRuntime: '',
  trailerNoUrls: false,
  trailerNoAvailabilityLanguage: false,
  finalPosterPsdLink: '',
  finalPosterPreviewName: '',
  landscapeArtworkAvailable: 'no',
  landscapePsdLink: '',
  artworkDimensions: '2000 x 3000',
  rgbConfirmed: false,
  aspectRatioConfirmed: false,
  layeredPsdConfirmed: false,
  artworkRestrictionsConfirmed: false,
  reviewedCompanyBanking: false,
  verifiedMetadata: false,
  checkedCreditSpelling: false,
  verifiedLinks: false,
  finalSyncCheck: false,
  finalMasterClean: false,
  finalTrailerClean: false,
  finalPosterMeetsRequirements: false,
  platformChangeAcknowledged: false,
  accurateSubmissionConfirmed: false,
  submittedBy: '',
  submitterRole: '',
  electronicSignature: '',
  submissionDate: new Date().toISOString().slice(0, 10),
  additionalNotes: '',
}

const requiredByStep: Record<number, string[]> = {
  0: ['clientCompanyName', 'clientAddress', 'copyrightLine', 'mainContactPerson', 'contactEmail'],
  1: ['titleName', 'releaseDate', 'runtime', 'videoDefinition', 'soundFormat', 'primaryGenre', 'rating', 'director', 'synopsis', 'logline'],
  3: ['deliveryMethod'],
  4: ['syncCheckCompleted', 'audioConfigurationConfirmed', 'pcmLittleEndianConfirmed'],
  7: ['rgbConfirmed', 'aspectRatioConfirmed', 'layeredPsdConfirmed', 'artworkRestrictionsConfirmed'],
  8: [
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
  ],
  9: ['submittedBy', 'submitterRole', 'electronicSignature'],
}

const fieldLabels: Record<string, string> = {
  clientCompanyName: 'Client company name',
  clientAddress: 'Client address',
  copyrightLine: 'Copyright line',
  mainContactPerson: 'Main contact person',
  contactEmail: 'Contact person email',
  titleName: 'Title name',
  releaseDate: 'Release date',
  runtime: 'Runtime',
  videoDefinition: 'Video definition',
  soundFormat: 'Sound format',
  primaryGenre: 'Primary genre',
  rating: 'Rating',
  director: 'Director',
  synopsis: 'Short description/synopsis',
  logline: 'Logline',
  deliveryMethod: 'Delivery method',
  syncCheckCompleted: 'Sync check completed',
  audioConfigurationConfirmed: 'Audio configuration confirmed',
  pcmLittleEndianConfirmed: 'PCM Little-Endian confirmed',
  rgbConfirmed: 'RGB confirmed',
  aspectRatioConfirmed: '2:3 aspect ratio confirmed',
  layeredPsdConfirmed: 'Layered PSD confirmed',
  artworkRestrictionsConfirmed: 'Artwork restrictions confirmed',
  reviewedCompanyBanking: 'Company and banking reviewed',
  verifiedMetadata: 'Title metadata verified',
  checkedCreditSpelling: 'Credit spelling checked',
  verifiedLinks: 'Links verified',
  finalSyncCheck: 'Final sync check completed',
  finalMasterClean: 'Master delivery material confirmed clean',
  finalTrailerClean: 'Trailer restrictions confirmed',
  finalPosterMeetsRequirements: 'Poster requirements confirmed',
  platformChangeAcknowledged: 'Platform change limitation acknowledged',
  accurateSubmissionConfirmed: 'Submission accuracy confirmed',
  submittedBy: 'Submitted by',
  submitterRole: 'Submitter title/role',
  electronicSignature: 'Electronic signature',
}

const textInputClass = 'min-h-11 w-full rounded-md border border-white/10 bg-white/[0.055] px-3 text-sm font-semibold text-white outline-none transition placeholder:text-white/32 focus:border-[#d4a843]/70 focus:bg-white/[0.08]'
const textareaClass = `${textInputClass} min-h-28 py-3 leading-6`
const selectClass = `${textInputClass} appearance-none`

const newId = () => Math.random().toString(36).slice(2, 10)

function defaultDraft(): DraftState {
  return {
    fields: defaultFields,
    writers: [{ id: newId(), name: '' }],
    producers: [{ id: newId(), name: '' }],
    cast: [{ id: newId(), characterName: '', creditedName: '' }],
    languages: [{ id: newId(), language: '', fileFormat: '.SRT' }],
  }
}

function readDraft(): DraftState {
  if (typeof window === 'undefined') return defaultDraft()

  try {
    const raw = window.localStorage.getItem(draftKey)
    if (!raw) return defaultDraft()
    const parsed = JSON.parse(raw) as Partial<DraftState>

    return {
      fields: { ...defaultFields, ...(parsed.fields ?? {}) },
      writers: parsed.writers?.length ? parsed.writers : [{ id: newId(), name: '' }],
      producers: parsed.producers?.length ? parsed.producers : [{ id: newId(), name: '' }],
      cast: parsed.cast?.length ? parsed.cast : [{ id: newId(), characterName: '', creditedName: '' }],
      languages: parsed.languages?.length ? parsed.languages : [{ id: newId(), language: '', fileFormat: '.SRT' }],
    }
  } catch {
    return defaultDraft()
  }
}

export function TribecaOneDistributionFormPage() {
  const initialDraft = useMemo(() => readDraft(), [])
  const [currentStep, setCurrentStep] = useState(0)
  const [fields, setFields] = useState<FormState>(initialDraft.fields)
  const [writers, setWriters] = useState<CreditRow[]>(initialDraft.writers)
  const [producers, setProducers] = useState<CreditRow[]>(initialDraft.producers)
  const [cast, setCast] = useState<CastRow[]>(initialDraft.cast)
  const [languages, setLanguages] = useState<LanguageRow[]>(initialDraft.languages)
  const [lastSavedAt, setLastSavedAt] = useState<string | null>(null)
  const [submitted, setSubmitted] = useState(false)
  const [submissionId, setSubmissionId] = useState<number | null>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [submitError, setSubmitError] = useState<string | null>(null)
  const [attemptedStep, setAttemptedStep] = useState<number | null>(null)

  const draft = useMemo(() => ({ fields, writers, producers, cast, languages }), [fields, writers, producers, cast, languages])

  useEffect(() => {
    const timeout = window.setTimeout(() => saveDraft(draft, setLastSavedAt), 450)
    return () => window.clearTimeout(timeout)
  }, [draft])

  const requiredErrors = getRequiredErrors(fields, currentStep)
  const allRequiredErrors = Object.keys(requiredByStep)
    .flatMap((step) => getRequiredErrors(fields, Number(step)))

  const progress = Math.round(((currentStep + 1) / steps.length) * 100)

  function updateField(name: string, value: FieldValue) {
    setFields((current) => ({ ...current, [name]: value }))
  }

  function goNext() {
    setAttemptedStep(currentStep)
    if (requiredErrors.length > 0) return
    setCurrentStep((step) => Math.min(step + 1, steps.length - 1))
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  function goPrevious() {
    setCurrentStep((step) => Math.max(step - 1, 0))
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  async function submitForm() {
    setAttemptedStep(currentStep)
    setSubmitError(null)
    if (allRequiredErrors.length > 0) {
      const firstInvalidStep = Number(Object.keys(requiredByStep).find((step) => getRequiredErrors(fields, Number(step)).length > 0) ?? 0)
      setCurrentStep(firstInvalidStep)
      return
    }

    setIsSubmitting(true)

    try {
      const response = await api.post<SubmissionResponse>('/tribeka-one-distribution/submissions', toSubmissionPayload(draft))
      setSubmissionId(response.submission_id)
      window.localStorage.removeItem(draftKey)
      setSubmitted(true)
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (error) {
      setSubmitError(submissionErrorMessage(error))
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <main className="min-h-screen bg-[#080808] text-white">
      <AppHeader active="distribution" />

      <section className="border-b border-white/10 bg-[linear-gradient(135deg,#0a0a0a_0%,#16120b_52%,#071112_100%)] px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-7xl">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <div className="inline-flex items-center gap-2 rounded-md border border-[#d4a843]/30 bg-[#d4a843]/10 px-3 py-1 text-[11px] font-black uppercase tracking-[0.18em] text-[#f0c96a]">
                <FileCheck2 className="h-3.5 w-3.5" />
                Licensor Onboarding
              </div>
              <h1 className="mt-4 max-w-4xl break-words text-3xl font-black uppercase leading-tight tracking-normal text-white sm:text-5xl">
                Tribeca One Distribution Onboarding Form
              </h1>
              <p className="mt-4 max-w-3xl text-sm leading-7 text-white/62">
                Submit company details, title metadata, delivery links, captions, trailer assets, and final artwork in one organized workflow.
              </p>
            </div>
            <div className="rounded-md border border-white/10 bg-white/[0.045] p-4 text-sm text-white/70">
              <div className="font-black text-white">{progress}% complete</div>
              <div className="mt-2 h-2 w-full min-w-[240px] overflow-hidden rounded-full bg-white/10">
                <div className="h-full rounded-full bg-[#d4a843]" style={{ width: `${progress}%` }} />
              </div>
              <div className="mt-2 text-xs font-semibold text-white/46">{lastSavedAt ? `Draft saved ${lastSavedAt}` : 'Draft autosaves locally'}</div>
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto grid max-w-7xl gap-6 px-4 py-8 sm:px-8 lg:grid-cols-[280px_minmax(0,1fr)] lg:px-12">
        <aside className="lg:sticky lg:top-24 lg:self-start">
          <div className="overflow-hidden rounded-md border border-white/10 bg-[#101010]">
            {steps.map((step, index) => (
              <button
                key={step}
                type="button"
                onClick={() => setCurrentStep(index)}
                className={[
                  'flex min-h-12 w-full items-center gap-3 border-b border-white/8 px-4 text-left text-sm font-bold transition last:border-b-0',
                  currentStep === index ? 'bg-[#d4a843] text-black' : 'text-white/62 hover:bg-white/[0.06] hover:text-white',
                ].join(' ')}
              >
                <span className={['flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-black', currentStep === index ? 'bg-black text-[#d4a843]' : 'bg-white/8 text-white/64'].join(' ')}>
                  {index + 1}
                </span>
                <span className="min-w-0 flex-1">{step}</span>
              </button>
            ))}
          </div>
        </aside>

        <div className="min-w-0">
          {submitted ? (
            <SubmissionSuccess fields={fields} submissionId={submissionId} onEdit={() => { setSubmitted(false); setCurrentStep(8) }} />
          ) : (
            <form
              className="rounded-md border border-white/10 bg-[#101010] shadow-2xl shadow-black/30"
              onSubmit={(event) => {
                event.preventDefault()
                void submitForm()
              }}
            >
              <div className="border-b border-white/10 p-5 sm:p-7">
                <div className="text-xs font-black uppercase tracking-[0.18em] text-[#d4a843]">Step {currentStep + 1} of {steps.length}</div>
                <h2 className="mt-2 text-2xl font-black tracking-normal text-white sm:text-3xl">{steps[currentStep]}</h2>
              </div>

              <div className="p-5 sm:p-7">
                {attemptedStep === currentStep && requiredErrors.length > 0 ? <ErrorPanel errors={requiredErrors} /> : null}
                {submitError ? <ErrorPanel errors={[submitError]} /> : null}

                {currentStep === 0 ? <CompanyStep fields={fields} updateField={updateField} /> : null}
                {currentStep === 1 ? <TitleStep fields={fields} updateField={updateField} /> : null}
                {currentStep === 2 ? (
                  <CreditsStep
                    writers={writers}
                    producers={producers}
                    cast={cast}
                    setWriters={setWriters}
                    setProducers={setProducers}
                    setCast={setCast}
                  />
                ) : null}
                {currentStep === 3 ? <LinksStep fields={fields} updateField={updateField} /> : null}
                {currentStep === 4 ? <FeatureAudioStep fields={fields} updateField={updateField} /> : null}
                {currentStep === 5 ? (
                  <CaptionsStep
                    fields={fields}
                    languages={languages}
                    updateField={updateField}
                    setLanguages={setLanguages}
                  />
                ) : null}
                {currentStep === 6 ? <TrailerStep fields={fields} updateField={updateField} /> : null}
                {currentStep === 7 ? <ArtworkStep fields={fields} updateField={updateField} /> : null}
                {currentStep === 8 ? <FinalReviewStep fields={fields} updateField={updateField} writers={writers} producers={producers} cast={cast} languages={languages} /> : null}
                {currentStep === 9 ? <SubmitStep fields={fields} updateField={updateField} errors={allRequiredErrors} /> : null}
              </div>

              <div className="flex flex-col gap-3 border-t border-white/10 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                <button
                  type="button"
                  onClick={() => saveDraft(draft, setLastSavedAt)}
                  className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-white/10 px-4 text-sm font-black text-white transition hover:bg-white/[0.07]"
                >
                  <Save className="h-4 w-4" />
                  Save Draft
                </button>

                <div className="flex gap-3">
                  <button
                    type="button"
                    onClick={goPrevious}
                    disabled={currentStep === 0}
                    className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-white/10 px-4 text-sm font-black text-white transition hover:bg-white/[0.07] disabled:opacity-35"
                  >
                    <ArrowLeft className="h-4 w-4" />
                    Back
                  </button>
                  {currentStep < steps.length - 1 ? (
                    <button
                      type="button"
                      onClick={goNext}
                      className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-[#d4a843] px-5 text-sm font-black text-black transition hover:bg-[#efc955]"
                    >
                      Save and Continue
                      <ArrowRight className="h-4 w-4" />
                    </button>
                  ) : (
                    <button
                      type="submit"
                      disabled={isSubmitting}
                      className="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-[#d4a843] px-5 text-sm font-black text-black transition hover:bg-[#efc955]"
                    >
                      <Send className="h-4 w-4" />
                      {isSubmitting ? 'Saving...' : 'Submit'}
                    </button>
                  )}
                </div>
              </div>
            </form>
          )}
        </div>
      </section>
    </main>
  )
}

function CompanyStep({ fields, updateField }: StepProps) {
  return (
    <div className="grid gap-6">
      <SectionTitle title="Licensor Information" />
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="Client Company Name" name="clientCompanyName" value={fields.clientCompanyName} onChange={updateField} required />
        <TextField label="Copyright Line" name="copyrightLine" value={fields.copyrightLine} onChange={updateField} required />
        <TextField label="Main Contact Person" name="mainContactPerson" value={fields.mainContactPerson} onChange={updateField} required />
        <TextField label="Contact Person's Email" name="contactEmail" type="email" value={fields.contactEmail} onChange={updateField} required />
        <TextField label="State of the Company" name="companyState" value={fields.companyState} onChange={updateField} />
      </div>
      <TextareaField label="Client Address" name="clientAddress" value={fields.clientAddress} onChange={updateField} required />

      <div className="rounded-md border border-[#d4a843]/22 bg-[#d4a843]/8 p-4">
        <div className="mb-4 flex items-center gap-2 text-sm font-black uppercase tracking-[0.12em] text-[#f0c96a]">
          <Lock className="h-4 w-4" />
          Protected Banking Section
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label="Banking Information - Name" name="bankingName" value={fields.bankingName} onChange={updateField} secure />
          <TextField label="ABA Number" name="abaNumber" value={fields.abaNumber} onChange={updateField} secure />
          <TextField label="Account Number" name="accountNumber" value={fields.accountNumber} onChange={updateField} secure />
        </div>
        <div className="mt-4">
          <TextareaField label="Banking Information - Address" name="bankingAddress" value={fields.bankingAddress} onChange={updateField} secure />
        </div>
      </div>
    </div>
  )
}

function TitleStep({ fields, updateField }: StepProps) {
  return (
    <div className="grid gap-6">
      <SectionTitle title="Title Information" />
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="Title Name" name="titleName" value={fields.titleName} onChange={updateField} required />
        <TextField label="Release Date" name="releaseDate" type="date" value={fields.releaseDate} onChange={updateField} required />
        <TextField label="Runtime (minutes)" name="runtime" type="number" value={fields.runtime} onChange={updateField} required />
        <SelectField label="Rating" name="rating" value={fields.rating} onChange={updateField} options={['', 'G', 'PG', 'PG-13', 'R', 'NC-17', 'TV-Y', 'TV-G', 'TV-PG', 'TV-14', 'TV-MA', 'Unrated']} required />
        <TextField label="Primary Genre" name="primaryGenre" value={fields.primaryGenre} onChange={updateField} required />
        <TextField label="Secondary Genre" name="secondaryGenre" value={fields.secondaryGenre} onChange={updateField} />
        <TextField label="Director" name="director" value={fields.director} onChange={updateField} required />
      </div>
      <RadioGroup label="Video Definition" name="videoDefinition" value={String(fields.videoDefinition)} options={['HD', 'SD']} onChange={updateField} required />
      <RadioGroup label="Sound Format" name="soundFormat" value={String(fields.soundFormat)} options={['5.1', 'Stereo']} onChange={updateField} required />
      <TextareaField label="Short Description/Synopsis (150 words)" name="synopsis" value={fields.synopsis} onChange={updateField} required />
      <TextareaField label="Logline" name="logline" value={fields.logline} onChange={updateField} required />
    </div>
  )
}

function CreditsStep({ writers, producers, cast, setWriters, setProducers, setCast }: {
  writers: CreditRow[]
  producers: CreditRow[]
  cast: CastRow[]
  setWriters: (rows: CreditRow[]) => void
  setProducers: (rows: CreditRow[]) => void
  setCast: (rows: CastRow[]) => void
}) {
  return (
    <div className="grid gap-7">
      <RepeatableCredit title="Writer(s)" rows={writers} setRows={setWriters} placeholder="Writer name" />
      <RepeatableCredit title="Producer(s)" rows={producers} setRows={setProducers} placeholder="Producer name" />

      <div>
        <SectionTitle title="Cast Information" />
        <div className="grid gap-3">
          {cast.map((row) => (
            <div key={row.id} className="grid gap-3 rounded-md border border-white/10 bg-white/[0.035] p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_44px]">
              <input
                value={row.characterName}
                onChange={(event) => setCast(cast.map((item) => item.id === row.id ? { ...item, characterName: event.target.value } : item))}
                className={textInputClass}
                placeholder="Character name"
              />
              <input
                value={row.creditedName}
                onChange={(event) => setCast(cast.map((item) => item.id === row.id ? { ...item, creditedName: event.target.value } : item))}
                className={textInputClass}
                placeholder="Credited name"
              />
              <RemoveButton disabled={cast.length === 1} onClick={() => setCast(cast.filter((item) => item.id !== row.id))} />
            </div>
          ))}
        </div>
        <AddButton label="Add another cast member" onClick={() => setCast([...cast, { id: newId(), characterName: '', creditedName: '' }])} />
      </div>
    </div>
  )
}

function LinksStep({ fields, updateField }: StepProps) {
  return (
    <div className="grid gap-6">
      <SectionTitle title="Public and Review Links" />
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="IMDb Link" name="imdbLink" type="url" value={fields.imdbLink} onChange={updateField} />
        <TextField label="Trailer Link" name="trailerLink" type="url" value={fields.trailerLink} onChange={updateField} />
        <TextField label="Screener Link" name="screenerLink" type="url" value={fields.screenerLink} onChange={updateField} />
        <TextField label="Optional Screener Password" name="screenerPassword" type="password" value={fields.screenerPassword} onChange={updateField} />
        <TextField label="Artwork Link - Not Final" name="artworkLinkNotFinal" type="url" value={fields.artworkLinkNotFinal} onChange={updateField} />
      </div>

      <SectionTitle title="Delivery Method" />
      <RadioGroup
        label="Select Delivery Method"
        name="deliveryMethod"
        value={String(fields.deliveryMethod)}
        options={[
          { value: 'cloud', label: 'Cloud delivery through Dropbox, Google Drive, or another file-transfer service' },
          { value: 'physical', label: 'Physical delivery using a small hard drive' },
        ]}
        onChange={updateField}
        required
      />
      {fields.deliveryMethod === 'cloud' ? (
        <div className="grid gap-4 sm:grid-cols-2">
          <TextField label="Master File/Folder Link" name="masterFolderLink" type="url" value={fields.masterFolderLink} onChange={updateField} />
          <TextField label="Download Password" name="downloadPassword" type="password" value={fields.downloadPassword} onChange={updateField} />
          <TextField label="Link Expiration Date" name="linkExpirationDate" type="date" value={fields.linkExpirationDate} onChange={updateField} />
        </div>
      ) : (
        <div className="grid gap-4 rounded-md border border-white/10 bg-white/[0.04] p-4">
          <div className="text-sm leading-7 text-white/76">
            <strong className="text-white">Tribeca One Distribution</strong>
            <br />
            1061 Countess Lane
            <br />
            Spring Hill, TN 37174
          </div>
          <CheckboxField label="I have labeled the hard drive with the movie title or my name." name="driveLabeledConfirmed" value={fields.driveLabeledConfirmed} onChange={updateField} />
        </div>
      )}
    </div>
  )
}

function FeatureAudioStep({ fields, updateField }: StepProps) {
  return (
    <div className="grid gap-7">
      <div className="rounded-md border border-[#d4a843]/22 bg-[#d4a843]/8 p-4 text-sm leading-7 text-white/74">
        Preferred master: ProRes HQ with final audio mix attached, no bars, no 2-pop, no countdown, no unnecessary material, and checked audio/picture sync. High-bitrate MP4 should be at least 20 Mbps when ProRes cannot be supplied.
      </div>

      <SectionTitle title="Feature Film Master" />
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="Master Delivery Link" name="masterDeliveryLink" type="url" value={fields.masterDeliveryLink} onChange={updateField} />
        <SelectField label="Master File Type" name="masterFileType" value={fields.masterFileType} onChange={updateField} options={['Apple ProRes 422 HQ', 'Uncompressed QuickTime', 'High-bitrate MP4', 'Digital Betacam', 'HD Cam', 'HD Cam SR', 'D5']} />
        <SelectField label="Resolution" name="resolution" value={fields.resolution} onChange={updateField} options={['1920 x 1080', '720 x 480', 'Other']} />
        <TextField label="Bitrate (Mbps)" name="bitrate" type="number" value={fields.bitrate} onChange={updateField} />
        <TextField label="Runtime Confirmation (minutes)" name="runtimeConfirmation" type="number" value={fields.runtimeConfirmation} onChange={updateField} />
      </div>
      <RadioGroup label="Master Includes Final Mix" name="finalMixIncluded" value={String(fields.finalMixIncluded)} options={[{ value: 'yes', label: 'Yes' }, { value: 'no', label: 'No' }]} onChange={updateField} />
      <CheckboxField label="Audio and picture synchronization check completed." name="syncCheckCompleted" value={fields.syncCheckCompleted} onChange={updateField} required />
      <RadioGroup label="Contains Bars, 2-Pop, or Countdown" name="containsBars" value={String(fields.containsBars)} options={[{ value: 'no', label: 'No' }, { value: 'yes', label: 'Yes' }]} onChange={updateField} />
      <TextareaField label="Additional Technical Notes" name="technicalNotes" value={fields.technicalNotes} onChange={updateField} />

      <SectionTitle title="Audio Specifications" />
      <div className="grid gap-4 sm:grid-cols-2">
        <SelectField label="Audio File Type" name="audioFileType" value={fields.audioFileType} onChange={updateField} options={['WAV', 'MOV', 'Attached to Master']} />
        <TextField label="Audio Delivery Link" name="audioDeliveryLink" type="url" value={fields.audioDeliveryLink} onChange={updateField} />
      </div>
      <RadioGroup label="Audio Mix" name="audioMix" value={String(fields.audioMix)} options={['Stereo', '5.1', 'Both']} onChange={updateField} />
      <CheckboxField label="Audio configuration confirmed: 5.1 uses L, R, C, LFE, Ls, Rs, Stereo L, Stereo R; 2.0 uses Stereo L and Stereo R." name="audioConfigurationConfirmed" value={fields.audioConfigurationConfirmed} onChange={updateField} required />
      <CheckboxField label="PCM Little-Endian confirmed." name="pcmLittleEndianConfirmed" value={fields.pcmLittleEndianConfirmed} onChange={updateField} required />
      <RadioGroup label="Individual WAV Tracks Supplied" name="individualWavTracks" value={String(fields.individualWavTracks)} options={[{ value: 'no', label: 'No' }, { value: 'yes', label: 'Yes' }]} onChange={updateField} />
    </div>
  )
}

function CaptionsStep({ fields, languages, updateField, setLanguages }: StepProps & { languages: LanguageRow[]; setLanguages: (rows: LanguageRow[]) => void }) {
  const showSccNdf = fields.captionsAvailable === 'yes' && languages.some((row) => row.fileFormat === '.SCC')

  return (
    <div className="grid gap-6">
      <RadioGroup label="Are captions or subtitles currently available?" name="captionsAvailable" value={String(fields.captionsAvailable)} options={[{ value: 'yes', label: 'Yes' }, { value: 'no', label: 'No' }]} onChange={updateField} />

      {fields.captionsAvailable === 'yes' ? (
        <>
          <RadioGroup label="Asset Type" name="captionAssetType" value={String(fields.captionAssetType)} options={['Closed captions', 'Subtitles', 'Both']} onChange={updateField} />
          <div>
            <SectionTitle title="Languages and Formats" />
            <div className="grid gap-3">
              {languages.map((row) => (
                <div key={row.id} className="grid gap-3 rounded-md border border-white/10 bg-white/[0.035] p-3 sm:grid-cols-[minmax(0,1fr)_220px_44px]">
                  <input
                    value={row.language}
                    onChange={(event) => setLanguages(languages.map((item) => item.id === row.id ? { ...item, language: event.target.value } : item))}
                    className={textInputClass}
                    placeholder="Language"
                  />
                  <select
                    value={row.fileFormat}
                    onChange={(event) => setLanguages(languages.map((item) => item.id === row.id ? { ...item, fileFormat: event.target.value } : item))}
                    className={selectClass}
                  >
                    {['.SRT', '.SCC', '.CAP', '.ITT', '.STL', '.TXT', 'LambdaCap - Japanese only'].map((format) => <option key={format}>{format}</option>)}
                  </select>
                  <RemoveButton disabled={languages.length === 1} onClick={() => setLanguages(languages.filter((item) => item.id !== row.id))} />
                </div>
              ))}
            </div>
            <AddButton label="Add another language" onClick={() => setLanguages([...languages, { id: newId(), language: '', fileFormat: '.SRT' }])} />
          </div>
          <TextField label="Caption/Subtitle Delivery Link" name="captionDeliveryLink" type="url" value={fields.captionDeliveryLink} onChange={updateField} />
          {showSccNdf ? <CheckboxField label="Any supplied SCC file is NDF, non-drop frame." name="sccNonDropFrame" value={fields.sccNonDropFrame} onChange={updateField} /> : null}
        </>
      ) : (
        <div className="grid gap-3 rounded-md border border-white/10 bg-white/[0.04] p-4">
          <CheckboxField label="I authorize captions to be ordered from Rev.com if required." name="revAuthorization" value={fields.revAuthorization} onChange={updateField} />
          <CheckboxField label="I acknowledge the stated reimbursement cost of $1.25 per program minute." name="revCostAcknowledged" value={fields.revCostAcknowledged} onChange={updateField} />
        </div>
      )}
    </div>
  )
}

function TrailerStep({ fields, updateField }: StepProps) {
  return (
    <div className="grid gap-6">
      <div className="rounded-md border border-[#d4a843]/22 bg-[#d4a843]/8 p-4 text-sm leading-7 text-white/74">
        Trailer delivery should be a ProRes HQ master with titles only at the end and no website information, URLs, "Coming Soon", or "Now Available" language.
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="Public Trailer Link" name="publicTrailerLink" type="url" value={fields.publicTrailerLink} onChange={updateField} />
        <TextField label="Trailer Master Delivery Link" name="trailerMasterDeliveryLink" type="url" value={fields.trailerMasterDeliveryLink} onChange={updateField} />
        <SelectField label="Trailer File Type" name="trailerFileType" value={fields.trailerFileType} onChange={updateField} options={['ProRes HQ', 'Apple ProRes 422 HQ', 'Uncompressed QuickTime', 'High-bitrate MP4']} />
        <TextField label="Trailer Runtime" name="trailerRuntime" value={fields.trailerRuntime} onChange={updateField} />
      </div>
      <CheckboxField label="No URLs or website information." name="trailerNoUrls" value={fields.trailerNoUrls} onChange={updateField} required />
      <CheckboxField label={'No "Coming Soon" or "Now Available" language.'} name="trailerNoAvailabilityLanguage" value={fields.trailerNoAvailabilityLanguage} onChange={updateField} required />
    </div>
  )
}

function ArtworkStep({ fields, updateField }: StepProps) {
  return (
    <div className="grid gap-6">
      <div className="rounded-md border border-[#d4a843]/22 bg-[#d4a843]/8 p-4 text-sm leading-7 text-white/74">
        Final key art should be a layered PSD, 2000 x 3000 pixels, RGB, 2:3, and 72 DPI. Artwork must not contain credit blocks, URLs, pricing references, festival laurels, placeholders, or nudity.
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="Final Poster PSD Link" name="finalPosterPsdLink" type="url" value={fields.finalPosterPsdLink} onChange={updateField} />
        <FileField label="Final Poster Preview (JPG/PNG)" name="finalPosterPreviewName" value={fields.finalPosterPreviewName} onChange={updateField} />
        <RadioGroup label="Landscape Artwork Available" name="landscapeArtworkAvailable" value={String(fields.landscapeArtworkAvailable)} options={[{ value: 'no', label: 'No' }, { value: 'yes', label: 'Yes' }]} onChange={updateField} />
        <SelectField label="Artwork Dimensions" name="artworkDimensions" value={fields.artworkDimensions} onChange={updateField} options={['2000 x 3000', 'Other']} />
      </div>
      {fields.landscapeArtworkAvailable === 'yes' ? <TextField label="Landscape Layered PSD Link" name="landscapePsdLink" type="url" value={fields.landscapePsdLink} onChange={updateField} /> : null}
      <div className="grid gap-3">
        <CheckboxField label="RGB confirmed." name="rgbConfirmed" value={fields.rgbConfirmed} onChange={updateField} required />
        <CheckboxField label="2:3 aspect ratio confirmed." name="aspectRatioConfirmed" value={fields.aspectRatioConfirmed} onChange={updateField} required />
        <CheckboxField label="Layered PSD confirmed." name="layeredPsdConfirmed" value={fields.layeredPsdConfirmed} onChange={updateField} required />
        <CheckboxField label="Artwork restrictions confirmed." name="artworkRestrictionsConfirmed" value={fields.artworkRestrictionsConfirmed} onChange={updateField} required />
      </div>
    </div>
  )
}

function FinalReviewStep({ fields, updateField, writers, producers, cast, languages }: StepProps & {
  writers: CreditRow[]
  producers: CreditRow[]
  cast: CastRow[]
  languages: LanguageRow[]
}) {
  return (
    <div className="grid gap-7">
      <ReviewSummary fields={fields} writers={writers} producers={producers} cast={cast} languages={languages} />
      <SectionTitle title="Submission Agreement" />
      <div className="grid gap-3">
        <CheckboxField label="I have reviewed the company and banking information." name="reviewedCompanyBanking" value={fields.reviewedCompanyBanking} onChange={updateField} required />
        <CheckboxField label="I have verified the title metadata, cast, credits, synopsis, and logline." name="verifiedMetadata" value={fields.verifiedMetadata} onChange={updateField} required />
        <CheckboxField label="I have checked the spelling of all credited names." name="checkedCreditSpelling" value={fields.checkedCreditSpelling} onChange={updateField} required />
        <CheckboxField label="I have verified all public and delivery links." name="verifiedLinks" value={fields.verifiedLinks} onChange={updateField} required />
        <CheckboxField label="I have completed a picture-and-audio synchronization check." name="finalSyncCheck" value={fields.finalSyncCheck} onChange={updateField} required />
        <CheckboxField label="The master does not contain bars, countdowns, or unnecessary delivery material." name="finalMasterClean" value={fields.finalMasterClean} onChange={updateField} required />
        <CheckboxField label="The trailer does not contain URLs or prohibited availability language." name="finalTrailerClean" value={fields.finalTrailerClean} onChange={updateField} required />
        <CheckboxField label="The poster meets the required dimensions and content restrictions." name="finalPosterMeetsRequirements" value={fields.finalPosterMeetsRequirements} onChange={updateField} required />
        <CheckboxField label="I understand that submitted platform information may not be changeable after the title goes live." name="platformChangeAcknowledged" value={fields.platformChangeAcknowledged} onChange={updateField} required />
        <CheckboxField label="I confirm that the submitted information is accurate." name="accurateSubmissionConfirmed" value={fields.accurateSubmissionConfirmed} onChange={updateField} required />
      </div>
    </div>
  )
}

function SubmitStep({ fields, updateField, errors }: StepProps & { errors: string[] }) {
  return (
    <div className="grid gap-6">
      {errors.length > 0 ? <ErrorPanel errors={errors} /> : (
        <div className="rounded-md border border-emerald-400/20 bg-emerald-400/8 p-4 text-sm font-semibold text-emerald-100">
          Required confirmations are complete.
        </div>
      )}
      <div className="grid gap-4 sm:grid-cols-2">
        <TextField label="Submitted By" name="submittedBy" value={fields.submittedBy} onChange={updateField} required />
        <TextField label="Submitter's Title/Role" name="submitterRole" value={fields.submitterRole} onChange={updateField} required />
        <TextField label="Electronic Signature" name="electronicSignature" value={fields.electronicSignature} onChange={updateField} required />
        <TextField label="Submission Date" name="submissionDate" type="date" value={fields.submissionDate} onChange={updateField} />
      </div>
      <TextareaField label="Additional Notes" name="additionalNotes" value={fields.additionalNotes} onChange={updateField} />
    </div>
  )
}

type StepProps = {
  fields: FormState
  updateField: (name: string, value: FieldValue) => void
}

function TextField({ label, name, value, onChange, type = 'text', required = false, secure = false }: {
  label: string
  name: string
  value: FieldValue
  onChange: (name: string, value: FieldValue) => void
  type?: TextFieldType
  required?: boolean
  secure?: boolean
}) {
  return (
    <label className="grid gap-2 text-sm font-bold text-white/74">
      <span>{label}{required ? <span className="text-[#d4a843]"> *</span> : null}{secure ? <ShieldCheck className="ml-2 inline h-4 w-4 text-[#f0c96a]" /> : null}</span>
      <input
        name={name}
        type={secure ? 'password' : type}
        value={String(value ?? '')}
        required={required}
        onChange={(event) => onChange(name, event.target.value)}
        className={textInputClass}
      />
    </label>
  )
}

function TextareaField({ label, name, value, onChange, required = false, secure = false }: {
  label: string
  name: string
  value: FieldValue
  onChange: (name: string, value: FieldValue) => void
  required?: boolean
  secure?: boolean
}) {
  return (
    <label className="grid gap-2 text-sm font-bold text-white/74">
      <span>{label}{required ? <span className="text-[#d4a843]"> *</span> : null}{secure ? <ShieldCheck className="ml-2 inline h-4 w-4 text-[#f0c96a]" /> : null}</span>
      <textarea
        name={name}
        value={String(value ?? '')}
        required={required}
        onChange={(event) => onChange(name, event.target.value)}
        className={textareaClass}
      />
    </label>
  )
}

function SelectField({ label, name, value, options, onChange, required = false }: {
  label: string
  name: string
  value: FieldValue
  options: string[]
  onChange: (name: string, value: FieldValue) => void
  required?: boolean
}) {
  return (
    <label className="grid gap-2 text-sm font-bold text-white/74">
      <span>{label}{required ? <span className="text-[#d4a843]"> *</span> : null}</span>
      <select name={name} value={String(value ?? '')} required={required} onChange={(event) => onChange(name, event.target.value)} className={selectClass}>
        {options.map((option) => <option key={option} value={option}>{option || 'Select'}</option>)}
      </select>
    </label>
  )
}

function RadioGroup({ label, name, value, options, onChange, required = false }: {
  label: string
  name: string
  value: string
  options: Array<string | { value: string; label: string }>
  onChange: (name: string, value: FieldValue) => void
  required?: boolean
}) {
  return (
    <fieldset className="grid gap-3">
      <legend className="text-sm font-bold text-white/74">{label}{required ? <span className="text-[#d4a843]"> *</span> : null}</legend>
      <div className="grid gap-2 sm:grid-cols-2">
        {options.map((option) => {
          const item = typeof option === 'string' ? { value: option, label: option } : option
          return (
            <label key={item.value} className={['flex min-h-12 items-center gap-3 rounded-md border px-3 text-sm font-black transition', value === item.value ? 'border-[#d4a843]/60 bg-[#d4a843]/12 text-[#f0c96a]' : 'border-white/10 bg-white/[0.035] text-white/68'].join(' ')}>
              <input
                type="radio"
                name={name}
                value={item.value}
                checked={value === item.value}
                required={required}
                onChange={() => onChange(name, item.value)}
                className="h-4 w-4 accent-[#d4a843]"
              />
              <span>{item.label}</span>
            </label>
          )
        })}
      </div>
    </fieldset>
  )
}

function CheckboxField({ label, name, value, onChange, required = false }: {
  label: string
  name: string
  value: FieldValue
  onChange: (name: string, value: FieldValue) => void
  required?: boolean
}) {
  return (
    <label className="flex min-h-12 items-start gap-3 rounded-md border border-white/10 bg-white/[0.035] p-3 text-sm font-semibold leading-6 text-white/74">
      <input
        type="checkbox"
        name={name}
        checked={Boolean(value)}
        required={required}
        onChange={(event) => onChange(name, event.target.checked)}
        className="mt-1 h-4 w-4 shrink-0 accent-[#d4a843]"
      />
      <span>{label}{required ? <span className="text-[#d4a843]"> *</span> : null}</span>
    </label>
  )
}

function FileField({ label, name, value, onChange }: {
  label: string
  name: string
  value: FieldValue
  onChange: (name: string, value: FieldValue) => void
}) {
  return (
    <label className="grid gap-2 text-sm font-bold text-white/74">
      <span>{label}</span>
      <span className="flex min-h-11 items-center gap-3 rounded-md border border-white/10 bg-white/[0.055] px-3 text-sm font-semibold text-white/74">
        <Upload className="h-4 w-4 shrink-0 text-[#d4a843]" />
        <span className="min-w-0 flex-1 truncate">{String(value || 'Choose preview file')}</span>
        <input
          type="file"
          accept=".jpg,.jpeg,.png,image/jpeg,image/png"
          className="sr-only"
          onChange={(event) => onChange(name, event.target.files?.[0]?.name ?? '')}
        />
      </span>
    </label>
  )
}

function RepeatableCredit({ title, rows, setRows, placeholder }: {
  title: string
  rows: CreditRow[]
  setRows: (rows: CreditRow[]) => void
  placeholder: string
}) {
  return (
    <div>
      <SectionTitle title={title} />
      <div className="grid gap-3">
        {rows.map((row) => (
          <div key={row.id} className="grid gap-3 rounded-md border border-white/10 bg-white/[0.035] p-3 sm:grid-cols-[minmax(0,1fr)_44px]">
            <input
              value={row.name}
              onChange={(event) => setRows(rows.map((item) => item.id === row.id ? { ...item, name: event.target.value } : item))}
              className={textInputClass}
              placeholder={placeholder}
            />
            <RemoveButton disabled={rows.length === 1} onClick={() => setRows(rows.filter((item) => item.id !== row.id))} />
          </div>
        ))}
      </div>
      <AddButton label={`Add ${title.toLowerCase().replace('(s)', '')}`} onClick={() => setRows([...rows, { id: newId(), name: '' }])} />
    </div>
  )
}

function AddButton({ label, onClick }: { label: string; onClick: () => void }) {
  return (
    <button type="button" onClick={onClick} className="mt-3 inline-flex min-h-10 items-center gap-2 rounded-md border border-[#d4a843]/40 px-3 text-sm font-black text-[#f0c96a] transition hover:bg-[#d4a843]/10">
      <Plus className="h-4 w-4" />
      {label}
    </button>
  )
}

function RemoveButton({ disabled, onClick }: { disabled: boolean; onClick: () => void }) {
  return (
    <button type="button" disabled={disabled} onClick={onClick} aria-label="Remove row" title="Remove row" className="inline-flex h-11 w-11 items-center justify-center rounded-md border border-white/10 text-white/62 transition hover:bg-red-500/12 hover:text-red-100 disabled:opacity-30">
      <Trash2 className="h-4 w-4" />
    </button>
  )
}

function SectionTitle({ title }: { title: string }) {
  return (
    <div className="flex items-center gap-3">
      <h3 className="text-lg font-black uppercase tracking-normal text-white">{title}</h3>
      <div className="h-px flex-1 bg-[linear-gradient(to_right,rgba(212,168,67,0.45),transparent)]" />
    </div>
  )
}

function ErrorPanel({ errors }: { errors: string[] }) {
  return (
    <div className="mb-6 rounded-md border border-red-400/24 bg-red-500/10 p-4 text-sm text-red-100">
      <div className="font-black">Please complete these required items:</div>
      <ul className="mt-2 grid gap-1">
        {errors.map((error) => <li key={error}>- {error}</li>)}
      </ul>
    </div>
  )
}

function ReviewSummary({ fields, writers, producers, cast, languages }: {
  fields: FormState
  writers: CreditRow[]
  producers: CreditRow[]
  cast: CastRow[]
  languages: LanguageRow[]
}) {
  const rows = [
    ['Company', fields.clientCompanyName],
    ['Contact', `${fields.mainContactPerson || 'Not provided'} - ${fields.contactEmail || 'No email'}`],
    ['Banking', fields.bankingName || fields.abaNumber || fields.accountNumber ? 'Captured in protected section' : 'Not provided'],
    ['Title', fields.titleName],
    ['Release', fields.releaseDate],
    ['Runtime', fields.runtime ? `${fields.runtime} minutes` : ''],
    ['Definition / Sound', `${fields.videoDefinition} / ${fields.soundFormat}`],
    ['Genres', [fields.primaryGenre, fields.secondaryGenre].filter(Boolean).join(', ')],
    ['Delivery', fields.deliveryMethod === 'cloud' ? 'Cloud delivery' : 'Physical hard drive'],
    ['Master', `${fields.masterFileType} - ${fields.resolution}`],
    ['Audio', `${fields.audioFileType} - ${fields.audioMix}`],
    ['Captions', fields.captionsAvailable === 'yes' ? `${fields.captionAssetType} (${languages.filter((row) => row.language).length || languages.length} language row)` : 'Not currently available'],
    ['Trailer', fields.publicTrailerLink || fields.trailerMasterDeliveryLink ? 'Trailer links provided' : 'No trailer link provided'],
    ['Artwork', fields.finalPosterPsdLink ? 'Final poster PSD link provided' : 'No final PSD link provided'],
    ['Writers', writers.map((row) => row.name).filter(Boolean).join(', ')],
    ['Producers', producers.map((row) => row.name).filter(Boolean).join(', ')],
    ['Cast Rows', String(cast.filter((row) => row.characterName || row.creditedName).length)],
  ]

  return (
    <div>
      <SectionTitle title="Final Summary" />
      <div className="mt-4 grid gap-3 sm:grid-cols-2">
        {rows.map(([label, value]) => (
          <div key={String(label)} className="rounded-md border border-white/10 bg-white/[0.035] p-4">
            <div className="text-[11px] font-black uppercase tracking-[0.14em] text-[#d4a843]">{label}</div>
            <div className="mt-1 min-h-6 break-words text-sm font-semibold leading-6 text-white/76">{value || 'Not provided'}</div>
          </div>
        ))}
      </div>
    </div>
  )
}

function SubmissionSuccess({ fields, submissionId, onEdit }: { fields: FormState; submissionId: number | null; onEdit: () => void }) {
  return (
    <div className="rounded-md border border-emerald-400/22 bg-[#101010] p-8 text-center shadow-2xl shadow-black/30">
      <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-400/12 text-emerald-200">
        <ClipboardCheck className="h-8 w-8" />
      </div>
      <h2 className="mt-5 text-2xl font-black text-white">Submission Saved</h2>
      <p className="mx-auto mt-3 max-w-2xl text-sm leading-7 text-white/62">
        The Tribeca One Distribution onboarding form for <strong className="text-white">{String(fields.titleName || 'this title')}</strong> has been saved to the database. The email HTML template has been prepared, but no email was sent.
      </p>
      {submissionId ? <div className="mt-4 text-sm font-black text-[#f0c96a]">Submission #{submissionId}</div> : null}
      <button type="button" onClick={onEdit} className="mt-6 inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-white/10 px-5 text-sm font-black text-white transition hover:bg-white/[0.07]">
        <ArrowLeft className="h-4 w-4" />
        Back to Review
      </button>
    </div>
  )
}

function getRequiredErrors(fields: FormState, step: number): string[] {
  return (requiredByStep[step] ?? [])
    .filter((name) => {
      const value = fields[name]
      return typeof value === 'boolean' ? value !== true : !String(value ?? '').trim()
    })
    .map((name) => fieldLabels[name] ?? name)
}

function saveDraft(draft: DraftState, setLastSavedAt: (value: string) => void) {
  if (typeof window === 'undefined') return
  window.localStorage.setItem(draftKey, JSON.stringify(draft))
  setLastSavedAt(new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }))
}

function toSubmissionPayload(draft: DraftState) {
  return {
    fields: draft.fields,
    writers: draft.writers.map(({ name }) => ({ name })),
    producers: draft.producers.map(({ name }) => ({ name })),
    cast: draft.cast.map(({ characterName, creditedName }) => ({ characterName, creditedName })),
    languages: draft.languages.map(({ language, fileFormat }) => ({ language, fileFormat })),
  }
}

function submissionErrorMessage(error: unknown) {
  if (error instanceof Error) {
    const payload = (error as Error & { payload?: unknown }).payload

    if (payload && typeof payload === 'object') {
      const message = 'message' in payload && typeof payload.message === 'string' ? payload.message : null
      const errors = 'errors' in payload && payload.errors && typeof payload.errors === 'object'
        ? Object.values(payload.errors).flat().filter((value): value is string => typeof value === 'string')
        : []

      if (errors.length > 0) {
        return errors[0]
      }

      if (message) {
        return message
      }
    }

    return error.message
  }

  return 'The submission could not be saved. Please try again.'
}
