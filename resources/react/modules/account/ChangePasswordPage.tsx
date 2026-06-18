import { FormEvent, useMemo, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Check, Eye, EyeOff, Loader2, LockKeyhole } from 'lucide-react'

import { AppHeader } from '@/components/AppHeader'
import { Button } from '@/components/ui/button'
import { logoutAllDevices, updateAccountPassword } from '@/modules/account/accountApi'
import { AccountHero, AccountSidebar, AuthRequired, Notice, readApiError } from '@/modules/account/ProfileDetailsPage'

type PasswordForm = {
  old_password: string
  new_password: string
  new_password_confirmation: string
}

const emptyForm: PasswordForm = {
  old_password: '',
  new_password: '',
  new_password_confirmation: '',
}

export function ChangePasswordPage() {
  const [form, setForm] = useState<PasswordForm>(emptyForm)
  const [visible, setVisible] = useState<Record<keyof PasswordForm, boolean>>({
    old_password: false,
    new_password: false,
    new_password_confirmation: false,
  })
  const [notice, setNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null)

  const passwordIssues = useMemo(() => validatePassword(form.new_password), [form.new_password])
  const confirmationMismatch = Boolean(form.new_password_confirmation && form.new_password !== form.new_password_confirmation)

  const passwordMutation = useMutation({
    mutationFn: updateAccountPassword,
    onSuccess: async (response) => {
      if (!response.success) {
        setNotice({ tone: 'error', text: response.message || firstError(response.errors) || 'Password could not be updated.' })
        return
      }

      setNotice({ tone: 'success', text: 'Password updated successfully. Signing you out now.' })
      setForm(emptyForm)

      try {
        await logoutAllDevices()
      } catch {
        // The password change succeeded; redirect even if session cleanup reports an error.
      }

      window.setTimeout(() => {
        window.location.href = '/login'
      }, 700)
    },
    onError: (error) => {
      setNotice({ tone: 'error', text: readApiError(error, 'Password could not be updated.') })
    },
  })

  if (!isAuthenticated()) {
    return <AuthRequired title="Change Password" />
  }

  function submitForm(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!form.old_password || !form.new_password || !form.new_password_confirmation) {
      setNotice({ tone: 'error', text: 'All password fields are required.' })
      return
    }

    if (passwordIssues.length > 0) {
      setNotice({ tone: 'error', text: passwordIssues.join(', ') })
      return
    }

    if (form.old_password === form.new_password) {
      setNotice({ tone: 'error', text: 'New password must be different from your old password.' })
      return
    }

    if (confirmationMismatch) {
      setNotice({ tone: 'error', text: 'New password and confirmation do not match.' })
      return
    }

    passwordMutation.mutate(form)
  }

  return (
    <main className="min-h-screen bg-[#050505] text-white">
      <AppHeader active="home" />
      <AccountHero title="Change Password" description="Update your password and sign out all active devices for a fresh login." actionLabel="Account Settings" actionHref="/account-setting" />

      <section className="px-4 py-10 sm:px-8 lg:px-12">
        <div className="mx-auto grid max-w-[1500px] gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
          <AccountSidebar activeHref="/change-password" />
          <div className="min-w-0">
            {notice ? <Notice tone={notice.tone} text={notice.text} onClose={() => setNotice(null)} /> : null}

            <form onSubmit={submitForm} className="max-w-[760px] rounded-md border border-white/10 bg-white/[0.035] p-5">
              <div className="mb-6 flex gap-4">
                <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-[#d4a843]/35 bg-[#d4a843]/14 text-[#edc342]">
                  <LockKeyhole className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="text-2xl font-black">Update Password</h2>
                  <p className="mt-2 text-sm leading-6 text-white/54">Use 8-12 characters with uppercase, lowercase, number, and special character.</p>
                </div>
              </div>

              <div className="grid gap-4">
                <PasswordField
                  label="Old Password"
                  value={form.old_password}
                  visible={visible.old_password}
                  onToggle={() => setVisible({ ...visible, old_password: !visible.old_password })}
                  onChange={(value) => setForm({ ...form, old_password: value })}
                />
                <PasswordField
                  label="New Password"
                  value={form.new_password}
                  visible={visible.new_password}
                  onToggle={() => setVisible({ ...visible, new_password: !visible.new_password })}
                  onChange={(value) => setForm({ ...form, new_password: value })}
                />
                {form.new_password && passwordIssues.length > 0 ? (
                  <div className="rounded-md border border-[#d4a843]/24 bg-[#d4a843]/10 px-4 py-3 text-sm font-semibold text-[#f3dc86]">
                    {passwordIssues.join(', ')}
                  </div>
                ) : null}
                <PasswordField
                  label="Confirm Password"
                  value={form.new_password_confirmation}
                  visible={visible.new_password_confirmation}
                  onToggle={() => setVisible({ ...visible, new_password_confirmation: !visible.new_password_confirmation })}
                  onChange={(value) => setForm({ ...form, new_password_confirmation: value })}
                />
                {confirmationMismatch ? (
                  <div className="rounded-md border border-red-400/20 bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-100">
                    Passwords do not match.
                  </div>
                ) : null}
              </div>

              <div className="mt-6 flex justify-end">
                <Button type="submit" disabled={passwordMutation.isPending} className="bg-[#edc342] font-black text-black hover:bg-[#f4ce4d]">
                  {passwordMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Check className="h-4 w-4" />}
                  Update Password
                </Button>
              </div>
            </form>
          </div>
        </div>
      </section>
    </main>
  )
}

function PasswordField({ label, value, visible, onToggle, onChange }: { label: string; value: string; visible: boolean; onToggle: () => void; onChange: (value: string) => void }) {
  return (
    <label className="grid gap-2">
      <span className="text-xs font-black uppercase tracking-[0.18em] text-white/42">{label}</span>
      <span className="grid h-12 grid-cols-[minmax(0,1fr)_44px] overflow-hidden rounded-md border border-white/10 bg-black/20 focus-within:border-[#d4a843]/70">
        <input
          type={visible ? 'text' : 'password'}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          className="h-full min-w-0 bg-transparent px-4 text-sm font-semibold text-white outline-none placeholder:text-white/36"
          placeholder={label}
        />
        <button type="button" onClick={onToggle} className="flex h-full w-11 items-center justify-center text-white/64 transition hover:text-white" aria-label={visible ? 'Hide password' : 'Show password'}>
          {visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
        </button>
      </span>
    </label>
  )
}

function validatePassword(password: string) {
  if (!password) return []

  const issues = []
  if (password.length < 8) issues.push('Minimum 8 characters')
  if (password.length > 12) issues.push('Maximum 12 characters')
  if (!/[A-Z]/.test(password)) issues.push('Add uppercase letter')
  if (!/[a-z]/.test(password)) issues.push('Add lowercase letter')
  if (!/[0-9]/.test(password)) issues.push('Add number')
  if (!/[!@#$%^&*(),.?":{}|<>]/.test(password)) issues.push('Add special character')

  return issues
}

function firstError(errors?: Record<string, string[] | string>) {
  if (!errors) return ''
  const value = Object.values(errors).flat()[0]
  return value ? String(value) : ''
}

function isAuthenticated() {
  return window.isAuthenticated === true && Boolean(window.ezwayAuth)
}
