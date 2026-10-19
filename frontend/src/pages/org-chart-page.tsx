import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { PageHeader } from '@/components/page-header'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Can } from '@/features/auth/can'
import { FunctionsView, PeopleView } from '@/features/org-chart/components/org-chart-views'

type OrgChartTab = 'functions' | 'people'

/**
 * Org chart page (spec 0273): gated by `org-chart.view`, two tree views in
 * tabs. Only the active tab is mounted, so only its endpoint is fetched.
 */
export default function OrgChartPage() {
  const { t } = useTranslation()
  const [tab, setTab] = useState<OrgChartTab>('functions')

  return (
    <Can
      permission="org-chart.view"
      fallback={<p className="text-sm text-muted-foreground">{t('orgChart.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-3">
        <PageHeader />
        <Tabs value={tab} onValueChange={(value) => setTab(value === 'people' ? 'people' : 'functions')}>
          <TabsList className="w-fit">
            <TabsTrigger value="functions" className="px-2.5 py-1 text-xs">
              {t('orgChart.tabs.functions')}
            </TabsTrigger>
            <TabsTrigger value="people" className="px-2.5 py-1 text-xs">
              {t('orgChart.tabs.people')}
            </TabsTrigger>
          </TabsList>
          <TabsContent value="functions">
            <FunctionsView />
          </TabsContent>
          <TabsContent value="people">
            <PeopleView />
          </TabsContent>
        </Tabs>
      </div>
    </Can>
  )
}
