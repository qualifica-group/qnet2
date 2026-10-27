import { useId, useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Check, LayoutGrid } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useAuth } from '@/features/auth/use-auth'
import { WorkspaceIcon } from '@/features/workspaces/components/workspace-icon'
import { forgetWorkspace, rememberWorkspace } from '@/features/workspaces/remembered-workspace'
import { useActiveWorkspaceSlug } from '@/features/workspaces/use-active-workspace-slug'
import { useAvailableWorkspaces } from '@/features/workspaces/use-available-workspaces'
import { workspacePanelTextClass } from '@/features/workspaces/workspace-color'
import type { AvailableWorkspace } from '@/features/workspaces/types'
import { workspacePath } from '@/features/workspaces/workspace-path'
import { cn } from '@/lib/utils'

const FULL_VIEW_HOME = '/dashboard'

const ROW_CLASS =
  'flex min-w-0 items-start gap-2 rounded-md border border-transparent px-2 py-1.5 text-sm outline-none hover:border-border focus-visible:ring-[3px] focus-visible:ring-ring/50'
// Border + check, not a fill: a tinted fill would lower the contrast of the coloured app names below AA.
const CURRENT_ROW_CLASS = 'border-border font-semibold'

/**
 * Header launcher of the apps (spec 0317 D-4, D-14), the "waffle" at the left
 * of the header. Hidden while there is nothing to choose from: no app
 * configured (the full view is open to everyone, D-5) or none visible to this
 * user.
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

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="ghost"
          size="icon"
          aria-label={t('launcher.open')}
          className="size-7 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground [&_svg]:size-3.5"
        >
          <LayoutGrid />
          <span className="sr-only">{t('launcher.open')}</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-[min(16rem,calc(100vw-1.5rem))] p-2">
        <p className="px-1 pb-2 text-xs font-medium text-muted-foreground">{t('launcher.title')}</p>
        <ul className="grid gap-0.5">
          {data.workspaces.map((workspace) => (
            <WorkspaceLauncherItem
              key={workspace.slug}
              workspace={workspace}
              isCurrent={workspace.slug === activeSlug}
              onChoose={() => choose(workspace.slug)}
            />
          ))}
          {data.full_view_available ? (
            <li className="min-w-0">
              <Link
                to={FULL_VIEW_HOME}
                onClick={() => choose(null)}
                aria-current={activeSlug === null ? 'true' : undefined}
                title={t('launcher.fullViewHint')}
                className={cn(ROW_CLASS, activeSlug === null && CURRENT_ROW_CLASS)}
              >
                <LayoutGrid className="size-4 shrink-0" aria-hidden />
                <span className="min-w-0 truncate">{t('launcher.fullView')}</span>
                {activeSlug === null ? <Check className="ml-auto size-3.5 shrink-0" aria-hidden /> : null}
              </Link>
            </li>
          ) : null}
        </ul>
      </PopoverContent>
    </Popover>
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
        <WorkspaceIcon name={workspace.icon} className="size-4 shrink-0" />
        <span className="min-w-0 flex-1">
          <span id={nameId} className="block truncate">
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
        {isCurrent ? <Check className="ml-auto size-3.5 shrink-0" aria-hidden /> : null}
      </Link>
    </li>
  )
}
