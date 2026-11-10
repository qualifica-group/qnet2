import { useNavigate } from 'react-router-dom'
import { LogOut, Settings } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { UserAvatar } from '@/components/user-avatar'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useAuth } from '@/features/auth/use-auth'
import type { User } from '@/features/auth/types'

/**
 * Authenticated-user entry point for the top-right of the app header. Shows the
 * display name with the job roles as subtitle, and exposes settings + sign-out
 * through a dropdown.
 */
export function NavUserHeader({ user }: { user: User }) {
  const { t } = useTranslation()
  const { logout } = useAuth()
  const navigate = useNavigate()

  // A person can hold several job roles; the email covers those with none.
  const jobRoles = (user.employment?.job_roles ?? [])
    .map((jobRole) => jobRole.name)
    .sort((first, second) => first.localeCompare(second))
    .join(', ')
  const subtitle = jobRoles || user.email

  const handleLogout = async () => {
    await logout()
    navigate('/login', { replace: true })
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant="ghost"
          className="h-auto gap-2 px-1.5 py-1 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
        >
          <div className="hidden max-w-52 text-right leading-tight sm:grid">
            <span className="truncate text-xs font-medium">{user.name}</span>
            <span className="truncate text-[11px] text-sidebar-foreground/70" title={subtitle}>
              {subtitle}
            </span>
          </div>
          <UserAvatar name={user.name} src={user.avatar_url} />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent className="min-w-56 rounded-lg" align="end" sideOffset={8}>
        <DropdownMenuLabel className="font-normal">
          <div className="grid leading-tight">
            <span className="truncate text-sm font-medium">{user.name}</span>
            <span className="truncate text-xs text-muted-foreground">{user.email}</span>
          </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={() => navigate('/settings')}>
          <Settings />
          {t('navigation.settings')}
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onClick={handleLogout}>
          <LogOut />
          {t('auth.signOut')}
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
