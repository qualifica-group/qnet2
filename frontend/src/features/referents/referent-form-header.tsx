import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Loader2, Radar, TriangleAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RECORD_HEADER_CLASS } from '@/components/record-form/layout'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { enumLabelOf } from '@/features/config/enum-label'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

interface ReferentFormHeaderProps {
  control: Control<ReferentFormValues>
  /** Drives the heading pair (create vs edit). */
  isEdit: boolean
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity bar of the referente form — the same bar Opportunità, Gestione
 * Richieste and the anagrafica carry (`RECORD_HEADER_CLASS`): heading and the
 * live ambit pill on the left, the actions on the right, a refused save
 * reported right under the button that was pressed.
 *
 * The pill is LIVE against the select far below and is gated by the same field
 * permission as that control: a label alone already tells the actor a hidden
 * field exists.
 */
export function ReferentFormHeader({
  control,
  isEdit,
  formId,
  isSubmitting,
  submitError,
  onCancel,
}: ReferentFormHeaderProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const contactScope = useWatch({ control, name: 'contact_scope' })

  return (
    <header className={RECORD_HEADER_CLASS}>
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
        <div className="flex min-w-0 flex-col">
          <h1 className="min-w-0 truncate text-base font-semibold">
            {t(isEdit ? 'referents.form.editTitle' : 'referents.form.createTitle')}
          </h1>
          <p className="min-w-0 truncate text-sm text-muted-foreground">
            {t(isEdit ? 'referents.form.editSubtitle' : 'referents.form.createSubtitle')}
          </p>
        </div>

        {contactScope && fieldPermission('contact_scope').visible ? (
          <Badge variant="secondary" className="h-5 min-h-5 gap-1.5">
            <Radar className="size-3" aria-hidden="true" />
            {enumLabelOf('referent_contact_scope', contactScope)}
          </Badge>
        ) : null}
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-2">
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
          {t('referents.form.cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isSubmitting}>
          {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
          {isSubmitting ? t('referents.form.saving') : t('referents.form.save')}
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
