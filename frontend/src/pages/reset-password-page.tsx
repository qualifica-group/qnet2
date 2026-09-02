import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { AuthShell } from '@/features/auth/auth-shell'
import { AuthNotice } from '@/features/auth/auth-notice'
import { ResetPasswordForm } from '@/features/auth/reset-password-form'

export default function ResetPasswordPage() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()
  const [done, setDone] = useState(false)

  const token = searchParams.get('token')
  const email = searchParams.get('email')

  return (
    <AuthShell
      title={t('auth.resetPasswordTitle')}
      description={t('auth.resetPasswordSubtitle')}
      footer={
        <Link
          to="/login"
          className="rounded-sm text-muted-foreground underline-offset-4 outline-none hover:text-foreground hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
          {t('auth.backToSignIn')}
        </Link>
      }
    >
      {!token || !email ? (
        <AuthNotice tone="error">{t('auth.resetLinkInvalid')}</AuthNotice>
      ) : done ? (
        <AuthNotice tone="success">{t('auth.passwordResetSuccess')}</AuthNotice>
      ) : (
        <ResetPasswordForm token={token} email={email} onSuccess={() => setDone(true)} />
      )}
    </AuthShell>
  )
}
