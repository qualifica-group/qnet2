import { useId, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Check, ChevronDown, LayoutGrid, Settings2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useAuth } from '@/features/auth/use-auth'
import { WorkspaceIcon } from '@/features/workspaces/components/workspace-icon'
import { forgetWorkspace, rememberWorkspace } from '@/features/workspaces/remembered-workspace'
import { useActiveWorkspaceSlug } from '@/features/workspaces/use-active-workspace-slug'
import { useAvailableWorkspaces } from '@/features/workspaces/use-available-workspaces'
import { workspacePanelTextClass, workspaceSidebarTextClass } from '@/features/workspaces/workspace-color'
import type { AvailableWorkspace } from '@/features/workspaces/types'
import { workspacePath } from '@/features/workspaces/workspace-path'
import { cn } from '@/lib/utils'

const FULL_VIEW_HOME = '/dashboard'
const MANAGE_APPS_ROUTE = '/dev/workspaces'

const ROW_CLASS =
  'flex min-w-0 items-center gap-2.5 rounded-md border border-transparent px-2 py-1.5 text-sm outline-none transition-colors hover:border-border focus-visible:ring-[3px] focus-visible:ring-ring/50'
// Border + check, not a fill: a tinted fill would lower the contrast of the coloured app names below AA.
const CURRENT_ROW_CLASS = 'border-border'
// The tile tints itself from the row's text colour, so each app gets its own hue without a second palette.
const TILE_CLASS =
  'flex size-8 shrink-0 items-center justify-center rounded-lg bg-current/10 ring-1 ring-current/15 ring-inset'

/**
 * Header launcher of the apps (spec 0317 D-4, D-14): a selector showing the
 * current app, opening the list of apps. Hidden while there is nothing to
 * choose from: no app configured (the full view is open to everyone, D-5) or
 * none visible to this user.
 */
export function WorkspaceLauncher() {
  const { t } = useTranslation('workspaces')
  const { user } = useAuth()
  const { data } = useAvailableWorkspaces()
  const activeSlug = useActiveWorkspaceSlug()
  const [open, setOpen] = useState(false)

  if (!data || data.workspaces.length === 0) {
    return null
  }

  const choose = (slug: string | null) => {
    if (slug) {
      rememberWorkspace(user?.id, slug)
    } else {
      forgetWorkspace(user?.id)
    }
    setOpen(false)
  }

  const current = data.workspaces.find((workspace) => workspace.slug === activeSlug)
  const currentLabel = current?.name ?? activeSlug ?? t('launcher.fullView')

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="ghost"
          size="sm"
          aria-label={t('launcher.open')}
          title={currentLabel}
          className="h-7 max-w-60 gap-1.5 px-1.5 text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
        >
          <LayoutGrid className="size-3.5 shrink-0 opacity-80" aria-hidden />
          <span className="h-4 w-px shrink-0 bg-sidebar-border" aria-hidden />
          {current ? (
            <WorkspaceIcon
              name={current.icon}
              className={cn('size-3.5 shrink-0', workspaceSidebarTextClass(current.color))}
              aria-hidden
            />
          ) : null}
          <span className="hidden min-w-0 truncate text-xs font-medium sm:inline">{currentLabel}</span>
          <ChevronDown className="size-3 shrink-0 opacity-60" aria-hidden />
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-[min(20rem,calc(100vw-1.5rem))] overflow-hidden p-0">
        <div className="flex items-center justify-between gap-2 border-b bg-surface px-3 py-2">
          <p className="text-xs font-semibold">{t('launcher.title')}</p>
          <span className="text-xs text-muted-foreground tabular-nums">{data.workspaces.length}</span>
        </div>
        <ul className="grid max-h-[min(24rem,60vh)] gap-0.5 overflow-y-auto p-1.5">
          {data.workspaces.map((workspace) => (
            <WorkspaceLauncherItem
              key={workspace.slug}
              workspace={workspace}
              isCurrent={workspace.slug === activeSlug}
              onChoose={() => choose(workspace.slug)}
            />
          ))}
        </ul>
        {/* With at least one app, only a super-admin gets the full view (D-3, D-5): the same audience as the config page. */}
        {data.full_view_available ? (
          <ul className="grid gap-0.5 border-t bg-surface p-1.5">
            <FullViewItem isCurrent={activeSlug === null} onChoose={() => choose(null)} />
            <li className="min-w-0">
              <Link
                to={MANAGE_APPS_ROUTE}
                onClick={() => setOpen(false)}
                className={cn(ROW_CLASS, 'py-1 text-xs text-muted-foreground hover:text-foreground')}
              >
                <span className="flex w-8 shrink-0 justify-center">
                  <Settings2 className="size-3.5" aria-hidden />
                </span>
                <span className="truncate">{t('launcher.manage')}</span>
              </Link>
            </li>
          </ul>
        ) : null}
      </PopoverContent>
    </Popover>
  )
}

function FullViewItem({ isCurrent, onChoose }: { isCurrent: boolean; onChoose: () => void }) {
  const { t } = useTranslation('workspaces')
  const nameId = useId()
  const hintId = useId()

  return (
    <li className="min-w-0">
      <Link
        to={FULL_VIEW_HOME}
        onClick={onChoose}
        aria-current={isCurrent ? 'true' : undefined}
        aria-labelledby={nameId}
        aria-describedby={hintId}
        className={cn(ROW_CLASS, 'text-foreground', isCurrent && CURRENT_ROW_CLASS)}
      >
        <span className={TILE_CLASS}>
          <LayoutGrid className="size-4" aria-hidden />
        </span>
        <span className="min-w-0 flex-1">
          <span id={nameId} className="block truncate font-semibold">
            {t('launcher.fullView')}
          </span>
          <span id={hintId} className="block truncate text-xs text-muted-foreground">
            {t('launcher.fullViewHint')}
          </span>
        </span>
        {isCurrent ? <Check className="size-3.5 shrink-0 text-primary" aria-hidden /> : null}
      </Link>
    </li>
  )
}

interface WorkspaceLauncherItemProps {
  workspace: AvailableWorkspace
  isCurrent: boolean
  onChoose: () => void
}

/** One app row. The accessible name is the app name only; the description is announced as its description. */
function WorkspaceLauncherItem({ workspace, isCurrent, onChoose }: WorkspaceLauncherItemProps) {
  const { t } = useTranslation('workspaces')
  const nameId = useId()
  const descriptionId = useId()

  return (
    <li className="min-w-0">
      <Link
        to={workspacePath(workspace.slug, workspace.home_route)}
        onClick={onChoose}
        aria-current={isCurrent ? 'true' : undefined}
        aria-labelledby={nameId}
        aria-describedby={workspace.description ? descriptionId : undefined}
        title={isCurrent ? t('launcher.current') : undefined}
        className={cn(ROW_CLASS, workspacePanelTextClass(workspace.color), isCurrent && CURRENT_ROW_CLASS)}
      >
        <span className={TILE_CLASS}>
          <WorkspaceIcon name={workspace.icon} className="size-4" />
        </span>
        <span className="min-w-0 flex-1">
          <span id={nameId} className="block truncate font-semibold">
            {workspace.name}
          </span>
          {workspace.description ? (
            <span
              id={descriptionId}
              className="line-clamp-2 block text-xs font-normal break-words text-muted-foreground"
            >
              {workspace.description}
            </span>
          ) : null}
        </span>
        {isCurrent ? <Check className="size-3.5 shrink-0 text-primary" aria-hidden /> : null}
      </Link>
    </li>
  )
}
