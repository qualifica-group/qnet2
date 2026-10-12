import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Loader2, Radar, TriangleAlert, UserRound } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordCardHeader } from '@/components/detail/record-panel'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { enumLabelOf } from '@/features/config/enum-label'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import { ReferentStatsStrip } from '@/features/referents/referent-detail-header'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

/** The name the server will derive from the card (ADR 0012): the company name, or first and last name. */
function draftName(draft: PersonalDataDraft): string {
  const name = draft.type === 'company' ? draft.company_name : [draft.first_name, draft.last_name].filter(Boolean).join(' ')
  return name?.trim() ?? ''
}

interface ReferentFormHeaderProps {
  control: Control<ReferentFormValues>
  /** The buffered card: it names the referente being created and holds its contacts/addresses. */
  profileDraft: PersonalDataDraft
  /** id of the RHF `<form>` the save action attaches to via the HTML `form=` attribute. */
  formId: string
  isSubmitting: boolean
  submitError: string | null
  onCancel: () => void
}

/**
 * Identity band + KPI strip of the referente create form, the detail's own
 * (`ReferentDetailHeader` + `ReferentStatsStrip`, spec 0216): monogram and name
 * follow the card being typed, the ambit pill and the strip follow the draft,
 * the actions sit where the detail has none. A refused save is reported right
 * under the actions.
 *
 * The ambit pill is gated by the same field permission as its control — a
 * label alone already tells the actor a hidden field exists.
 */
export function ReferentFormHeader({
  control,
  profileDraft,
  formId,
  isSubmitting,
  submitError,
  onCancel,
}: ReferentFormHeaderProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const contactScope = useWatch({ control, name: 'contact_scope' })
  const heading = draftName(profileDraft) || t('referents.form.createTitle')

  return (
    <>
      <RecordCardHeader
        media={<DetailMonogram name={heading} icon={<UserRound />} className="size-10 text-base [&>svg]:size-5" />}
        title={heading}
        subtitle={t('referents.form.createSubtitle')}
        badges={
          contactScope && fieldPermission('contact_scope').visible ? (
            <Badge variant="secondary" className="gap-1.5">
              <Radar aria-hidden="true" />
              {enumLabelOf('referent_contact_scope', contactScope)}
            </Badge>
          ) : null
        }
        actions={
          <>
            <Button type="button" variant="secondary" onClick={onCancel} disabled={isSubmitting}>
              {t('referents.form.cancel')}
            </Button>
            <Button type="submit" form={formId} disabled={isSubmitting}>
              {isSubmitting ? <Loader2 className="size-4 animate-spin" aria-hidden="true" /> : null}
              {isSubmitting ? t('referents.form.saving') : t('referents.form.save')}
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

      <ReferentStatsStrip
        contacts={profileDraft.contacts.length}
        addresses={profileDraft.addresses.length}
        cardType={profileDraft.type}
      />
    </>
  )
}
