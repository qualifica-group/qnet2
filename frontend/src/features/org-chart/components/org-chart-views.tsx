import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { OrgMoveLayer } from '@/features/org-chart/components/org-move-layer'
import { OrgChartPanel } from '@/features/org-chart/components/org-chart-panel'
import { buildFunctionTree } from '@/features/org-chart/build-function-tree'
import { buildPeopleTree } from '@/features/org-chart/build-people-tree'
import { useOrgChartFunctions, useOrgChartPeople } from '@/features/org-chart/use-org-chart'
import type { OrgTreeNode } from '@/features/org-chart/tree-nodes'

const NO_NODES: OrgTreeNode[] = []

export function FunctionsView() {
  const { t } = useTranslation()
  const query = useOrgChartFunctions()
  const nodes = useMemo(() => (query.data ? buildFunctionTree(query.data) : NO_NODES), [query.data])

  return (
    <OrgMoveLayer view="functions" nodes={nodes}>
      {(rowRenderers) => (
        <OrgChartPanel
          {...rowRenderers}
          nodes={nodes}
          isLoading={query.isLoading}
          isError={query.isError}
          error={query.error}
          ariaLabel={t('orgChart.tabs.functions')}
          emptyLabel={t('orgChart.emptyFunctions')}
        />
      )}
    </OrgMoveLayer>
  )
}

export function PeopleView() {
  const { t } = useTranslation()
  const query = useOrgChartPeople()
  const nodes = useMemo(() => (query.data ? buildPeopleTree(query.data) : NO_NODES), [query.data])

  return (
    <OrgMoveLayer view="people" nodes={nodes}>
      {(rowRenderers) => (
        <OrgChartPanel
          {...rowRenderers}
          nodes={nodes}
          isLoading={query.isLoading}
          isError={query.isError}
          error={query.error}
          ariaLabel={t('orgChart.tabs.people')}
          emptyLabel={t('orgChart.emptyPeople')}
        />
      )}
    </OrgMoveLayer>
  )
}
