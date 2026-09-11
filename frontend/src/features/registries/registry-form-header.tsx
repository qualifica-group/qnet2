import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Handshake, Loader2, TriangleAlert, Truck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RECORD_HEADER_CLASS } from '@/components/record-form/layout'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { enumLabelOf } from '@/features/config/enum-label'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

interface RegistryFormHeaderProps {
  control: Control<RegistryFormValues>
  /** Drives the heading pair (create vs edit). */
  isEdit: boolean
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity bar of the anagrafica form — the same bar Opportunità and Gestione
 * Richieste carry (`RECORD_HEADER_CLASS`): heading and live pills on the left,
 * the actions on the right, a refused save reported right under the button
 * that was pressed.
 *
 * The pills are LIVE against the toggles far below, and each is ABSENT when
 * its flag does not hold: the bar states what this anagrafica IS, it does not
 * enumerate what it is not. Every pill is gated by the same field permission
 * as the control it mirrors — a label alone already tells the actor a hidden
 * field exists.
 */
export function RegistryFormHeader({
  control,
  isEdit,
  formId,
  isSubmitting,
  submitError,
  onCancel,
}: RegistryFormHeaderProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const isSupplier = useWatch({ control, name: 'is_supplier' })
  const isQualifiedSupplier = useWatch({ control, name: 'is_qualified_supplier' })
  const agreementStatus = useWatch({ control, name: 'agreement_status' })

  return (
    <header className={RECORD_HEADER_CLASS}>
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
        <div className="flex min-w-0 flex-col">
          <h1 className="min-w-0 truncate text-base font-semibold">
            {t(isEdit ? 'registries.form.editTitle' : 'registries.form.createTitle')}
          </h1>
          <p className="min-w-0 truncate text-sm text-muted-foreground">
            {t(isEdit ? 'registries.form.editSubtitle' : 'registries.form.createSubtitle')}
          </p>
        </div>

        {isSupplier && fieldPermission('is_supplier').visible ? (
          <Badge variant="secondary" className="h-5 min-h-5 gap-1.5">
            <Truck className="size-3" aria-hidden="true" />
            {t('registries.form.isSupplier')}
          </Badge>
        ) : null}

        {isSupplier && isQualifiedSupplier && fieldPermission('is_qualified_supplier').visible ? (
          <Badge variant="outline" className="h-5 min-h-5">
            {t('registries.form.isQualifiedSupplier')}
          </Badge>
        ) : null}

        {agreementStatus && fieldPermission('agreement_status').visible ? (
          <Badge variant="outline" className="h-5 min-h-5 gap-1.5">
            <Handshake className="size-3" aria-hidden="true" />
            {enumLabelOf('agreement_status', agreementStatus)}
          </Badge>
        ) : null}
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-2">
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
          {t('registries.form.cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isSubmitting}>
          {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
          {isSubmitting ? t('registries.form.saving') : t('registries.form.save')}
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
