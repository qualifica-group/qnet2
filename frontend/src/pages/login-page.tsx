import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { AuthShell } from '@/features/auth/auth-shell'
import { LoginForm } from '@/features/auth/login-form'
import { useAuth } from '@/features/auth/use-auth'

interface LocationState {
  from?: { pathname: string }
}

export default function LoginPage() {
  const { t } = useTranslation()
  const { isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const redirectTo = (location.state as LocationState | null)?.from?.pathname ?? '/dashboard'

  if (isAuthenticated) {
    return <Navigate to={redirectTo} replace />
  }

  return (
    <AuthShell
      title={t('auth.signInTitle')}
      description={t('auth.signInSubtitle')}
      footer={
        <Link
          to="/forgot-password"
          className="rounded-sm text-muted-foreground underline-offset-4 outline-none hover:text-foreground hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50"
        >
          {t('auth.forgotPasswordLink')}
        </Link>
      }
    >
      <LoginForm onSuccess={() => navigate(redirectTo, { replace: true })} />
    </AuthShell>
  )
}
