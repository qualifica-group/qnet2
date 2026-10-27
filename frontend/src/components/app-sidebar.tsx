import { useEffect } from 'react'
import { Bell, Settings } from 'lucide-react'
import { NavLink } from 'react-router-dom'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuBadge,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSkeleton,
  SidebarRail,
} from '@/components/ui/sidebar'
import { NavMain, NAV_BADGE_CLASS, NAV_ITEM_CLASS } from '@/components/nav-main'
import { useNavigation } from '@/features/navigation/use-navigation'
import { useAuth } from '@/features/auth/use-auth'
import { useAbsenceMenuBadge } from '@/features/absences/use-absence-menu-badge'
import { useUnreadBadge } from '@/features/notifications/use-unread-badge'
import { ActiveWorkspaceName } from '@/features/workspaces/components/active-workspace-name'
import { useWorkspaceNav } from '@/features/workspaces/use-workspace-nav'
import { env } from '@/config/env'

const NOTIFICATIONS_ROUTE = '/notifications'
const SETTINGS_ROUTE = '/settings'

export function AppSidebar() {
  const { t } = useTranslation()
  const { link, pathname } = useWorkspaceNav()
  const { logout } = useAuth()
  const navigation = useNavigation()
  const unreadBadge = useUnreadBadge()
  const absencesBadge = useAbsenceMenuBadge()

  // The visible `SidebarMenuBadge` hides itself when the sidebar collapses to
  // icons (design-system default, `group-data-[collapsible=icon]:hidden`) to
  // avoid overlapping the icon — so the count is folded into the tooltip
  // instead, which only renders while collapsed, keeping the badge "readable"
  // through the one surface still showing text.
  const notificationsTooltip =
    unreadBadge.count > 0
      ? `${t('navigation.notifications')} (${unreadBadge.count})`
      : t('navigation.notifications')

  // Navigation is required to use the app. If it cannot be loaded the session
  // can no longer be trusted, so log out and return to /login (ProtectedRoute
  // performs the redirect once the session is cleared). A 404 under an app
  // prefix only means the app is gone or not visible: the route gate is about
  // to redirect away, which must not end the session.
  const isSessionError = navigation.isError && !isNotFound(navigation.error)
  useEffect(() => {
    if (isSessionError) {
      void logout()
    }
  }, [isSessionError, logout])

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader className="border-b border-sidebar-border">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton className="pointer-events-none gap-0">
              <img
                src="/brands/logo_white.svg"
                alt=""
                aria-hidden
                className="size-6 shrink-0 object-contain"
              />
              <div className="grid flex-1 text-left leading-tight">
                <ActiveWorkspaceName />
                <span className="truncate text-sm font-semibold">{env.appNameSidebar}</span>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {(navigation.isPending || navigation.isError) && (
          <SidebarGroup>
            <NavSkeleton />
          </SidebarGroup>
        )}

        {navigation.data && <NavMain items={navigation.data} badges={{ absences: absencesBadge }} />}
      </SidebarContent>

      <SidebarFooter className="border-t border-sidebar-border">
        <SidebarMenu>
          {/* Fixed FE-only link (spec 0150 D-4, revised 2026-09-23): NOT part
              of the backend-driven navigation tree, unlike every entry in
              <NavMain>. Positioned right above "Impostazioni" by design. */}
          <SidebarMenuItem>
            <SidebarMenuButton
              asChild
              tooltip={notificationsTooltip}
              size="sm"
              isActive={pathname === NOTIFICATIONS_ROUTE}
              className={NAV_ITEM_CLASS}
            >
              <NavLink to={link(NOTIFICATIONS_ROUTE)}>
                <Bell />
                <span>{t('navigation.notifications')}</span>
              </NavLink>
            </SidebarMenuButton>
            {unreadBadge.label ? (
              <SidebarMenuBadge
                aria-label={unreadBadge.ariaLabel}
                className={NAV_BADGE_CLASS}
              >
                {unreadBadge.label}
              </SidebarMenuBadge>
            ) : null}
          </SidebarMenuItem>
          <SidebarMenuItem>
            <SidebarMenuButton
              asChild
              tooltip={t('navigation.settings')}
              size="sm"
              isActive={pathname === SETTINGS_ROUTE}
              className={NAV_ITEM_CLASS}
            >
              <NavLink to={link(SETTINGS_ROUTE)}>
                <Settings />
                <span>{t('navigation.settings')}</span>
              </NavLink>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>

      <SidebarRail />
    </Sidebar>
  )
}

function isNotFound(error: unknown): boolean {
  return axios.isAxiosError(error) && error.response?.status === 404
}

function NavSkeleton() {
  return (
    <SidebarMenu>
      {Array.from({ length: 4 }).map((_, index) => (
        <SidebarMenuItem key={index}>
          <SidebarMenuSkeleton showIcon />
        </SidebarMenuItem>
      ))}
    </SidebarMenu>
  )
}
