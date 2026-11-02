import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useConfirm } from '@/components/confirm-dialog-context'
import { WorkspaceIcon } from '@/features/workspaces/components/workspace-icon'
import { WorkspaceFormSheet } from '@/features/workspaces/components/workspace-form-sheet'
import { useDeleteWorkspace, useWorkspaces } from '@/features/workspaces/use-workspaces'
import { workspacePanelTextClass } from '@/features/workspaces/workspace-color'
import { cn } from '@/lib/utils'
import type { Workspace } from '@/features/workspaces/types'

type FormTarget = { type: 'create' } | { type: 'edit'; workspace: Workspace }

/** "Apps" configuration page of the Develop section (spec 0317): super-admin only, enforced server-side. */
export default function WorkspacesPage() {
  const { t, i18n } = useTranslation('workspaces')
  const confirm = useConfirm()
  const workspaces = useWorkspaces()
  const deleteMutation = useDeleteWorkspace()
  const [target, setTarget] = useState<FormTarget | null>(null)

  const remove = async (workspace: Workspace) => {
    // Step 1: ask for confirmation
    const confirmed = await confirm({
      title: t('confirmDelete.title', { name: workspace.name }),
      description: t('confirmDelete.description'),
      confirmLabel: t('confirmDelete.confirm'),
      tone: 'destructive',
    })
    if (!confirmed) {
      return
    }
    // Step 2: delete; the mutation refreshes the lists and the menus
    try {
      await deleteMutation.mutateAsync(workspace.id)
      toast.success(t('page.deleted'))
    } catch (error) {
      toast.error(apiErrorMessage(error, t('page.deleteError'), i18n))
    }
  }

  return (
    <div className="flex min-w-0 flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Button size="sm" onClick={() => setTarget({ type: 'create' })}>
            <Plus className="size-3.5" aria-hidden />
            {t('page.new')}
          </Button>
        }
      />
      <p className="text-sm text-muted-foreground">{t('page.description')}</p>

      {workspaces.isPending ? (
        <div className="flex flex-col gap-2" aria-busy="true">
          <Skeleton className="h-14 w-full" />
          <Skeleton className="h-14 w-full" />
        </div>
      ) : workspaces.isError ? (
        <p role="alert" className="text-sm text-destructive">
          {t('page.loadError')}
        </p>
      ) : workspaces.data.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('page.empty')}</p>
      ) : (
        <ul className="grid gap-2">
          {workspaces.data.map((workspace) => (
            <WorkspaceRow
              key={workspace.id}
              workspace={workspace}
              onEdit={() => setTarget({ type: 'edit', workspace })}
              onDelete={() => void remove(workspace)}
            />
          ))}
        </ul>
      )}

      {target ? (
        <WorkspaceFormSheet
          key={target.type === 'edit' ? target.workspace.id : 'new'}
          workspace={target.type === 'edit' ? target.workspace : null}
          onClose={() => setTarget(null)}
        />
      ) : null}
    </div>
  )
}

interface WorkspaceRowProps {
  workspace: Workspace
  onEdit: () => void
  onDelete: () => void
}

function WorkspaceRow({ workspace, onEdit, onDelete }: WorkspaceRowProps) {
  const { t } = useTranslation('workspaces')

  return (
    <li className="flex items-center gap-3 rounded-lg border border-border bg-card p-3 shadow-sm">
      <WorkspaceIcon name={workspace.icon} className={cn('size-5 shrink-0', workspacePanelTextClass(workspace.color))} />
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold">{workspace.name}</p>
        <p className="truncate text-xs text-muted-foreground">
          /app/{workspace.slug} · {t('page.entries', { count: workspace.navigation_keys.length })}
        </p>
      </div>
      <Button
        type="button"
        size="icon"
        variant="secondary"
        className="size-7"
        aria-label={t('page.editAria', { name: workspace.name })}
        onClick={onEdit}
      >
        <Pencil className="size-3.5" aria-hidden />
      </Button>
      <Button
        type="button"
        size="icon"
        variant="secondary"
        className="size-7"
        aria-label={t('page.deleteAria', { name: workspace.name })}
        onClick={onDelete}
      >
        <Trash2 className="size-3.5" aria-hidden />
      </Button>
    </li>
  )
}
