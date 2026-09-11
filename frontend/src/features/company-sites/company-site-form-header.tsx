import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Loader2, TriangleAlert } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RECORD_HEADER_CLASS } from '@/components/record-form/layout'

interface CompanySiteFormHeaderProps {
  /** Drives the heading pair (create vs edit). */
  isEdit: boolean
  /** True when the edited site is already the organization's default one. */
  isDefault: boolean
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  /**
   * Domain action rendered before Cancel — here the "Società di Default"
   * button. It is repeated at the foot of the form via
   * `RecordFormActions.leadingActions`, the slot that exists for exactly this.
   */
  leadingActions?: ReactNode
  onCancel: () => void
}

/**
 * Identity bar of the company-site form — the same bar Opportunità, Gestione
 * Richieste and the anagrafiche carry (`RECORD_HEADER_CLASS`): heading and the
 * live "default" pill on the left, actions on the right, a refused save
 * reported right under the button that was pressed.
 *
 * The pill is ABSENT when the site is not the default: the bar states what this
 * site IS, it does not enumerate what it is not — the same rule
 * `RegistryFormHeader` follows.
 */
export function CompanySiteFormHeader({
  isEdit,
  isDefault,
  formId,
  isSubmitting,
  submitError,
  leadingActions,
  onCancel,
}: CompanySiteFormHeaderProps) {
  const { t } = useTranslation()

  return (
    <header className={RECORD_HEADER_CLASS}>
      <div className="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-1">
        <div className="flex min-w-0 flex-col">
          <h1 className="min-w-0 truncate text-base font-semibold">
            {t(isEdit ? 'companySites.form.editTitle' : 'companySites.form.createTitle')}
          </h1>
          <p className="min-w-0 truncate text-sm text-muted-foreground">
            {t(isEdit ? 'companySites.form.editSubtitle' : 'companySites.form.createSubtitle')}
          </p>
        </div>

        {isDefault ? (
          <Badge variant="secondary" className="h-5 min-h-5">
            {t('companySites.detail.defaultBadge')}
          </Badge>
        ) : null}
      </div>

      <div className="ml-auto flex shrink-0 items-center gap-2">
        {leadingActions}
        <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
          {t('companySites.form.cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isSubmitting}>
          {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
          {isSubmitting ? t('companySites.form.saving') : t('companySites.form.save')}
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
