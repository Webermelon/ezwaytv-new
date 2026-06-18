import { FormEvent, ReactNode, useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertCircle, Check, Edit3, Loader2, Plus, Trash2, Upload, UserRound, UsersRound, X } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Button } from '@/components/ui/button'
import { ApiError } from '@/lib/api'
import { deleteUserProfile, loadUserProfiles, saveUserProfile, selectUserProfile, type UserProfile } from '@/modules/profile/profileApi'

type ProfileFormState = {
  id?: number
  name: string
  avatar: string
  file: File | null
  previewUrl: string
}

const avatarOptions = [
  '/dummy-images/avatars/icon1.png',
  '/dummy-images/avatars/icon2.png',
  '/dummy-images/avatars/icon3.png',
  '/dummy-images/avatars/icon4.png',
  '/dummy-images/avatars/icon5.png',
  '/dummy-images/avatars/icon6.png',
  '/dummy-images/avatars/icon7.png',
  '/dummy-images/avatars/icon8.png',
  '/storage/avatars/image/icon2.png',
  '/storage/avatars/image/icon4.png',
]

const emptyForm: ProfileFormState = {
  name: '',
  avatar: avatarOptions[0],
  file: null,
  previewUrl: '',
}

export function ManageProfilePage() {
  const queryClient = useQueryClient()
  const [formOpen, setFormOpen] = useState(false)
  const [form, setForm] = useState<ProfileFormState>(emptyForm)
  const [deleteTarget, setDeleteTarget] = useState<UserProfile | null>(null)
  const [notice, setNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null)

  const profilesQuery = useQuery({
    queryKey: ['user-profiles'],
    queryFn: loadUserProfiles,
    enabled: isAuthenticated(),
  })

  const profiles = profilesQuery.data ?? []
  const activeProfile = profiles.find((profile) => toBoolean(profile.is_active))
  const canAddProfile = window.ezwayAuth?.is_subscribe || profiles.length < 1

  const saveMutation = useMutation({
    mutationFn: saveUserProfile,
    onSuccess: (result) => {
      queryClient.setQueryData(['user-profiles'], result.profiles)
      setNotice({ tone: 'success', text: result.message || 'Profile saved.' })
      closeForm()
    },
    onError: (error) => {
      setNotice({ tone: 'error', text: readApiError(error, 'Profile could not be saved.') })
    },
  })

  const deleteMutation = useMutation({
    mutationFn: deleteUserProfile,
    onSuccess: (message) => {
      queryClient.invalidateQueries({ queryKey: ['user-profiles'] })
      setNotice({ tone: 'success', text: message || 'Profile deleted.' })
      setDeleteTarget(null)
    },
    onError: (error) => {
      setNotice({ tone: 'error', text: readApiError(error, 'Profile could not be deleted.') })
      setDeleteTarget(null)
    },
  })

  const selectMutation = useMutation({
    mutationFn: selectUserProfile,
    onSuccess: (result) => {
      queryClient.setQueryData(['user-profiles'], result.profiles)
      const nextActive = result.profiles.find((profile) => toBoolean(profile.is_active))

      if (window.ezwayAuth && nextActive) {
        window.ezwayAuth = {
          ...window.ezwayAuth,
          current_profile: {
            id: nextActive.id,
            name: nextActive.name,
            is_child_profile: toBoolean(nextActive.is_child_profile),
          },
          avatar: nextActive.avatar || window.ezwayAuth.avatar,
        }
      }

      setNotice({ tone: 'success', text: result.message || 'Profile selected.' })
    },
    onError: (error) => {
      setNotice({ tone: 'error', text: readApiError(error, 'Profile could not be selected.') })
    },
  })

  useEffect(() => {
    return () => {
      if (form.previewUrl) {
        URL.revokeObjectURL(form.previewUrl)
      }
    }
  }, [form.previewUrl])

  if (!isAuthenticated()) {
    return (
      <main className="min-h-screen bg-[#050505] text-white">
        <AppHeader active="home" />
        <section className="px-4 py-16 sm:px-8 lg:px-12">
          <div className="mx-auto max-w-[760px] rounded-md border border-white/10 bg-white/[0.035] p-8 text-center">
            <UsersRound className="mx-auto h-10 w-10 text-[#edc342]" />
            <h1 className="mt-4 text-3xl font-black">Manage Profiles</h1>
            <p className="mt-3 text-sm leading-6 text-white/58">Sign in to create, edit, and switch viewing profiles.</p>
            <Button asChild className="mt-6 bg-[#edc342] text-black hover:bg-[#f4ce4d]">
              <a href={`/login?redirect=${encodeURIComponent('/manage-profile')}`}>Sign In</a>
            </Button>
          </div>
        </section>
      </main>
    )
  }

  function openCreateForm() {
    setNotice(null)
    setForm(emptyForm)
    setFormOpen(true)
  }

  function openEditForm(profile: UserProfile) {
    setNotice(null)
    setForm({
      id: profile.id,
      name: profile.name ?? '',
      avatar: profile.avatar || avatarOptions[0],
      file: null,
      previewUrl: '',
    })
    setFormOpen(true)
  }

  function closeForm() {
    if (form.previewUrl) {
      URL.revokeObjectURL(form.previewUrl)
    }

    setForm(emptyForm)
    setFormOpen(false)
  }

  function submitForm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const name = form.name.trim()

    if (!name) {
      setNotice({ tone: 'error', text: 'Enter a profile name.' })
      return
    }

    if (name.length > 12) {
      setNotice({ tone: 'error', text: 'Profile names can be 12 characters or fewer.' })
      return
    }

    saveMutation.mutate({
      id: form.id,
      name,
      avatar: form.file ? undefined : form.avatar,
      file: form.file,
    })
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />

      <section className="border-b border-white/8 bg-[radial-gradient(circle_at_80%_0%,rgba(212,168,67,0.18),transparent_28%),linear-gradient(180deg,#0b0b0b_0%,#050505_100%)] px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1500px]">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <span className="inline-flex items-center gap-2 rounded-full border border-[#d4a843]/28 bg-[#d4a843]/12 px-4 py-2 text-xs font-black uppercase tracking-[0.22em] text-[#edc342]">
                <UsersRound className="h-4 w-4" />
                Manage Profiles
              </span>
              <h1 className="mt-5 text-4xl font-black leading-none sm:text-5xl">Who is watching?</h1>
              <p className="mt-4 max-w-2xl text-sm leading-6 text-white/62 sm:text-base">
                Create viewer profiles and switch the active eZWay TV experience for this device.
              </p>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:min-w-[420px]">
              <SummaryTile label="Profiles" value={`${profiles.length}`} />
              <SummaryTile label="Active" value={activeProfile?.name ?? 'None'} />
            </div>
          </div>
        </div>
      </section>

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto max-w-[1500px]">
          {notice ? <Notice tone={notice.tone} text={notice.text} onClose={() => setNotice(null)} /> : null}

          <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 className="text-2xl font-black">Your Profiles</h2>
              <p className="mt-1 text-sm text-white/52">Each profile keeps its own watch activity and recommendations.</p>
            </div>
            <Button
              type="button"
              onClick={openCreateForm}
              disabled={!canAddProfile}
              className="h-11 bg-[#edc342] px-4 font-black text-black hover:bg-[#f4ce4d]"
            >
              <Plus className="h-4 w-4" />
              Add Profile
            </Button>
          </div>

          {!canAddProfile ? (
            <div className="mb-6 rounded-md border border-[#d4a843]/24 bg-[#d4a843]/10 px-4 py-3 text-sm font-semibold text-[#f3dc86]">
              Upgrade your subscription to add more viewer profiles.
            </div>
          ) : null}

          {profilesQuery.isLoading ? (
            <div className="flex min-h-64 items-center justify-center rounded-md border border-white/10 bg-white/[0.035]">
              <Loader2 className="h-6 w-6 animate-spin text-[#edc342]" />
            </div>
          ) : profilesQuery.isError ? (
            <div className="rounded-md border border-red-400/20 bg-red-500/10 p-8 text-center text-red-100">
              {readApiError(profilesQuery.error, 'Profiles could not be loaded.')}
            </div>
          ) : profiles.length > 0 ? (
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              {profiles.map((profile) => (
                <ProfileCard
                  key={profile.id}
                  profile={profile}
                  profileCount={profiles.length}
                  selecting={selectMutation.isPending}
                  onEdit={() => openEditForm(profile)}
                  onDelete={() => setDeleteTarget(profile)}
                  onSelect={() => selectMutation.mutate(profile.id)}
                />
              ))}
            </div>
          ) : (
            <EmptyProfiles onCreate={openCreateForm} />
          )}
        </div>
      </section>

      {formOpen ? (
        <ProfileFormModal
          form={form}
          saving={saveMutation.isPending}
          onClose={closeForm}
          onSubmit={submitForm}
          onChange={setForm}
        />
      ) : null}

      {deleteTarget ? (
        <DeleteProfileModal
          profile={deleteTarget}
          deleting={deleteMutation.isPending}
          onClose={() => setDeleteTarget(null)}
          onConfirm={() => deleteMutation.mutate(deleteTarget.id)}
        />
      ) : null}
    </main>
  )
}

function ProfileCard({
  profile,
  profileCount,
  selecting,
  onEdit,
  onDelete,
  onSelect,
}: {
  profile: UserProfile
  profileCount: number
  selecting: boolean
  onEdit: () => void
  onDelete: () => void
  onSelect: () => void
}) {
  const isActive = toBoolean(profile.is_active)
  const deleteDisabled = profileCount <= 1

  return (
    <article className={['rounded-md border bg-white/[0.035] p-5 transition', isActive ? 'border-[#d4a843]/54 shadow-[0_0_32px_rgba(212,168,67,0.12)]' : 'border-white/10 hover:border-white/18'].join(' ')}>
      <div className="flex items-start justify-between gap-3">
        <div className="flex min-w-0 items-center gap-4">
          <ProfileAvatar profile={profile} sizeClassName="h-20 w-20" />
          <div className="min-w-0">
            <h3 className="truncate text-xl font-black">{profile.name}</h3>
            <div className="mt-2 flex flex-wrap gap-2">
              {isActive ? (
                <span className="inline-flex items-center gap-1.5 rounded-md border border-emerald-400/25 bg-emerald-500/10 px-2 py-1 text-xs font-bold text-emerald-200">
                  <Check className="h-3.5 w-3.5" />
                  Active
                </span>
              ) : null}
            </div>
          </div>
        </div>
      </div>

      <div className="mt-5 grid grid-cols-[minmax(0,1fr)_44px_44px] gap-2">
        <Button
          type="button"
          onClick={onSelect}
          disabled={isActive || selecting}
          className={isActive ? 'bg-white/[0.08] text-white/62' : 'bg-[#edc342] font-black text-black hover:bg-[#f4ce4d]'}
        >
          {selecting && !isActive ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
          {isActive ? 'Selected' : 'Select'}
        </Button>
        <IconButton label="Edit profile" onClick={onEdit}>
          <Edit3 className="h-4 w-4" />
        </IconButton>
        <IconButton label={deleteDisabled ? 'Keep at least one profile' : 'Delete profile'} onClick={onDelete} disabled={deleteDisabled}>
          <Trash2 className="h-4 w-4" />
        </IconButton>
      </div>
    </article>
  )
}

function ProfileFormModal({
  form,
  saving,
  onClose,
  onSubmit,
  onChange,
}: {
  form: ProfileFormState
  saving: boolean
  onClose: () => void
  onSubmit: (event: FormEvent<HTMLFormElement>) => void
  onChange: (next: ProfileFormState) => void
}) {
  const selectedImage = form.previewUrl || form.avatar
  const remaining = Math.max(0, 12 - form.name.length)

  function setFile(file: File | null) {
    if (form.previewUrl) {
      URL.revokeObjectURL(form.previewUrl)
    }

    onChange({
      ...form,
      file,
      previewUrl: file ? URL.createObjectURL(file) : '',
    })
  }

  return (
    <ModalShell title={form.id ? 'Edit Profile' : 'Add Profile'} onClose={onClose}>
      <form onSubmit={onSubmit} className="grid gap-5">
        <div className="flex items-center gap-4">
          <span className="flex h-20 w-20 shrink-0 overflow-hidden rounded-full border border-[#d4a843]/35 bg-[#d4a843]/12">
            {selectedImage ? <img src={selectedImage} alt="" className="h-full w-full object-cover" /> : null}
          </span>
          <label className="inline-flex h-10 cursor-pointer items-center justify-center gap-2 rounded-md border border-white/10 bg-white/[0.06] px-4 text-sm font-bold text-white transition hover:bg-white/[0.1]">
            <Upload className="h-4 w-4" />
            Upload
            <input
              type="file"
              accept="image/*"
              className="sr-only"
              onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
          </label>
        </div>

        <label className="grid gap-2">
          <span className="text-xs font-black uppercase tracking-[0.18em] text-white/42">Profile Name</span>
          <input
            value={form.name}
            maxLength={12}
            onChange={(event) => onChange({ ...form, name: event.target.value })}
            className="h-12 rounded-md border border-white/10 bg-white/[0.055] px-4 text-base font-bold text-white outline-none transition placeholder:text-white/36 focus:border-[#d4a843]/70"
            placeholder="Name"
          />
          <span className="text-xs font-semibold text-white/42">{remaining} characters left</span>
        </label>

        <fieldset className="grid gap-3">
          <legend className="text-xs font-black uppercase tracking-[0.18em] text-white/42">Choose Avatar</legend>
          <div className="grid grid-cols-5 gap-2 sm:grid-cols-10">
            {avatarOptions.map((avatar) => (
              <button
                key={avatar}
                type="button"
                onClick={() => {
                  if (form.previewUrl) {
                    URL.revokeObjectURL(form.previewUrl)
                  }

                  onChange({ ...form, avatar, file: null, previewUrl: '' })
                }}
                className={['aspect-square overflow-hidden rounded-full border bg-white/[0.04] p-0.5 transition', !form.file && form.avatar === avatar ? 'border-[#edc342]' : 'border-white/10 hover:border-white/28'].join(' ')}
                aria-label="Choose avatar"
              >
                <img src={avatar} alt="" className="h-full w-full rounded-full object-cover" />
              </button>
            ))}
          </div>
        </fieldset>

        <div className="grid gap-2 sm:grid-cols-2">
          <Button type="button" variant="outline" onClick={onClose} className="border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08]">
            Cancel
          </Button>
          <Button type="submit" disabled={saving} className="bg-[#edc342] font-black text-black hover:bg-[#f4ce4d]">
            {saving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
            Save Profile
          </Button>
        </div>
      </form>
    </ModalShell>
  )
}

function DeleteProfileModal({
  profile,
  deleting,
  onClose,
  onConfirm,
}: {
  profile: UserProfile
  deleting: boolean
  onClose: () => void
  onConfirm: () => void
}) {
  return (
    <ModalShell title="Delete Profile" onClose={onClose}>
      <div className="grid gap-5">
        <div className="flex items-center gap-4 rounded-md border border-red-400/20 bg-red-500/10 p-4">
          <AlertCircle className="h-6 w-6 shrink-0 text-red-200" />
          <p className="text-sm leading-6 text-red-50">Delete {profile.name}? This removes the profile from this account.</p>
        </div>
        <div className="grid gap-2 sm:grid-cols-2">
          <Button type="button" variant="outline" onClick={onClose} className="border-white/10 bg-white/[0.04] text-white hover:bg-white/[0.08]">
            Cancel
          </Button>
          <Button type="button" onClick={onConfirm} disabled={deleting} className="bg-red-500 font-black text-white hover:bg-red-400">
            {deleting ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
            Delete
          </Button>
        </div>
      </div>
    </ModalShell>
  )
}

function ModalShell({ title, children, onClose }: { title: string; children: ReactNode; onClose: () => void }) {
  return (
    <div className="fixed inset-0 z-[10000] flex items-center justify-center overflow-y-auto bg-black/72 px-4 py-6 backdrop-blur-sm">
      <div className="w-full max-w-[620px] rounded-md border border-white/10 bg-[#101010] shadow-2xl shadow-black">
        <div className="flex items-center justify-between gap-4 border-b border-white/8 px-5 py-4">
          <h2 className="text-xl font-black">{title}</h2>
          <button
            type="button"
            onClick={onClose}
            className="flex h-10 w-10 items-center justify-center rounded-md border border-white/10 bg-white/[0.05] text-white transition hover:bg-white/[0.1]"
            aria-label="Close"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="p-5">{children}</div>
      </div>
    </div>
  )
}

function EmptyProfiles({ onCreate }: { onCreate: () => void }) {
  return (
    <div className="rounded-md border border-white/10 bg-white/[0.035] p-8 text-center">
      <UserRound className="mx-auto h-10 w-10 text-[#edc342]" />
      <h3 className="mt-4 text-2xl font-black">Create your first profile</h3>
      <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-white/54">Set up a viewer profile to personalize watch activity and recommendations.</p>
      <Button type="button" onClick={onCreate} className="mt-6 bg-[#edc342] font-black text-black hover:bg-[#f4ce4d]">
        <Plus className="h-4 w-4" />
        Add Profile
      </Button>
    </div>
  )
}

function SummaryTile({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-white/10 bg-white/[0.04] px-4 py-3">
      <div className="text-xs font-black uppercase tracking-[0.18em] text-white/36">{label}</div>
      <div className="mt-2 truncate text-xl font-black text-white">{value}</div>
    </div>
  )
}

function Notice({ tone, text, onClose }: { tone: 'success' | 'error'; text: string; onClose: () => void }) {
  const classes = tone === 'success'
    ? 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100'
    : 'border-red-400/20 bg-red-500/10 text-red-100'

  return (
    <div className={`mb-6 flex items-start justify-between gap-3 rounded-md border px-4 py-3 text-sm font-semibold ${classes}`}>
      <span>{text}</span>
      <button type="button" onClick={onClose} className="shrink-0 text-current opacity-70 hover:opacity-100" aria-label="Dismiss message">
        <X className="h-4 w-4" />
      </button>
    </div>
  )
}

function ProfileAvatar({ profile, sizeClassName }: { profile: UserProfile; sizeClassName: string }) {
  const initials = (profile.name || 'EZ')
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()

  return (
    <span className={`${sizeClassName} flex shrink-0 overflow-hidden rounded-full border border-[#d4a843]/35 bg-[#d4a843]/12 text-[#edc342]`}>
      {profile.avatar ? (
        <img src={profile.avatar} alt="" className="h-full w-full object-cover" loading="lazy" />
      ) : (
        <span className="flex h-full w-full items-center justify-center text-lg font-black">{initials}</span>
      )}
    </span>
  )
}

function IconButton({ label, disabled, onClick, children }: { label: string; disabled?: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      disabled={disabled}
      onClick={onClick}
      className="flex h-10 w-11 items-center justify-center rounded-md border border-white/10 bg-white/[0.055] text-white transition hover:bg-white/[0.1] disabled:cursor-not-allowed disabled:opacity-40"
    >
      {children}
    </button>
  )
}

function readApiError(error: unknown, fallback: string) {
  if (error instanceof ApiError) {
    const payload = error.payload as { message?: string; errors?: Record<string, string[] | string> } | null
    const firstError = payload?.errors ? Object.values(payload.errors).flat()[0] : null

    return firstError || payload?.message || fallback
  }

  return error instanceof Error ? error.message : fallback
}

function toBoolean(value: unknown) {
  return value === true || value === 1 || value === '1'
}

function isAuthenticated() {
  return window.isAuthenticated === true && Boolean(window.ezwayAuth)
}
