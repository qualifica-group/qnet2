import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { ComplianceStatusBadge } from '@/features/staff-trainings/compliance-status'
import type { ComplianceRow } from '@/features/staff-trainings/types'
import { formatDate } from '@/lib/formatting/date-display'

interface StaffTrainingComplianceTableProps {
  rows: ComplianceRow[]
  /** Opens the training behind a row; only rows with a `training_id` are clickable. */
  onOpen: (trainingId: number) => void
}

const CELL_CLASS = 'px-3 py-2 text-left align-middle'

/**
 * Person x course table. Wide content scrolls inside its own container, never the page
 * (375 px); the row's course cell becomes a button when a training backs the row.
 */
export function StaffTrainingComplianceTable({ rows, onOpen }: StaffTrainingComplianceTableProps) {
  const { t } = useTranslation()
  const prefix = 'staffTrainings.compliance.columns'

  return (
    <div className="overflow-x-auto rounded-lg border border-border bg-card">
      <table className="w-full min-w-[32rem] text-xs">
        <caption className="sr-only">{t('staffTrainings.compliance.caption')}</caption>
        <thead className="bg-surface">
          <tr>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.person`)}</th>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.course`)}</th>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.status`)}</th>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.completedOn`)}</th>
            <th scope="col" className={CELL_CLASS}>{t(`${prefix}.expiresOn`)}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={`${row.user.id}-${row.course.id}`} className="border-t border-border">
              <td className={CELL_CLASS}>{row.user.name}</td>
              <td className={CELL_CLASS}>
                <span className="flex flex-wrap items-center gap-1.5">
                  {row.training_id === null ? (
                    <span>{row.course.name}</span>
                  ) : (
                    <button
                      type="button"
                      className="rounded-sm text-left font-medium underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                      aria-label={t('staffTrainings.compliance.openTraining', {
                        person: row.user.name,
                        course: row.course.name,
                      })}
                      onClick={() => onOpen(row.training_id as number)}
                    >
                      {row.course.name}
                    </button>
                  )}
                  {row.required ? <Badge variant="outline">{t('staffTrainings.compliance.required')}</Badge> : null}
                </span>
              </td>
              <td className={CELL_CLASS}>
                <ComplianceStatusBadge status={row.status} />
              </td>
              <td className={CELL_CLASS}>{row.completed_on ? formatDate(row.completed_on) : '—'}</td>
              <td className={CELL_CLASS}>{row.expires_on ? formatDate(row.expires_on) : '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
