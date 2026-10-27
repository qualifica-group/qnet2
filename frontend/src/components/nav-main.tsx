import { createElement } from 'react'
import { ChevronRight } from 'lucide-react'
import { NavLink } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuAction,
  SidebarMenuBadge,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from '@/components/ui/sidebar'
import { resolveIcon } from '@/features/navigation/icon-map'
import { buildNavBlocks, isSingleChildGroup } from '@/features/navigation/nav-blocks'
import type { NavigationItem } from '@/features/navigation/types'
import { useWorkspaceNav } from '@/features/workspaces/use-workspace-nav'
import { cn } from '@/lib/utils'

// Compact navigation items: smaller text (via size="sm") and shrunk icons,
// overriding the design-system default of size-4.
export const NAV_ITEM_CLASS = '[&>svg]:size-3.5'

// Sections and parent items (the ones holding children) read as headings, not
// as destinations: uppercase + weight separates them from the leaves you can
// click. Letter-spacing compensates the loss of word shape that uppercase causes.
const NAV_HEADING_CLASS = 'font-medium uppercase tracking-wide'

// Leaves stay at regular weight, active included: the active state is already
// carried by background and colour, so weight is reserved for the hierarchy.
const NAV_LEAF_CLASS = 'font-normal data-[active=true]:font-normal'

// Red pill like the bell's destructive Badge. The peer-* overrides are needed
// because the primitive recolors its text on hover/active of the menu button.
export const NAV_BADGE_CLASS =
  'rounded-full bg-destructive text-white peer-hover/menu-button:text-white peer-data-[active=true]/menu-button:text-white'

/** Count pill of a menu entry; the count is also folded into the tooltip, the only text shown once the sidebar collapses. */
export interface NavBadge {
  count: number
  /** Compact text ("7", "99+"). */
  label: string
  /** Accessible label with the EXACT count. */
  ariaLabel: string
}

/** Badges by navigation item key; an absent or null entry shows nothing. */
export type NavBadges = Record<string, NavBadge | null | undefined>

export function NavMain({ items, badges }: { items: NavigationItem[]; badges?: NavBadges }) {
  return (
    <>
      {buildNavBlocks(items).map((block) =>
        block.kind === 'items' ? (
          <SidebarGroup key={block.key}>
            <SidebarMenu>
              {block.items.map((item) => (
                <NavNode key={item.key} item={item} badges={badges} />
              ))}
            </SidebarMenu>
          </SidebarGroup>
        ) : (
          <NavSection key={block.section.key} section={block.section} badges={badges} />
        ),
      )}
    </>
  )
}

// A section is a static labeled group: its children render as flat siblings.
// A child that is itself a parent (has children) is rendered collapsible by
// NavNode, so both behaviors coexist under the same section. A top-level group
// with a single child is rendered here too, as a section over that child.
function NavSection({ section, badges }: { section: NavigationItem; badges?: NavBadges }) {
  const { t } = useTranslation()

  return (
    <SidebarGroup>
      <SidebarGroupLabel className={NAV_HEADING_CLASS}>{t(section.label)}</SidebarGroupLabel>
      <SidebarMenu>
        {section.children.map((child) => (
          <NavNode key={child.key} item={child} badges={badges} />
        ))}
      </SidebarMenu>
    </SidebarGroup>
  )
}

function NavNode({ item, badges }: { item: NavigationItem; badges?: NavBadges }) {
  const { t } = useTranslation()
  const { link, pathname } = useWorkspaceNav()

  // Inside a section a lone child takes the group's place: sections do not nest.
  if (isSingleChildGroup(item)) {
    return <NavNode item={item.children[0]} badges={badges} />
  }

  const icon = createElement(resolveIcon(item.icon))
  const label = t(item.label)
  const badge = badges?.[item.key]

  // Route-less parent: a heading that only toggles its children.
  if (item.children.length > 0 && !item.route) {
    const keepOpen = hasActiveDescendant(item, pathname)

    return (
      <Collapsible asChild defaultOpen={keepOpen} className="group/collapsible">
        <SidebarMenuItem>
          <CollapsibleTrigger asChild>
            <SidebarMenuButton tooltip={label} size="sm" className={cn(NAV_ITEM_CLASS, NAV_HEADING_CLASS)}>
              {icon}
              <span>{label}</span>
              <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
            </SidebarMenuButton>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <SidebarMenuSub>
              {item.children.map((child) => (
                <NavSubNode key={child.key} item={child} />
              ))}
            </SidebarMenuSub>
          </CollapsibleContent>
        </SidebarMenuItem>
      </Collapsible>
    )
  }

  // A leaf node without a route is a non-navigable label (defensive: the
  // backend only emits route-less nodes as groups, which are handled above).
  if (!item.route) {
    return (
      <SidebarMenuItem>
        <SidebarMenuButton tooltip={label} size="sm" className={cn('pointer-events-none opacity-70', NAV_ITEM_CLASS, NAV_LEAF_CLASS)}>
          {icon}
          <span>{label}</span>
        </SidebarMenuButton>
      </SidebarMenuItem>
    )
  }

  const linkButton = (
    <SidebarMenuButton
      asChild
      tooltip={badge ? `${label} (${badge.count})` : label}
      size="sm"
      isActive={isActive(item, pathname)}
      className={cn(NAV_ITEM_CLASS, NAV_LEAF_CLASS)}
    >
      <NavLink to={link(item.route)}>
        {icon}
        <span>{label}</span>
      </NavLink>
    </SidebarMenuButton>
  )

  // Navigable parent (e.g. Gestione Richieste -> Richieste di modifica): the
  // label keeps navigating, a dedicated chevron toggles the children, same as
  // a nested sub-item in NavSubNode. Its chevron takes the badge's slot.
  if (item.children.length > 0) {
    return (
      <Collapsible asChild defaultOpen={hasActiveDescendant(item, pathname)} className="group/collapsible">
        <SidebarMenuItem>
          {linkButton}
          <CollapsibleTrigger asChild>
            <SidebarMenuAction className="[&>svg]:size-3.5">
              <ChevronRight className="transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
              <span className="sr-only">{label}</span>
            </SidebarMenuAction>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <SidebarMenuSub>
              {item.children.map((child) => (
                <NavSubNode key={child.key} item={child} />
              ))}
            </SidebarMenuSub>
          </CollapsibleContent>
        </SidebarMenuItem>
      </Collapsible>
    )
  }

  return (
    <SidebarMenuItem>
      {linkButton}
      {badge ? (
        <SidebarMenuBadge aria-label={badge.ariaLabel} className={NAV_BADGE_CLASS}>
          {badge.label}
        </SidebarMenuBadge>
      ) : null}
    </SidebarMenuItem>
  )
}

// A sub-item inside a collapsible group. A leaf renders a navigable link; a
// sub-item that itself has children stays navigable AND collapses its nested
// children behind a dedicated chevron (e.g. Leads → Import): the label keeps
// navigating, the chevron toggles the list, default collapsed.
function NavSubNode({ item }: { item: NavigationItem }) {
  const { t } = useTranslation()
  const { link, pathname } = useWorkspaceNav()
  const label = t(item.label)

  const linkNode = (
    <SidebarMenuSubButton asChild size="sm" isActive={isActive(item, pathname)} className={cn(NAV_ITEM_CLASS, NAV_LEAF_CLASS)}>
      <NavLink to={item.route ? link(item.route) : '#'}>
        <span>{label}</span>
      </NavLink>
    </SidebarMenuSubButton>
  )

  if (item.children.length === 0) {
    return <SidebarMenuSubItem>{linkNode}</SidebarMenuSubItem>
  }

  const keepOpen = hasActiveDescendant(item, pathname)

  return (
    <Collapsible asChild defaultOpen={keepOpen} className="group/subcollapsible">
      <SidebarMenuSubItem>
        {linkNode}
        <CollapsibleTrigger asChild>
          <SidebarMenuAction className="top-1 [&>svg]:size-3.5">
            <ChevronRight className="transition-transform duration-200 group-data-[state=open]/subcollapsible:rotate-90" />
            <span className="sr-only">{label}</span>
          </SidebarMenuAction>
        </CollapsibleTrigger>
        <CollapsibleContent>
          <SidebarMenuSub>
            {item.children.map((child) => (
              <NavSubNode key={child.key} item={child} />
            ))}
          </SidebarMenuSub>
        </CollapsibleContent>
      </SidebarMenuSubItem>
    </Collapsible>
  )
}

function isActive(item: NavigationItem, pathname: string): boolean {
  if (!item.route) {
    return false
  }
  return pathname === item.route || pathname.startsWith(`${item.route}/`)
}

// Keeps a group expanded when any descendant (not just a direct child) matches
// the current route — needed now that sub-items can nest their own children.
function hasActiveDescendant(item: NavigationItem, pathname: string): boolean {
  return item.children.some(
    (child) => isActive(child, pathname) || hasActiveDescendant(child, pathname),
  )
}
