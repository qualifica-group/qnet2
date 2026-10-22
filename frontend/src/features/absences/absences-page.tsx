import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { FilterTabs } from '@/components/ui/filter-tabs'
import { Can } from '@/features/auth/can'
import { useAbilities } from '@/features/auth/use-abilities'
import type { TableViewHandle } from '@/features/table/table-view'
import { BalanceOverview } from '@/features/absences/balance-overview/balance-overview'
import { AbsenceBalancesCard } from '@/features/absences/balances/absence-balances-card'
import { AbsenceCalendar } from '@/features/absences/calendar/absence-calendar'
import { AbsenceRequestDetailSheet } from '@/features/absences/requests/absence-request-detail-sheet'
import type { AbsenceRequestPrefill } from '@/features/absences/requests/absence-request-payload'
import { AbsenceRequestSheet } from '@/features/absences/requests/absence-request-sheet'
import { AbsenceRequestsGrid } from '@/features/absences/requests/absence-requests-grid'
import { useAbsenceScope } from '@/features/absences/requests/use-absence-scope'
import { AbsenceSummaryPanel } from '@/features/absences/summary/absence-summary-panel'
import { ABSENCE_CALENDAR_PERMISSIONS, type AbsenceRequest } from '@/features/absences/types'

/** Query parameter of the notifications' `action_url` (`/absences?request={id}`). */
const REQUEST_PARAM = 'request'

const MANAGE_ALL = 'absence-requests.manageAll'
const PAGE_VIEWS = ['requests', 'calendar', 'balances'] as const
type PageView = (typeof PAGE_VIEWS)[number]

/** Positive integer id from the URL, or null for anything else. */
function parseRequestId(value: string | null): number | null {
  const id = Number(value)
  return Number.isInteger(id) && id > 0 ? id : null
}

/**
 * Absences page (spec 0278): the actor's balances, the requests grid with its
 * scope tabs and personal summary, the create/edit-draft Sheet and the detail Sheet (also opened by
 * `?request={id}`). Gated by `absence-requests.viewAny`. The "Calendario" view
 * (spec 0279) shows up with any of the three calendar permissions; the view switch sits in the
 * page header, so only the scope tabs stay above the grid (spec 0287 D-5).
 */
export default function AbsencesPage() {
  const { t } = useTranslation()
  const [searchParams, setSearchParams] = useSearchParams()
  const tableRef = useRef<TableViewHandle>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<AbsenceRequest | undefined>(undefined)
  const [prefill, setPrefill] = useState<AbsenceRequestPrefill | undefined>(undefined)
  const [view, setView] = useState<PageView>('requests')
  const { can } = useAbilities()
  const { scope, selectScope, toApproveCount } = useAbsenceScope()
  const canSeeCalendar = ABSENCE_CALENDAR_PERMISSIONS.some((permission) => can(permission))
  const canSeeBalances = can(MANAGE_ALL)
  const availableViews = PAGE_VIEWS.filter(
    (value) => value === 'requests' || (value === 'calendar' ? canSeeCalendar : canSeeBalances),
  )
  const activeView = availableViews.includes(view) ? view : 'requests'

  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const openedId = parseRequestId(searchParams.get(REQUEST_PARAM))

  const openDetail = useCallback(
    (id: number) => setSearchParams({ [REQUEST_PARAM]: String(id) }, { replace: true }),
    [setSearchParams],
  )
  const closeDetail = useCallback(() => setSearchParams({}, { replace: true }), [setSearchParams])

  const openCreate = (startingValues?: AbsenceRequestPrefill) => {
    setEditing(undefined)
    setPrefill(startingValues)
    setFormOpen(true)
  }
  const openEdit = (request: AbsenceRequest) => {
    closeDetail()
    setEditing(request)
    setPrefill(undefined)
    setFormOpen(true)
  }

  return (
    <Can
      permission="absence-requests.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('absences.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader
          actions={
            <div className="flex flex-wrap items-center justify-end gap-2">
              {availableViews.length > 1 ? (
                <FilterTabs
                  aria-label={t('absences.page.viewsLabel')}
                  value={activeView}
                  options={availableViews.map((value) => ({ value, label: t(`absences.page.views.${value}`) }))}
                  onValueChange={setView}
                />
              ) : null}
              <Can permission="absence-requests.create">
                <Button type="button" onClick={() => openCreate()}>
                  <Plus aria-hidden="true" />
                  {t('absences.page.newRequest')}
                </Button>
              </Can>
            </div>
          }
        />
        {activeView === 'calendar' ? <AbsenceCalendar onOpen={openDetail} onCreate={(startsOn, person) => openCreate({ startsOn, person })} /> : null}
        {activeView === 'balances' ? <BalanceOverview /> : null}
        {activeView === 'requests' ? (
          <>
            <AbsenceSummaryPanel onOpen={openDetail} onGoToApprove={() => selectScope('to_approve')} />
            <AbsenceBalancesCard />
            <AbsenceRequestsGrid
              tableRef={tableRef}
              onOpen={openDetail}
              scope={scope}
              onScopeChange={selectScope}
              toApproveCount={toApproveCount}
            />
          </>
        ) : null}
      </div>

      <AbsenceRequestDetailSheet
        requestId={openedId}
        onClose={closeDetail}
        onEdit={openEdit}
        refreshGrid={refreshGrid}
      />
      <AbsenceRequestSheet
        open={formOpen}
        onOpenChange={setFormOpen}
        request={editing}
        prefill={prefill}
        refreshGrid={refreshGrid}
        onSaved={() => setFormOpen(false)}
      />
    </Can>
  )
}
