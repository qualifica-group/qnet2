import type { ReactNode } from 'react'
import { DashboardSectionHeader } from '@/features/dashboard/dashboard-section-header'
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'

interface DashboardModuleStatsSectionProps {
  /** Table domain of the module, e.g. `leads` (`GET /api/stats/{domain}`). */
  domain: string
  icon: ReactNode
  title: string
}

/**
 * One module statistics block (spec 0151 D-7/D-9): section header + the
 * generic `ModuleStatsPanel`, always open on the dashboard (unlike the list
 * pages, there is no toggle button here).
 */
export function DashboardModuleStatsSection({ domain, icon, title }: DashboardModuleStatsSectionProps) {
  return (
    <section aria-labelledby={`dashboard-stats-${domain}-heading`} className="flex flex-col gap-3">
      <DashboardSectionHeader id={`dashboard-stats-${domain}-heading`} icon={icon} title={title} />
      <ModuleStatsPanel domain={domain} isOpen />
    </section>
  )
}
