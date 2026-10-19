import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { PageHeader } from '@/components/page-header'
import { Button } from '@/components/ui/button'
import { Can } from '@/features/auth/can'
import type { TableViewHandle } from '@/features/table/table-view'
import { AbsenceBalancesCard } from '@/features/absences/balances/absence-balances-card'
import { AbsenceRequestDetailSheet } from '@/features/absences/requests/absence-request-detail-sheet'
import { AbsenceRequestSheet } from '@/features/absences/requests/absence-request-sheet'
import { AbsenceRequestsGrid } from '@/features/absences/requests/absence-requests-grid'
import type { AbsenceRequest } from '@/features/absences/types'

/** Query parameter of the notifications' `action_url` (`/absences?request={id}`). */
const REQUEST_PARAM = 'request'

/** Positive integer id from the URL, or null for anything else. */
function parseRequestId(value: string | null): number | null {
  const id = Number(value)
  return Number.isInteger(id) && id > 0 ? id : null
}

/**
 * Absences page (spec 0278): the actor's balances, the requests grid with its
 * scope tabs, the create/edit-draft Sheet and the detail Sheet (also opened by
 * `?request={id}`). Gated by `absence-requests.viewAny`.
 */
export default function AbsencesPage() {
  const { t } = useTranslation()
  const [searchParams, setSearchParams] = useSearchParams()
  const tableRef = useRef<TableViewHandle>(null)
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<AbsenceRequest | undefined>(undefined)

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
      <div className="flex flex-1 flex-col gap-3">
        <PageHeader
          actions={
            <Can permission="absence-requests.create">
              <Button type="button" onClick={openCreate}>
                <Plus aria-hidden="true" />
                {t('absences.page.newRequest')}
              </Button>
            </Can>
          }
        />
        <AbsenceBalancesCard />
        <AbsenceRequestsGrid tableRef={tableRef} onOpen={openDetail} />
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
