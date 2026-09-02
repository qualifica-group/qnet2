import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { AuthShell } from '@/features/auth/auth-shell'
import { AuthNotice } from '@/features/auth/auth-notice'
import { ForgotPasswordForm } from '@/features/auth/forgot-password-form'

export default function ForgotPasswordPage() {
  const { t } = useTranslation()
  const [submitted, setSubmitted] = useState(false)

  return (
    <AuthShell
      title={t('auth.forgotPasswordTitle')}
      description={t('auth.forgotPasswordSubtitle')}
      footer={
        <Link
          to="/login"
          className="rounded-sm text-muted-foreground underline-offset-4 outline-none hover:text-foreground hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
          {t('auth.backToSignIn')}
        </Link>
      }
    >
      {submitted ? (
        <AuthNotice tone="success">{t('auth.resetLinkSent')}</AuthNotice>
      ) : (
        <ForgotPasswordForm onSuccess={() => setSubmitted(true)} />
      )}
    </AuthShell>
  )
}
