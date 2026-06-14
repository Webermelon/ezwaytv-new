import type { FormEvent, ReactNode, RefObject } from 'react'
import { useEffect, useRef, useState } from 'react'
import {
  CheckCircle2,
  CreditCard,
  FileImage,
  Film,
  Loader2,
  ReceiptText,
  ShieldCheck,
  Upload,
  X,
} from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Button } from '@/components/ui/button'
import { ApiError, api } from '@/lib/api'

type SubmitState = 'idle' | 'submitting' | 'success' | 'error'
type VerificationState = 'idle' | 'checking' | 'verified' | 'duplicate' | 'error'
type UploadKind = 'poster' | 'video'
type PreviewFile = {
  name: string
  size: string
  url: string
}
type UploadProgress = {
  percent: number
  text: string
  success: boolean
}

const rotationPrice = '$24.99'
const paymentUrl = 'https://ezwaynetwork.com/ezway-tv-music-submission-purchase/'

export function MusicUploadPage() {
  return (
    <main className="min-h-screen bg-[#060606] text-white">
      <AppHeader />
      <section className="border-b border-white/10 bg-[#0d0d0d] px-4 py-8 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-7xl">
          <div className="inline-flex items-center gap-2 text-xs font-black uppercase tracking-[0.12em] text-[#d4a843]">
            <ReceiptText className="h-4 w-4" />
            eZWay Music Submission
          </div>
          <div className="mt-4 flex flex-col justify-between gap-4 md:flex-row md:items-end">
            <div>
              <h1 className="text-3xl font-black leading-tight sm:text-5xl">Upload Your Music Video</h1>
              <p className="mt-3 max-w-2xl text-sm leading-7 text-white/60">
                Complete the payment reference, contact details, poster artwork, and video file for backend review and scheduling.
              </p>
            </div>
            <a href="/music#pricing" className="inline-flex h-10 w-fit items-center justify-center rounded-md border border-white/10 bg-white/[0.06] px-4 text-sm font-bold text-white/72 transition hover:bg-white/[0.1] hover:text-white">
              View Pricing
            </a>
          </div>
        </div>
      </section>
      <MusicSubmissionSection />
    </main>
  )
}

function MusicSubmissionSection() {
  const [state, setState] = useState<SubmitState>('idle')
  const [message, setMessage] = useState('')
  const [submitterEmail, setSubmitterEmail] = useState('')
  const [verificationState, setVerificationState] = useState<VerificationState>('idle')
  const [verificationMessage, setVerificationMessage] = useState('Verify your payment email to unlock the form.')
  const [posterPreview, setPosterPreview] = useState<PreviewFile | null>(null)
  const [videoPreview, setVideoPreview] = useState<PreviewFile | null>(null)
  const [progressVisible, setProgressVisible] = useState(false)
  const [uploadMessage, setUploadMessage] = useState('Upload status will appear here.')
  const [uploadMessageTone, setUploadMessageTone] = useState<'muted' | 'success' | 'error'>('muted')
  const [posterProgress, setPosterProgress] = useState<UploadProgress>({ percent: 0, text: 'Waiting', success: false })
  const [videoProgress, setVideoProgress] = useState<UploadProgress>({ percent: 0, text: 'Waiting', success: false })
  const posterInputRef = useRef<HTMLInputElement | null>(null)
  const videoInputRef = useRef<HTMLInputElement | null>(null)
  const posterUrlRef = useRef<string | null>(null)
  const videoUrlRef = useRef<string | null>(null)
  const isFormUnlocked = verificationState === 'verified'

  useEffect(() => {
    return () => {
      if (posterUrlRef.current) URL.revokeObjectURL(posterUrlRef.current)
      if (videoUrlRef.current) URL.revokeObjectURL(videoUrlRef.current)
    }
  }, [])

  function handlePreviewChange(kind: UploadKind) {
    const input = kind === 'poster' ? posterInputRef.current : videoInputRef.current
    const file = input?.files?.[0]

    if (!file) {
      clearPreview(kind)
      return
    }

    const preview = {
      name: file.name,
      size: formatSize(file.size),
      url: URL.createObjectURL(file),
    }

    if (kind === 'poster') {
      if (posterUrlRef.current) URL.revokeObjectURL(posterUrlRef.current)
      posterUrlRef.current = preview.url
      setPosterPreview(preview)
      setPosterProgress({ percent: 0, text: 'Waiting', success: false })
    } else {
      if (videoUrlRef.current) URL.revokeObjectURL(videoUrlRef.current)
      videoUrlRef.current = preview.url
      setVideoPreview(preview)
      setVideoProgress({ percent: 0, text: 'Waiting', success: false })
    }
  }

  function clearPreview(kind: UploadKind) {
    if (kind === 'poster') {
      if (posterUrlRef.current) URL.revokeObjectURL(posterUrlRef.current)
      posterUrlRef.current = null
      if (posterInputRef.current) posterInputRef.current.value = ''
      setPosterPreview(null)
      setPosterProgress({ percent: 0, text: 'Waiting', success: false })
      return
    }

    if (videoUrlRef.current) URL.revokeObjectURL(videoUrlRef.current)
    videoUrlRef.current = null
    if (videoInputRef.current) videoInputRef.current.value = ''
    setVideoPreview(null)
    setVideoProgress({ percent: 0, text: 'Waiting', success: false })
  }

  function resetVerification({ clearMedia = true }: { clearMedia?: boolean } = {}) {
    setVerificationState('idle')
    setVerificationMessage('Verify your payment email to unlock the form.')
    setMessage('')

    if (clearMedia) {
      clearPreview('poster')
      clearPreview('video')
      setProgressVisible(false)
    }
  }

  async function verifyPayment() {
    const email = submitterEmail.trim()

    if (!email) {
      setVerificationState('error')
      setVerificationMessage('Enter the email used for payment first.')
      return
    }

    setVerificationState('checking')
    setVerificationMessage('Checking FluentForm payment status...')
    setMessage('')

    try {
      const response = await api.post<{ message?: string; verified?: boolean }>('/music/video-submissions/verify', {
        channel_slug: 'ezway-music',
        submitter_email: email,
      })

      setVerificationState('verified')
      setVerificationMessage(response.message || 'Payment email verified and paid. You can upload your submission now.')
    } catch (error) {
      const errorMessage = readSubmitError(error)
      const isDuplicate = error instanceof ApiError && error.status === 409
      setVerificationState(isDuplicate ? 'duplicate' : 'error')
      setVerificationMessage(isDuplicate ? 'You already uploaded the video for this payment email.' : errorMessage)
      setMessage(isDuplicate ? 'You already uploaded the video.' : errorMessage)
      clearPreview('poster')
      clearPreview('video')
    }
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget

    if (!form.checkValidity()) {
      form.reportValidity()
      return
    }

    if (verificationState !== 'verified') {
      setState('error')
      setMessage('Verify your payment email before uploading media.')
      return
    }

    setState('submitting')
    setMessage('')
    setProgressVisible(true)
    setPosterProgress({ percent: 0, text: 'Starting', success: false })
    setVideoProgress({ percent: 0, text: 'Starting', success: false })
    setUploadMessageTone('muted')
    setUploadMessage('Uploading files to cloud media storage. Keep this page open until both items show success.')

    try {
      const response = await submitWithProgress(form, (poster, video) => {
        setPosterProgress(poster)
        setVideoProgress(video)
      })

      setPosterProgress({ percent: 100, text: 'Success', success: true })
      setVideoProgress({ percent: 100, text: 'Success', success: true })
      setUploadMessageTone('success')
      setUploadMessage(response.message || 'Upload complete. Your submission has been received.')
      setState('success')
      setMessage(response.message || 'Your music video was uploaded. Our team will review it and schedule it for the channel.')

      window.setTimeout(() => {
        window.location.href = response.redirect_url || window.location.href
      }, 1400)
    } catch (error) {
      setState('error')
      const errorMessage = readSubmitError(error)
      setMessage(errorMessage)
      setUploadMessageTone('error')
      setUploadMessage(errorMessage)
    }
  }

  return (
    <section id="music-video-submission" className="px-4 py-8 sm:px-8 sm:py-10 lg:px-12">
      <div className="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[360px_minmax(0,1fr)] lg:items-start">
        <aside className="lg:sticky lg:top-24">
          <div className="rounded-md border border-white/10 bg-[#141414] p-5 shadow-xl shadow-black/20">
            <div className="flex items-start justify-between gap-4 border-b border-white/10 pb-5">
              <div>
                <div className="text-xs font-black uppercase tracking-[0.12em] text-[#d4a843]">Payment Summary</div>
                <div className="mt-2 text-3xl font-black">{rotationPrice}</div>
                <div className="mt-1 text-sm text-white/48">per month</div>
              </div>
              <CreditCard className="h-9 w-9 text-[#d4a843]" />
            </div>
            <Button asChild className="mt-5 h-11 w-full bg-[#d4a843] text-black hover:bg-[#eac45b]">
              <a href={paymentUrl} target="_blank" rel="noreferrer">
                <CreditCard className="h-4 w-4" />
                Make Payment
              </a>
            </Button>

            <div className="mt-5 rounded-md border border-[#d4a843]/20 bg-[#d4a843]/10 p-4">
              <div className="text-sm font-black text-[#f1c95c]">Locked Channel</div>
              <div className="mt-1 text-lg font-black text-white">eZWay Music</div>
              <div className="mt-1 text-xs leading-5 text-white/58">Music video submissions for the eZWay Music streaming channel.</div>
            </div>

            <div className="mt-5 border-t border-white/10 pt-5">
              <div className="text-sm font-black text-white">Payment Verification</div>
              <label className="mt-3 block">
                <span className="text-xs font-bold uppercase tracking-[0.08em] text-white/52">Payment email</span>
                <input
                  type="email"
                  value={submitterEmail}
                  onChange={(event) => {
                    setSubmitterEmail(event.target.value)
                    resetVerification()
                  }}
                  placeholder="Email used for payment"
                  className="mt-2 h-11 w-full rounded-md border border-white/10 bg-black/36 px-3 text-sm text-white outline-none transition placeholder:text-white/36 focus:border-[#d4a843]/70"
                />
              </label>
              <div
                className={[
                  'mt-3 text-xs font-semibold leading-5',
                  verificationState === 'verified'
                    ? 'text-emerald-300'
                    : verificationState === 'duplicate' || verificationState === 'error'
                      ? 'text-red-300'
                      : 'text-white/52',
                ].join(' ')}
              >
                {verificationMessage}
              </div>
              <Button
                type="button"
                disabled={verificationState === 'checking' || !submitterEmail.trim()}
                onClick={verifyPayment}
                className="mt-4 h-10 w-full bg-[#d4a843] text-black hover:bg-[#eac45b]"
              >
                {verificationState === 'checking' ? <Loader2 className="h-4 w-4 animate-spin" /> : <CheckCircle2 className="h-4 w-4" />}
                Verify Payment
              </Button>
            </div>

            <div className="mt-5 grid gap-3 text-sm text-white/68">
              {['Payment email is required', 'Poster image and video file are required', 'Accepted videos: MP4, MOV, M4V and WEBM'].map((item) => (
                <span key={item} className="flex gap-2">
                  <ShieldCheck className="mt-0.5 h-4 w-4 shrink-0 text-[#d4a843]" />
                  {item}
                </span>
              ))}
            </div>
          </div>
        </aside>

        <form action="/music/video-submissions" method="POST" encType="multipart/form-data" onSubmit={handleSubmit} className="rounded-md border border-white/10 bg-[#141414] shadow-2xl shadow-black/35">
          <input type="hidden" name="channel_slug" value="ezway-music" />
          <div className="border-b border-white/10 px-5 py-5 sm:px-7">
            <h2 className="text-xl font-black">Submission Details</h2>
            <p className="mt-1 text-sm text-white/50">Fields marked with an asterisk are required.</p>
          </div>

          <div className="relative">
            <div className={['grid gap-7 p-5 sm:p-7', isFormUnlocked ? '' : 'pointer-events-none select-none blur-[3px] opacity-45'].join(' ')}>
              <FormSection number="01" title="Video Information">
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="Music video title" name="title" required disabled={!isFormUnlocked} />
                  <Field label="Artist name" name="artist_name" disabled={!isFormUnlocked} />
                </div>
                <label className="mt-4 block">
                  <span className="text-sm font-bold text-white/76">Notes</span>
                  <textarea disabled={!isFormUnlocked} name="notes" rows={4} className="mt-2 w-full rounded-md border border-white/10 bg-black/36 px-3 py-3 text-sm text-white outline-none transition placeholder:text-white/36 focus:border-[#d4a843]/70 disabled:cursor-not-allowed disabled:opacity-50" placeholder="Genre, release details, social links or scheduling notes" />
                </label>
              </FormSection>

              <FormSection number="02" title="Submitter Contact">
                <div className="grid gap-4 sm:grid-cols-3">
                  <Field label="Your name" name="submitter_name" disabled={!isFormUnlocked} />
                  <Field
                    label="Email"
                    name="submitter_email"
                    type="email"
                    value={submitterEmail}
                    onChange={(value) => {
                      setSubmitterEmail(value)
                      resetVerification()
                    }}
                    disabled={!isFormUnlocked}
                  />
                  <Field label="Phone" name="submitter_phone" disabled={!isFormUnlocked} />
                </div>
              </FormSection>

              <input type="hidden" name="purchase_reference" value={submitterEmail} />
              <input type="hidden" name="purchase_confirmation" value={isFormUnlocked ? '1' : ''} />

              <FormSection number="03" title="Media Files">
                <div className="grid gap-4 sm:grid-cols-2">
                    <FileField
                      label="Poster image"
                      name="poster"
                      icon={FileImage}
                      accept="image/jpeg,image/png,image/webp"
                      inputRef={posterInputRef}
                      preview={posterPreview}
                      onChange={() => handlePreviewChange('poster')}
                      onClear={() => clearPreview('poster')}
                      disabled={!isFormUnlocked}
                    />
                    <FileField
                      label="Video file"
                      name="video"
                      icon={Film}
                      accept="video/mp4,video/quicktime,video/webm"
                      inputRef={videoInputRef}
                      preview={videoPreview}
                      onChange={() => handlePreviewChange('video')}
                      onClear={() => clearPreview('video')}
                      disabled={!isFormUnlocked}
                      video
                    />
                  </div>
              </FormSection>

              {message ? (
                <div className={['rounded-md border px-4 py-3 text-sm font-semibold', state === 'success' ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-200' : 'border-red-400/30 bg-red-400/10 text-red-200'].join(' ')}>
                  {message}
                </div>
              ) : null}

              {progressVisible ? (
                <div className="rounded-md border border-white/10 bg-black/28 p-4">
                  <UploadStatusRow label="Poster Image" progress={posterProgress} />
                  <UploadStatusRow label="Video File" progress={videoProgress} />
                  <div
                    className={[
                      'mt-3 text-sm',
                      uploadMessageTone === 'success' ? 'text-emerald-300' : uploadMessageTone === 'error' ? 'text-red-300' : 'text-white/56',
                    ].join(' ')}
                  >
                    {uploadMessage}
                  </div>
                </div>
              ) : null}
            </div>

            {!isFormUnlocked ? (
              <div className="absolute inset-0 flex items-center justify-center rounded-b-md border-t border-dashed border-[#d4a843]/28 bg-black/46 p-4 text-center backdrop-blur-[1px]">
                <div>
                  <ShieldCheck className="mx-auto h-9 w-9 text-[#d4a843]" />
                  <div className="mt-3 text-sm font-black text-white">Submission form is locked</div>
                  <div className="mt-1 max-w-sm text-xs leading-5 text-white/58">
                    Verify the email used for payment in the payment summary panel to unlock every field.
                  </div>
                </div>
              </div>
            ) : null}
          </div>

          <div className="flex flex-col gap-3 border-t border-white/10 bg-black/20 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-7">
            <div className="text-sm text-white/50">
              Keep this page open while your poster and video upload.
            </div>
            <Button type="submit" disabled={state === 'submitting' || verificationState !== 'verified'} className="h-12 w-full bg-[#d4a843] px-6 text-black hover:bg-[#eac45b] sm:w-auto">
              {state === 'submitting' ? <Loader2 className="h-5 w-5 animate-spin" /> : <Upload className="h-5 w-5" />}
              {state === 'submitting' ? 'Uploading...' : 'Upload for Review'}
            </Button>
          </div>
        </form>
      </div>
    </section>
  )
}

function FormSection({ number, title, children }: { number: string; title: string; children: ReactNode }) {
  return (
    <section className="border-b border-white/10 pb-7 last:border-b-0 last:pb-0">
      <div className="mb-4 flex items-center gap-3">
        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-[#d4a843] text-xs font-black text-black">{number}</span>
        <h3 className="text-lg font-black text-white">{title}</h3>
      </div>
      {children}
    </section>
  )
}

function Field({
  label,
  name,
  type = 'text',
  required = false,
  placeholder,
  value,
  onChange,
}: {
  label: string
  name: string
  type?: string
  required?: boolean
  placeholder?: string
  value?: string
  onChange?: (value: string) => void
}) {
  return (
    <label className="block">
      <span className="text-sm font-bold text-white/76">{label}{required ? <span className="text-[#d4a843]"> *</span> : null}</span>
      <input
        name={name}
        type={type}
        required={required}
        placeholder={placeholder}
        value={value}
        onChange={onChange ? (event) => onChange(event.target.value) : undefined}
        className="mt-2 h-11 w-full rounded-md border border-white/10 bg-black/36 px-3 text-sm text-white outline-none transition placeholder:text-white/36 focus:border-[#d4a843]/70"
      />
    </label>
  )
}

function FileField({
  label,
  name,
  icon: Icon,
  accept,
  inputRef,
  preview,
  onChange,
  onClear,
  disabled = false,
  video = false,
}: {
  label: string
  name: string
  icon: typeof FileImage
  accept: string
  inputRef: RefObject<HTMLInputElement | null>
  preview: PreviewFile | null
  onChange: () => void
  onClear: () => void
  disabled?: boolean
  video?: boolean
}) {
  return (
    <div className="block rounded-md border border-dashed border-white/16 bg-black/24 p-4">
      <label className="block">
        <span className="flex items-center gap-2 text-sm font-bold text-white/76">
          <Icon className="h-4 w-4 text-[#d4a843]" />
          {label} <span className="text-[#d4a843]">*</span>
        </span>
        <input ref={inputRef} name={name} type="file" required disabled={disabled} accept={accept} onChange={onChange} className="mt-3 block w-full text-sm text-white/64 file:mr-4 file:rounded-sm file:border-0 file:bg-[#d4a843] file:px-3 file:py-2 file:text-sm file:font-black file:text-black disabled:cursor-not-allowed disabled:opacity-50" />
      </label>
      {preview ? (
        <div className="relative mt-3 overflow-hidden rounded-md border border-[#d4a843]/24 bg-black">
          <button
            type="button"
            onClick={onClear}
            aria-label={`Remove ${label.toLowerCase()}`}
            className="absolute right-2 top-2 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/78 text-white backdrop-blur transition hover:bg-black"
          >
            <X className="h-4 w-4" />
          </button>
          {video ? (
            <video src={preview.url} controls preload="metadata" className="aspect-video w-full bg-black object-contain" />
          ) : (
            <img src={preview.url} alt={`${label} preview`} className="aspect-video w-full bg-black object-contain" />
          )}
          <div className="flex justify-between gap-3 bg-black/92 px-3 py-2 text-xs text-white/56">
            <span className="min-w-0 truncate">{preview.name}</span>
            <span className="shrink-0">{preview.size}</span>
          </div>
        </div>
      ) : null}
    </div>
  )
}

function UploadStatusRow({ label, progress }: { label: string; progress: UploadProgress }) {
  const percent = Math.max(0, Math.min(100, Math.round(progress.percent)))

  return (
    <div className="grid gap-2 py-2 sm:grid-cols-[160px_1fr_92px] sm:items-center sm:gap-3">
      <div className="text-sm font-bold text-white">{label}</div>
      <div className="h-2.5 overflow-hidden rounded-full bg-white/10">
        <div
          className={['h-full rounded-full transition-[width] duration-200', progress.success ? 'bg-emerald-500' : 'bg-[#d4a843]'].join(' ')}
          style={{ width: `${percent}%` }}
        />
      </div>
      <div className={['text-sm sm:text-right', progress.success ? 'font-bold text-emerald-400' : 'text-white/56'].join(' ')}>
        {progress.success ? '✓ ' : ''}{progress.text}
      </div>
    </div>
  )
}

function submitWithProgress(
  form: HTMLFormElement,
  onProgress: (poster: UploadProgress, video: UploadProgress) => void,
) {
  return new Promise<{ message?: string; redirect_url?: string }>((resolve, reject) => {
    const posterInput = form.elements.namedItem('poster') as HTMLInputElement | null
    const videoInput = form.elements.namedItem('video') as HTMLInputElement | null
    const posterBytes = posterInput?.files?.[0]?.size || 0
    const videoBytes = videoInput?.files?.[0]?.size || 0
    const fileBytes = posterBytes + videoBytes
    const xhr = new XMLHttpRequest()
    const formData = new FormData(form)

    xhr.open('POST', form.action || '/music/video-submissions')
    xhr.setRequestHeader('Accept', 'application/json')
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest')

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    if (csrfToken) {
      xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken)
    }

    xhr.upload.addEventListener('progress', (progressEvent) => {
      if (!progressEvent.lengthComputable || !fileBytes) return

      const uploaded = Math.min(progressEvent.loaded, fileBytes)
      const posterUploaded = Math.min(uploaded, posterBytes)
      const videoUploaded = Math.max(0, Math.min(uploaded - posterBytes, videoBytes))
      const posterPercent = posterBytes ? (posterUploaded / posterBytes) * 100 : 0
      const videoPercent = videoBytes ? (videoUploaded / videoBytes) * 100 : 0

      onProgress(
        {
          percent: posterPercent,
          text: posterPercent >= 100 ? 'Processing' : `${Math.round(posterPercent)}%`,
          success: false,
        },
        {
          percent: videoPercent,
          text: videoPercent >= 100 ? 'Processing' : `${Math.round(videoPercent)}%`,
          success: false,
        },
      )
    })

    xhr.addEventListener('load', () => {
      const response = parseXhrResponse(xhr.responseText)

      if (xhr.status >= 200 && xhr.status < 300) {
        resolve(response)
        return
      }

      reject(new ApiError(xhr.statusText || 'Upload failed', xhr.status, response))
    })

    xhr.addEventListener('error', () => {
      reject(new Error('Network error during upload. Please try again.'))
    })

    xhr.send(formData)
  })
}

function parseXhrResponse(text: string) {
  try {
    return JSON.parse(text || '{}') as { message?: string; redirect_url?: string; errors?: Record<string, string[]> }
  } catch {
    return {}
  }
}

function formatSize(bytes: number) {
  if (!bytes) return ''
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unit = 0

  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024
    unit += 1
  }

  return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
}

function readSubmitError(error: unknown) {
  if (error instanceof ApiError) {
    const payload = error.payload as { message?: string; errors?: Record<string, string[]> } | null
    const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null
    return firstError ?? payload?.message ?? 'The upload could not be submitted. Please check the form and try again.'
  }

  if (error instanceof Error) {
    return error.message
  }

  return 'The upload could not be submitted. Please try again.'
}
