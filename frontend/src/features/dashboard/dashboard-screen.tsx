import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2, FileText, Target, UserPlus } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { useAbilities } from '@/features/auth/use-abilities'
import { DashboardModuleStatsSection } from '@/features/dashboard/dashboard-module-stats-section'
import { DashboardTasksSection } from '@/features/dashboard/dashboard-tasks-section'
import { DashboardTimeEntriesSection } from '@/features/dashboard/dashboard-time-entries-section'

interface ModuleStatsBlock {
  domain: string
  icon: ReactNode
  titleKey: string
}

/** Module statistics blocks in D-9's fixed order; Task statistics live inside `DashboardTasksSection`. */
const MODULE_STATS_BLOCKS: readonly ModuleStatsBlock[] = [
  { domain: 'opportunities', icon: <Target aria-hidden="true" />, titleKey: 'dashboard.moduleSections.opportunities' },
  { domain: 'quotes', icon: <FileText aria-hidden="true" />, titleKey: 'dashboard.moduleSections.quotes' },
  { domain: 'leads', icon: <UserPlus aria-hidden="true" />, titleKey: 'dashboard.moduleSections.leads' },
  { domain: 'registries', icon: <Building2 aria-hidden="true" />, titleKey: 'dashboard.moduleSections.registries' },
]

/**
 * `/dashboard` page assembly (spec 0151 D-9/AC-011): each block is gated by
 * its own `frontend_gates` permission and simply not MOUNTED when missing —
 * the surest way to guarantee a hidden block issues no request — then an
 * empty state covers the case where every gate is closed.
 */
export function DashboardScreen() {
  const { t } = useTranslation()
  const { can, isLoading: isLoadingAbilities } = useAbilities()

  const canTasks = can('tasks.viewAny')
  const canTimeEntries = can('time-entries.viewAny')
  const statsBlocks = MODULE_STATS_BLOCKS.map((block) => ({ ...block, visible: can(`${block.domain}.viewAny`) }))
  const hasAnyBlock = canTasks || canTimeEntries || statsBlocks.some((block) => block.visible)

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeader />

      {!isLoadingAbilities && !hasAnyBlock ? (
        <div className="flex flex-col items-center gap-1 rounded-lg border border-dashed bg-card px-4 py-10 text-center">
          <p className="text-sm font-medium">{t('dashboard.empty.title')}</p>
          <p className="text-sm text-muted-foreground">{t('dashboard.empty.description')}</p>
        </div>
      ) : null}

      {canTasks ? <DashboardTasksSection /> : null}
      {canTimeEntries ? <DashboardTimeEntriesSection /> : null}

      {statsBlocks.map((block) =>
        block.visible ? (
          <DashboardModuleStatsSection key={block.domain} domain={block.domain} icon={block.icon} title={t(block.titleKey)} />
        ) : null,
      )}
    </div>
  )
}
