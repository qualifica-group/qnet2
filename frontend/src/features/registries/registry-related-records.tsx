import { useMemo, useState, type ComponentType, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Briefcase, ClipboardList, FileText, ListChecks, Percent } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { RecordCard } from '@/components/detail/record-panel'
import { useAbilities } from '@/features/auth/use-abilities'
import {
  RegistryCommissionConfigurationsPanel,
  RegistryOpportunitiesPanel,
  RegistryQuotesPanel,
  RegistryTasksPanel,
  RegistryWorkOrdersPanel,
  type RegistryRelatedPanelProps,
} from '@/features/registries/registry-related-panels'

/** Compact trigger sizing, the same strip as the record's collaboration card. */
const TRIGGER_CLASS = 'px-2.5 py-1 text-xs'

interface RelatedTab {
  key: string
  /** The module's `viewAny`: without it the tab does not exist (AC-002). */
  permission: string
  labelKey: string
  icon: ReactNode
  Panel: ComponentType<RegistryRelatedPanelProps>
}

const RELATED_TABS: readonly RelatedTab[] = [
  {
    key: 'opportunities',
    permission: 'opportunities.viewAny',
    labelKey: 'navigation.opportunities',
    icon: <Briefcase className="size-3.5" aria-hidden="true" />,
    Panel: RegistryOpportunitiesPanel,
  },
  {
    key: 'quotes',
    permission: 'quotes.viewAny',
    labelKey: 'navigation.quotes',
    icon: <FileText className="size-3.5" aria-hidden="true" />,
    Panel: RegistryQuotesPanel,
  },
  {
    key: 'work-orders',
    permission: 'work-orders.viewAny',
    labelKey: 'navigation.workOrders',
    icon: <ClipboardList className="size-3.5" aria-hidden="true" />,
    Panel: RegistryWorkOrdersPanel,
  },
  {
    key: 'tasks',
    permission: 'tasks.viewAny',
    labelKey: 'navigation.tasks',
    icon: <ListChecks className="size-3.5" aria-hidden="true" />,
    Panel: RegistryTasksPanel,
  },
  {
    key: 'commission-configurations',
    permission: 'commission-configurations.viewAny',
    labelKey: 'registries.detail.related.commissionConfigurations',
    icon: <Percent className="size-3.5" aria-hidden="true" />,
    Panel: RegistryCommissionConfigurationsPanel,
  },
]

interface RegistryRelatedRecordsProps {
  registryId: number
}

/**
 * The anagrafica's related records (spec 0199): Opportunita', Offerte,
 * Commesse and Task of this client, one tab each, plus the Configuratore
 * commissioni rules naming it as recipient (spec 0204), full width below the record
 * (a side column is too narrow for a toolbar + grid). Only the active tab's
 * grid is mounted; its counter appears once that grid has reported its total
 * and stays while the user moves to another tab. No tab left = no card.
 */
export function RegistryRelatedRecords({ registryId }: RegistryRelatedRecordsProps) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [counts, setCounts] = useState<Record<string, number>>({})

  // One stable handler per tab: the grid keys its own row-count wiring on it.
  const countHandlers = useMemo(
    () =>
      Object.fromEntries(
        RELATED_TABS.map(({ key }) => [
          key,
          (count: number | null) => {
            if (count !== null) {
              setCounts((current) => (current[key] === count ? current : { ...current, [key]: count }))
            }
          },
        ]),
      ),
    [],
  )

  const tabs = RELATED_TABS.filter((tab) => can(tab.permission))

  if (tabs.length === 0) {
    return null
  }

  return (
    <RecordCard>
      <Tabs defaultValue={tabs[0].key} className="gap-0">
        <div className="overflow-x-auto px-4 py-3">
          <TabsList aria-label={t('registries.detail.related.title')}>
            {tabs.map((tab) => (
              <TabsTrigger key={tab.key} value={tab.key} className={TRIGGER_CLASS}>
                {tab.icon}
                {t(tab.labelKey)}
                {counts[tab.key] !== undefined ? (
                  <Badge
                    variant="secondary"
                    className="px-1.5 py-0 text-[10px]"
                    aria-label={t('registries.detail.related.countLabel', { count: counts[tab.key] })}
                  >
                    {counts[tab.key]}
                  </Badge>
                ) : null}
              </TabsTrigger>
            ))}
          </TabsList>
        </div>
        <div className="border-t" />
        <div className="min-w-0 p-4">
          {tabs.map(({ key, Panel }) => (
            <TabsContent key={key} value={key}>
              <Panel registryId={registryId} onRowCountChanged={countHandlers[key]} />
            </TabsContent>
          ))}
        </div>
      </Tabs>
    </RecordCard>
  )
}
