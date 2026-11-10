import { Outlet } from 'react-router-dom'
import { ChevronLeft } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { AppSidebar } from '@/components/app-sidebar'
import { NavUserHeader } from '@/components/nav-user-header'
import { ThemeToggle } from '@/components/theme-toggle'
import { NotificationBell } from '@/features/notifications/notification-bell'
import { useNotificationTitle } from '@/features/notifications/use-notification-title'
import { WorkspaceLauncher } from '@/features/workspaces/components/workspace-launcher'
import { FloatingAiAssistant } from '@/features/ai-assistant/components/floating-ai-assistant'
import { HelpButton } from '@/features/help/components/help-button'
import { SupportRequestButton } from '@/features/support-requests/support-request-button'
import { usePresenceHeartbeat } from '@/features/system-health/use-presence-heartbeat'
import { useAuth } from '@/features/auth/use-auth'
import { ImpersonationBanner } from '@/features/auth/impersonation-banner'
import { VersionUpdateBanner } from '@/components/version-update-banner'
import { TopLoadingBar } from '@/components/top-loading-bar'
import { Separator } from '@/components/ui/separator'
import {
  SidebarInset,
  SidebarProvider,
  useSidebar,
} from '@/components/ui/sidebar'
import { BreadcrumbTitleProvider } from '@/routes/breadcrumb-title'
import { UserDetailSheetProvider } from '@/features/users/user-detail-sheet'
import { cn } from '@/lib/utils'
import { OnboardingProvider } from '@/features/onboarding/onboarding-provider'
import { onboardingAnchor } from '@/features/onboarding/steps'

export function AppLayout() {
  const { user } = useAuth()

  // Unread notifications take over the browser tab title while the user is
  // signed in; the hook restores the app name once everything is read.
  useNotificationTitle()

  // Presence ping (spec 0187): keeps the user counted as online while the app is open.
  usePresenceHeartbeat()

  return (
    <BreadcrumbTitleProvider>
      <UserDetailSheetProvider>
        <TopLoadingBar />
        <SidebarProvider>
          <AppSidebar />
          {/* min-w-0: a flex item cannot shrink below its content's intrinsic width, so a wide
              scrollable table (spec 0268) would widen the whole page instead of scrolling in place. */}
          <SidebarInset className="min-w-0">
            <VersionUpdateBanner />
            <ImpersonationBanner />
            <header className="relative flex h-12 shrink-0 items-center gap-2 border-b border-sidebar-border bg-sidebar px-4 text-sidebar-foreground">
              <SidebarSeamToggle />
              {/* ml-6 below md: the seam toggle sits inside the header there (left-3, size-6) and would cover the launcher. */}
              <div className="ml-6 flex empty:hidden md:ml-0" {...onboardingAnchor('workspace-launcher')}>
                <WorkspaceLauncher />
              </div>
              {/* Each icon sits in a tour anchor (spec 0368); `empty:hidden` drops the
                  wrapper of a button that renders nothing, so the tour skips it. */}
              <div className="ml-auto flex items-center gap-0.5">
                <div className="flex empty:hidden" {...onboardingAnchor('support-request')}>
                  <SupportRequestButton />
                </div>
                <div className="flex empty:hidden" {...onboardingAnchor('help')}>
                  <HelpButton />
                </div>
                <div className="flex empty:hidden" {...onboardingAnchor('notifications')}>
                  <NotificationBell />
                </div>
                <div className="flex empty:hidden" {...onboardingAnchor('theme')}>
                  <ThemeToggle />
                </div>
                <Separator
                  orientation="vertical"
                  className="mx-1 bg-sidebar-border data-[orientation=vertical]:h-5"
                />
                {user && (
                  <div className="flex" {...onboardingAnchor('user-menu')}>
                    <NavUserHeader user={user} />
                  </div>
                )}
              </div>
            </header>
            <OnboardingProvider>
              <main className="flex flex-1 flex-col gap-4 p-4">
                <Outlet />
              </main>
              <FloatingAiAssistant />
            </OnboardingProvider>
          </SidebarInset>
        </SidebarProvider>
      </UserDetailSheetProvider>
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
      {...onboardingAnchor('sidebar-toggle')}
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
