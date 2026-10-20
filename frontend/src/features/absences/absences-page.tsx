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
import { AbsenceBalancesCard } from '@/features/absences/balances/absence-balances-card'
import { AbsenceCalendar } from '@/features/absences/calendar/absence-calendar'
import { AbsenceRequestDetailSheet } from '@/features/absences/requests/absence-request-detail-sheet'
import { AbsenceRequestSheet } from '@/features/absences/requests/absence-request-sheet'
import { AbsenceRequestsGrid } from '@/features/absences/requests/absence-requests-grid'
import { ABSENCE_CALENDAR_PERMISSIONS, type AbsenceRequest } from '@/features/absences/types'

/** Query parameter of the notifications' `action_url` (`/absences?request={id}`). */
const REQUEST_PARAM = 'request'

const PAGE_VIEWS = ['requests', 'calendar'] as const
type PageView = (typeof PAGE_VIEWS)[number]

/** Positive integer id from the URL, or null for anything else. */
function parseRequestId(value: string | null): number | null {
  const id = Number(value)
  return Number.isInteger(id) && id > 0 ? id : null
}

/**
 * Absences page (spec 0278): the actor's balances, the requests grid with its
 * scope tabs, the create/edit-draft Sheet and the detail Sheet (also opened by
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
  const [view, setView] = useState<PageView>('requests')
  const { can } = useAbilities()
  const canSeeCalendar = ABSENCE_CALENDAR_PERMISSIONS.some((permission) => can(permission))

  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])
  const openedId = parseRequestId(searchParams.get(REQUEST_PARAM))

  const openDetail = useCallback(
    (id: number) => setSearchParams({ [REQUEST_PARAM]: String(id) }, { replace: true }),
    [setSearchParams],
  )
  const closeDetail = useCallback(() => setSearchParams({}, { replace: true }), [setSearchParams])

  const openCreate = () => {
    setEditing(undefined)
    setFormOpen(true)
  }
  const openEdit = (request: AbsenceRequest) => {
    closeDetail()
    setEditing(request)
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
              {canSeeCalendar ? (
                <FilterTabs
                  aria-label={t('absences.page.viewsLabel')}
                  value={view}
                  options={PAGE_VIEWS.map((value) => ({ value, label: t(`absences.page.views.${value}`) }))}
                  onValueChange={setView}
                />
              ) : null}
              <Can permission="absence-requests.create">
                <Button type="button" onClick={openCreate}>
                  <Plus aria-hidden="true" />
                  {t('absences.page.newRequest')}
                </Button>
              </Can>
            </div>
          }
        />
        {canSeeCalendar && view === 'calendar' ? (
          <AbsenceCalendar onOpen={openDetail} />
        ) : (
          <>
            <AbsenceBalancesCard />
            <AbsenceRequestsGrid tableRef={tableRef} onOpen={openDetail} />
          </>
        )}
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
        refreshGrid={refreshGrid}
        onSaved={() => setFormOpen(false)}
      />
    </Can>
  )
}
