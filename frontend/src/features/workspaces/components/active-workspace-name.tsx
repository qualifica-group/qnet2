import { useActiveWorkspaceSlug } from '@/features/workspaces/use-active-workspace-slug'
import { useWorkspace } from '@/features/workspaces/use-workspace-name'
import { workspaceSidebarTextClass } from '@/features/workspaces/workspace-color'
import { cn } from '@/lib/utils'

/** First line of the sidebar header, above the product name: the app the user is working in, in its colour. */
export function ActiveWorkspaceName() {
  const slug = useActiveWorkspaceSlug()
  return slug ? <WorkspaceNameLine slug={slug} /> : null
}

// Split from the wrapper so the available-apps query only runs inside an app.
function WorkspaceNameLine({ slug }: { slug: string }) {
  const workspace = useWorkspace(slug)
  return (
    <span className={cn('truncate text-xs font-semibold', workspaceSidebarTextClass(workspace?.color))}>
      {workspace?.name ?? slug}
    </span>
  )
}
