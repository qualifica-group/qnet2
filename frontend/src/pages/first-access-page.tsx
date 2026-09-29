import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { AuthShell } from '@/features/auth/auth-shell'
import { PasswordForm } from '@/features/auth/password-form'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'

/** Forced password change shown before anything else while `must_set_password` is set (spec 0177). */
export default function FirstAccessPage() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { logout } = useAuth()

  // Step 1: refresh `me` so the cleared flag lifts the ProtectedRoute redirect
  // Step 2: enter the app
  const handlePasswordChanged = async () => {
    await queryClient.invalidateQueries({ queryKey: authKeys.me })
    navigate('/dashboard', { replace: true })
  }

  return (
    <AuthShell
      title={t('auth.firstAccessTitle')}
      description={t('auth.firstAccessSubtitle')}
      footer={
        <Button type="button" variant="ghost" size="sm" onClick={() => void logout()}>
          {t('auth.signOut')}
        </Button>
      }
    >
      <PasswordForm onSuccess={() => void handlePasswordChanged()} />
    </AuthShell>
  )
}
