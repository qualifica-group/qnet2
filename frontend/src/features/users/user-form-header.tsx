import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { CircleCheck, Loader2, TriangleAlert, UserCog } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RECORD_HEADER_CLASS } from '@/components/record-form/layout'
import { useResourcePermissions } from '@/features/authorization/permissions'
import {
  summarizeAssignment,
  useAssignmentFieldsVisibility,
} from '@/features/users/user-assignment'
import type { UserFormValues } from '@/features/users/use-user-form'

interface UserFormHeaderProps {
  control: Control<UserFormValues>
  /** Drives the heading pair (create vs edit). */
  isEdit: boolean
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity bar of the user form — the same bar Gestione Richieste and
 * Opportunità carry (`RECORD_HEADER_CLASS`): heading and live pills on the
 * left, the actions on the right, a refused save reported right under the
 * button that was pressed.
 *
 * The pills are LIVE against the fields below, so they never contradict what
 * is being typed. The assignment pill is the one that earns its place here
 * rather than only in the side column: the column is sticky at `@4xl` but
 * scrolls away on a narrow panel, and "this person will receive nothing" is
 * the one verdict that must stay on screen while the rest of the form is
 * filled. The REASON is not repeated here — that is the side column's band.
 */
export function UserFormHeader({
  control,
  isEdit,
  formId,
  isSubmitting,
  submitError,
  onCancel,
}: UserFormHeaderProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const assignmentFields = useAssignmentFieldsVisibility()
  const isActive = useWatch({ control, name: 'is_active' })
  const isManager = useWatch({ control, name: 'employment.is_manager' })
  const competenceRows = useWatch({ control, name: 'employment.product_lines' })
  const primarySiteId = useWatch({ control, name: 'employment.primary_operational_site_id' })
  const remoteSiteIds = useWatch({ control, name: 'employment.remote_operational_site_ids' })

  const { assignable } = summarizeAssignment({ competenceRows, primarySiteId, remoteSiteIds })

  return (
    <header className={RECORD_HEADER_CLASS}>
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
        <div className="flex min-w-0 flex-col">
          <h1 className="min-w-0 truncate text-base font-semibold">
            {t(isEdit ? 'users.form.editTitle' : 'users.form.createTitle')}
          </h1>
          <p className="min-w-0 truncate text-sm text-muted-foreground">
            {t(isEdit ? 'users.form.editSubtitle' : 'users.form.createSubtitle')}
          </p>
        </div>

        {fieldPermission('is_active').visible ? (
          <Badge variant={isActive ? 'secondary' : 'outline'} className="h-5 min-h-5">
            {t(isActive ? 'users.form.is_active' : 'users.form.header.inactive')}
          </Badge>
        ) : null}

        {isManager && fieldPermission('employment.is_manager').visible ? (
          <Badge variant="outline" className="h-5 min-h-5 gap-1.5">
            <UserCog className="size-3" aria-hidden="true" />
            {t('users.form.employment.isManager')}
          </Badge>
        ) : null}

        {assignmentFields.any ? (
          <Badge
            variant="outline"
            className={
              assignable
                ? 'h-5 min-h-5 gap-1.5 border-emerald-500/40 text-emerald-700 dark:text-emerald-400'
                : 'h-5 min-h-5 gap-1.5 border-amber-500/40 text-amber-700 dark:text-amber-400'
            }
          >
            {assignable ? (
              <CircleCheck className="size-3" aria-hidden="true" />
            ) : (
              <TriangleAlert className="size-3" aria-hidden="true" />
            )}
            {t(assignable ? 'users.assignment.assignable' : 'users.assignment.notAssignable')}
          </Badge>
        ) : null}
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-2">
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
          {t('users.form.cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isSubmitting}>
          {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
          {isSubmitting ? t('users.form.saving') : t('users.form.save')}
        </Button>
      </div>

      {submitError && (
        <div
          role="alert"
          className="flex w-full items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      )}
    </header>
  )
}
