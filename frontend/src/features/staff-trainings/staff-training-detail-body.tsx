import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { StaffTrainingAttachment } from '@/features/staff-trainings/staff-training-attachment'
import { StaffTrainingOutcomeBadge } from '@/features/staff-trainings/staff-training-outcome-badge'
import type { StaffTrainingActions } from '@/features/staff-trainings/use-staff-training-actions'
import type { StaffTraining } from '@/features/staff-trainings/types'
import { formatDate, formatDateTime } from '@/lib/formatting/date-display'

interface FieldProps {
  label: string
  children: ReactNode
}

function Field({ label, children }: FieldProps) {
  return (
    <div className="min-w-0">
      <dt className="text-xs text-muted-foreground">{label}</dt>
      <dd className="break-words text-sm">{children}</dd>
    </div>
  )
}

interface StaffTrainingDetailBodyProps {
  training: StaffTraining
  actions: StaffTrainingActions
  canManage: boolean
}

/** Read-only data of a training: who, which course, outcome, the computed expiry, certificate and who recorded it. */
export function StaffTrainingDetailBody({ training, actions, canManage }: StaffTrainingDetailBodyProps) {
  const { t } = useTranslation()
  const prefix = 'staffTrainings.detail'

  return (
    <dl className="grid gap-3 sm:grid-cols-2">
      <Field label={t(`${prefix}.person`)}>{training.user.name}</Field>
      <Field label={t(`${prefix}.course`)}>{training.course.name}</Field>
      <Field label={t(`${prefix}.completedOn`)}>{formatDate(training.completed_on)}</Field>
      <Field label={t(`${prefix}.outcome`)}>
        <StaffTrainingOutcomeBadge outcome={training.outcome} />
      </Field>
      <Field label={t(`${prefix}.expiresOn`)}>
        {training.expires_on ? formatDate(training.expires_on) : t(`${prefix}.neverExpires`)}
      </Field>
      <Field label={t(`${prefix}.validity`)}>
        {training.course.validity_months === null
          ? t(`${prefix}.neverExpires`)
          : t(`${prefix}.validityMonths`, { count: training.course.validity_months })}
      </Field>
      <Field label={t(`${prefix}.recordedBy`)}>
        {training.recorded_by.name} ({formatDateTime(training.created_at)})
      </Field>
      {training.notes ? (
        <div className="sm:col-span-2">
          <Field label={t(`${prefix}.notes`)}>
            <span className="whitespace-pre-wrap">{training.notes}</span>
          </Field>
        </div>
      ) : null}
      <div className="sm:col-span-2">
        <Field label={t(`${prefix}.attachment`)}>
          <StaffTrainingAttachment training={training} actions={actions} canManage={canManage} />
        </Field>
      </div>
    </dl>
  )
}
