import type { ReactNode } from 'react'
import { AppBreadcrumbs } from '@/routes/breadcrumbs'

interface PageHeaderProps {
  /**
   * Page-level actions rendered on the same row as the breadcrumb, aligned to
   * the right. The primary create action of every table lives HERE (aligned to
   * the breadcrumb), not inside the table toolbar.
   */
  actions?: ReactNode
}

/**
 * Standard page header: breadcrumb on the left, page-level actions on the
 * right. Shared by every module so the header looks the same everywhere and
 * the "New entity" button is always aligned with the breadcrumb. No title or
 * subtitle: the page title already lives in the top app bar (`AppPageTitle`).
 */
export function PageHeader({ actions }: PageHeaderProps) {
  return (
    <div className="flex items-center justify-between gap-4">
      <AppBreadcrumbs />
      {actions ? (
        <div className="flex items-center gap-2">{actions}</div>
      ) : null}
    </div>
  )
}
