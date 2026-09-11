import { useTranslation } from 'react-i18next'
import { Loader2, TriangleAlert } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { RECORD_HEADER_CLASS } from '@/components/record-form/layout'

interface CompanyFormHeaderProps {
  /** Drives the heading pair (create vs edit). */
  isEdit: boolean
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity bar of the company form — the same bar Opportunità, Gestione
 * Richieste and the anagrafiche carry (`RECORD_HEADER_CLASS`): heading on the
 * left, actions on the right, a refused save reported right under the button
 * that was pressed.
 */
export function CompanyFormHeader({
  isEdit,
  formId,
  isSubmitting,
  submitError,
  onCancel,
}: CompanyFormHeaderProps) {
  const { t } = useTranslation()

  return (
    <header className={RECORD_HEADER_CLASS}>
      <div className="flex min-w-0 flex-1 flex-col">
        <h1 className="min-w-0 truncate text-base font-semibold">
          {t(isEdit ? 'companies.form.editTitle' : 'companies.form.createTitle')}
        </h1>
        <p className="min-w-0 truncate text-sm text-muted-foreground">
          {t(isEdit ? 'companies.form.editSubtitle' : 'companies.form.createSubtitle')}
        </p>
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-2">
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
          {t('companies.form.cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isSubmitting}>
          {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
          {isSubmitting ? t('companies.form.saving') : t('companies.form.save')}
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
