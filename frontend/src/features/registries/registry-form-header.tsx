import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Building2, Handshake, Loader2, TriangleAlert, Truck } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { enumLabelOf } from '@/features/config/enum-label'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { RegistryStatsStrip } from '@/features/registries/registry-detail-header'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/** The name the server will derive from the card (ADR 0012): the company name, or first and last name. */
function draftName(draft: PersonalDataDraft): string {
  const name = draft.type === 'company' ? draft.company_name : [draft.first_name, draft.last_name].filter(Boolean).join(' ')
  return name?.trim() ?? ''
}

interface RegistryFormHeaderProps {
  control: Control<RegistryFormValues>
  /** The buffered card: it names the anagrafica being created. */
  profileDraft: PersonalDataDraft
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the anagrafica create form, the detail's own
 * (`RegistryDetailHeader` + `RegistryStatsStrip`, spec 0200): monogram and name
 * follow the card being typed, the pills and the strip follow the rows below,
 * the actions sit where the detail has none. A refused save is reported right
 * under the actions.
 *
 * Every pill is gated by the same field permission as the control it mirrors —
 * a label alone already tells the actor a hidden field exists.
 */
export function RegistryFormHeader({
  control,
  profileDraft,
  formId,
  isSubmitting,
  submitError,
  onCancel,
}: RegistryFormHeaderProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const [isSupplier, isQualifiedSupplier, agreementStatus, referentIds, managerSlots, sectorIds, employeeCount] =
    useWatch({
      control,
      name: [
        'is_supplier',
        'is_qualified_supplier',
        'agreement_status',
        'referent_ids',
        'manager_slots',
        'sector_ids',
        'employee_count',
      ],
    })
  const heading = draftName(profileDraft) || t('registries.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<Building2 />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={t('registries.form.createSubtitle')}
        badges={
          <>
            {isSupplier && fieldPermission('is_supplier').visible ? (
              <Badge variant="secondary" className="gap-1.5">
                <Truck aria-hidden="true" />
                {t('registries.form.isSupplier')}
              </Badge>
            ) : null}
            {isSupplier && isQualifiedSupplier && fieldPermission('is_qualified_supplier').visible ? (
              <Badge variant="outline">{t('registries.form.isQualifiedSupplier')}</Badge>
            ) : null}
            {agreementStatus && fieldPermission('agreement_status').visible ? (
              <Badge variant="outline" className="gap-1.5">
                <Handshake aria-hidden="true" />
                {enumLabelOf('agreement_status', agreementStatus)}
              </Badge>
            ) : null}
          </>
        }
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('registries.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('registries.form.saving') : t('registries.form.save')}
            </Button>
          </>
        }
      />

      {submitError ? (
        <div
          role="alert"
          className="mx-4 mt-3 flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm font-medium text-destructive"
        >
          <TriangleAlert className="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
          {submitError}
        </div>
      ) : null}

      <RegistryStatsStrip
        referents={referentIds.length}
        // An empty slot is a deliberate gap, not a manager: only the filled ones count.
        managers={managerSlots.filter((slot) => slot !== null).length}
        sectors={sectorIds.length}
        employees={employeeCount}
      />
    </>
  )
}
