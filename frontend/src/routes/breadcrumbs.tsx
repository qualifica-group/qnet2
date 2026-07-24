import { Fragment } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { useNavigation } from '@/features/navigation/use-navigation'
import { resolveIcon } from '@/features/navigation/icon-map'
import { useBreadcrumbTitles } from '@/routes/breadcrumb-title'
import type { NavigationItem } from '@/features/navigation/types'

/**
 * Maps a URL path segment to an i18n label key. Segments not listed here fall
 * back to a humanized version of the segment itself, so the breadcrumb never
 * breaks when a new route is added.
 */
const SEGMENT_LABELS: Record<string, string> = {
  dashboard: 'navigation.dashboard',
  users: 'navigation.users',
  roles: 'navigation.roles',
  companies: 'navigation.companies',
  'company-sites': 'navigation.companySites',
  'business-functions': 'navigation.businessFunctions',
  referents: 'navigation.referents',
  'referent-types': 'navigation.referentTypes',
  registries: 'navigation.registries',
  'operational-sites': 'navigation.operationalSites',
  attributes: 'navigation.attributes',
  new: 'common.new',
  edit: 'common.edit',
  'custom-fields': 'navigation.customFields',
  'product-categories': 'navigation.productCategories',
  sectors: 'navigation.sectors',
  products: 'navigation.products',
  'vat-rates': 'navigation.vatRates',
  sources: 'navigation.sources',
  tags: 'navigation.tags',
  projects: 'navigation.projects',
  campaigns: 'navigation.campaigns',
  leads: 'navigation.leads',
  opportunities: 'navigation.opportunities',
  'opportunity-statuses': 'navigation.opportunityStatuses',
  'opportunity-workflows': 'navigation.opportunityWorkflows',
  'request-management': 'navigation.requestManagement',
  'reward-types': 'navigation.rewardTypes',
  'reward-statuses': 'navigation.rewardStatuses',
  'rewarded-referents': 'navigation.rewardedReferents',
  imports: 'navigation.imports',
  'pipeline-statuses': 'navigation.pipelineStatuses',
  // Namespaced key (`ns:key`): the migrations module registers its own
  // i18next namespace instead of merging into `en.ts`/`it.ts` (see
  // `features/migrations/i18n.ts`).
  migrations: 'migrations:nav.label',
  settings: 'navigation.settings',
  login: 'auth.signInTitle',
  'forgot-password': 'auth.forgotPasswordTitle',
  'reset-password': 'auth.resetPasswordTitle',
}

function humanize(segment: string): string {
  return segment
    .replace(/-/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

/** Depth-first lookup of the navigation node whose route matches `route`. */
function findNavItemByRoute(
  items: NavigationItem[],
  route: string,
): NavigationItem | null {
  for (const item of items) {
    if (item.route === route) {
      return item
    }
    const nested = findNavItemByRoute(item.children, route)
    if (nested) {
      return nested
    }
  }
  return null
}

interface Crumb {
  label: string
  href: string
}

/**
 * Crumbs of the current URL. A label registered through `useBreadcrumbTitle`
 * (an entity name, for `/registries/12`) wins over the segment itself, which
 * would otherwise show the raw id.
 */
function useCrumbs(): Crumb[] {
  const { t, i18n } = useTranslation()
  const { pathname } = useLocation()
  const titles = useBreadcrumbTitles()

  const segments = pathname.split('/').filter(Boolean)

  return segments.map((segment, index) => {
    const href = '/' + segments.slice(0, index + 1).join('/')
    const key = SEGMENT_LABELS[segment]
    const label = key && i18n.exists(key) ? t(key) : humanize(segment)
    return { label: titles[href] ?? label, href }
  })
}

/**
 * Plain page title for the current route (the last URL segment), rendered in
 * the top app bar. This is a heading, NOT a breadcrumb.
 */
export function AppPageTitle({ className }: { className?: string }) {
  const crumbs = useCrumbs()
  const label = crumbs[crumbs.length - 1]?.label

  if (!label) {
    return null
  }

  return <h1 className={className ?? 'text-base font-semibold'}>{label}</h1>
}

/**
 * Breadcrumb derived directly from the current URL (no router `handle` /
 * `useMatches` dependency). For `/users` it renders a single "Utenti" crumb;
 * for nested paths it renders the full hierarchy.
 */
export function AppBreadcrumbs() {
  const navigation = useNavigation()
  const crumbs = useCrumbs()

  if (crumbs.length === 0) {
    return null
  }

  // The module icon is the one the sidebar uses for the top-level crumb: match
  // its route against the backend-driven navigation tree.
  const moduleItem = navigation.data
    ? findNavItemByRoute(navigation.data, crumbs[0].href)
    : null
  const ModuleIcon = moduleItem?.icon ? resolveIcon(moduleItem.icon) : null

  return (
    <Breadcrumb>
      <BreadcrumbList>
        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1
          const icon =
            index === 0 && ModuleIcon ? (
              <ModuleIcon className="size-4 shrink-0" aria-hidden />
            ) : null

          return (
            <Fragment key={crumb.href}>
              <BreadcrumbItem>
                {isLast ? (
                  <BreadcrumbPage className="flex items-center gap-1.5">
                    {icon}
                    {crumb.label}
                  </BreadcrumbPage>
                ) : (
                  <BreadcrumbLink asChild>
                    <Link to={crumb.href} className="flex items-center gap-1.5">
                      {icon}
                      {crumb.label}
                    </Link>
                  </BreadcrumbLink>
                )}
              </BreadcrumbItem>
              {!isLast ? <BreadcrumbSeparator /> : null}
            </Fragment>
          )
        })}
      </BreadcrumbList>
    </Breadcrumb>
  )
}
