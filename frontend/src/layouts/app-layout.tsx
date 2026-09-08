import { Outlet } from 'react-router-dom'
import { ChevronLeft } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { AppSidebar } from '@/components/app-sidebar'
import { NavUserHeader } from '@/components/nav-user-header'
import { ThemeToggle } from '@/components/theme-toggle'
import { NotificationBell } from '@/features/notifications/notification-bell'
import { useAuth } from '@/features/auth/use-auth'
import { ImpersonationBanner } from '@/features/auth/impersonation-banner'
import { VersionUpdateBanner } from '@/components/version-update-banner'
import { Separator } from '@/components/ui/separator'
import {
  SidebarInset,
  SidebarProvider,
  useSidebar,
} from '@/components/ui/sidebar'
import { BreadcrumbTitleProvider } from '@/routes/breadcrumb-title'
import { cn } from '@/lib/utils'

export function AppLayout() {
  const { user } = useAuth()

  return (
    <BreadcrumbTitleProvider>
      <SidebarProvider>
        <AppSidebar />
        <SidebarInset>
          <VersionUpdateBanner />
          <ImpersonationBanner />
          <header className="relative flex h-12 shrink-0 items-center gap-2 border-b border-sidebar-border bg-sidebar px-4 text-sidebar-foreground">
            <SidebarSeamToggle />
            <div className="ml-auto flex items-center gap-0.5">
              <NotificationBell />
              <ThemeToggle />
              <Separator
                orientation="vertical"
                className="mx-1 bg-sidebar-border data-[orientation=vertical]:h-5"
              />
              {user && <NavUserHeader user={user} />}
            </div>
          </header>
          <main className="flex flex-1 flex-col gap-4 p-4">
            <Outlet />
          </main>
        </SidebarInset>
      </SidebarProvider>
    </BreadcrumbTitleProvider>
  )
}

/**
 * Circular collapse control that straddles the seam between the sidebar and the
 * header. Anchored to the header's left edge (which always tracks the sidebar's
 * right edge, even when collapsed) and lifted above the fixed sidebar via z-20.
 */
function SidebarSeamToggle() {
  const { t } = useTranslation()
  const { toggleSidebar, state } = useSidebar()

  return (
    <button
      type="button"
      onClick={toggleSidebar}
      aria-label={t('navigation.toggleSidebar')}
      className="absolute top-1/2 left-3 z-20 flex size-6 -translate-y-1/2 items-center justify-center rounded-full border border-sidebar-border bg-sidebar text-sidebar-foreground shadow-md transition-colors hover:bg-sidebar-accent md:left-0 md:-translate-x-1/2"
    >
      <ChevronLeft
        className={cn(
          'size-3.5 transition-transform duration-200',
          state === 'collapsed' && 'rotate-180',
        )}
      />
      <span className="sr-only">{t('navigation.toggleSidebar')}</span>
    </button>
  )
}
