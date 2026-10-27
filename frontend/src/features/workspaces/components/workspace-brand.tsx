import { SidebarMenuButton } from '@/components/ui/sidebar'
import { WorkspaceIcon } from '@/features/workspaces/components/workspace-icon'
import { useActiveWorkspaceSlug } from '@/features/workspaces/use-active-workspace-slug'
import { useWorkspace } from '@/features/workspaces/use-workspace-name'
import { workspaceSidebarTextClass } from '@/features/workspaces/workspace-color'
import { cn } from '@/lib/utils'

const LOGO_SRC = '/brands/logo_white.svg'

interface BrandApp {
  name: string
  icon: string | null
  color: string | null
}

/**
 * Sidebar header: the logo tile and the product name. Inside an app the app
 * name leads, in its colour, above the product name (spec 0317 AC-024), and
 * the app icon badges the tile so the app stays recognisable with the sidebar
 * collapsed to icons.
 */
export function WorkspaceBrand({ productName }: { productName: string }) {
  const slug = useActiveWorkspaceSlug()
  return slug ? <AppBrand slug={slug} productName={productName} /> : <BrandButton productName={productName} />
}

// Split from the wrapper so the available-apps query only runs inside an app.
function AppBrand({ slug, productName }: { slug: string; productName: string }) {
  const workspace = useWorkspace(slug)
  const app = { name: workspace?.name ?? slug, icon: workspace?.icon ?? null, color: workspace?.color ?? null }
  return <BrandButton productName={productName} app={app} />
}

function BrandButton({ productName, app }: { productName: string; app?: BrandApp }) {
  const appTextClass = workspaceSidebarTextClass(app?.color)

  return (
    <SidebarMenuButton size="lg" className="pointer-events-none gap-2.5">
      <span className="relative flex size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-accent ring-1 ring-sidebar-border ring-inset">
        <img src={LOGO_SRC} alt="" aria-hidden className="size-5 object-contain" />
        {app ? (
          <span className="absolute -right-1 -bottom-1 flex size-4 items-center justify-center rounded-[5px] bg-sidebar ring-2 ring-sidebar">
            <WorkspaceIcon name={app.icon} className={cn('size-2.5', appTextClass)} aria-hidden />
          </span>
        ) : null}
      </span>
      <span className="grid min-w-0 flex-1 text-left leading-tight">
        {app ? <span className={cn('truncate text-sm font-semibold', appTextClass)}>{app.name}</span> : null}
        <span className={cn('truncate', app ? 'text-xs text-sidebar-foreground/70' : 'text-sm font-semibold')}>
          {productName}
        </span>
      </span>
    </SidebarMenuButton>
  )
}
