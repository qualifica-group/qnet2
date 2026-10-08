import { useTranslation } from 'react-i18next'
import { Briefcase, Contact, UserRound, Users } from 'lucide-react'
import type { UseFormReturn } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RECORD_PERSON_ROW_CLASS, RecordPerson } from '@/components/detail/record-person'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { UserProfileSummary } from '@/components/user-profile-hover-card'
import { managerPositionLabel } from '@/features/shared/manager-position-label'
import { quoteRelationLabels } from '@/features/quotes/quote-field-strings'
import { QuoteCommercialField, QuoteManagersField, QuoteSupervisorField } from '@/features/quotes/quote-relation-fields'
import { QuoteReporterField } from '@/features/quotes/quote-reporter-field'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteManagerRef } from '@/features/quotes/types'
import type { RewardAssignmentRef } from '@/features/rewards/types'

/** A referent shown as a link to its record, or the kit's empty placeholder. */
function ReferentLink({ referent }: { referent: RelationFieldRef | null }) {
  return referent ? (
    <RecordLink domain="referents" id={referent.id}>
      {referent.name}
    </RecordLink>
  ) : (
    <DetailEmpty />
  )
}

/** The Opportunita's own anagrafica and referente: read-only, on the detail only. */
interface DerivedContacts {
  registry: RelationFieldRef | null
  referent: RelationFieldRef | null
}

interface QuoteContactsSectionProps {
  /** Absent on create: they are a projection of the persisted record's Opportunita'. */
  derived?: DerivedContacts
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  /** The persisted buoni (detail), seeding the reporter editor's chips; `[]` on create. */
  rewards: RewardAssignmentRef[]
  /** Create only: the buoni picked in the draft, counted under the closed Segnalatore row. */
  rewardCount?: number
  form: UseFormReturn<QuoteFormValues>
  inline: InlineEdit
}

/**
 * "Anagrafica e contatti" (user request 2026-08-31, the same section the
 * Opportunita' record carries): the parent's anagrafica and referente, read
 * only, then the offer's own Commerciale and Segnalatore snapshot (D-3), each
 * in place (spec 0197). The Segnalatore's editor carries its buoni, exactly
 * where the form always kept them: changing the person retargets them
 * server-side (spec 0059 AC-022).
 */
export function QuoteContactsSection({
  derived,
  commercial,
  reporter,
  rewards,
  rewardCount = 0,
  form,
  inline,
}: QuoteContactsSectionProps) {
  const { t } = useTranslation()
  const { control, setValue } = form

  return (
    <RecordSection title={t('quotes.detail.sections.identity')} icon={<Contact />}>
      <RecordFieldList>
        {derived ? (
          <>
            <RecordField label={t('quotes.detail.registry')}>
              {derived.registry ? (
                <RecordLink domain="registries" id={derived.registry.id}>
                  {derived.registry.name}
                </RecordLink>
              ) : (
                <DetailEmpty />
              )}
            </RecordField>
            <RecordField label={t('quotes.detail.referent')}>
              <ReferentLink referent={derived.referent} />
            </RecordField>
          </>
        ) : null}
        <RecordInlineField
          field="commercial_id"
          label={t('quotes.detail.commercial')}
          icon={<Briefcase />}
          inline={inline}
          editor={<QuoteCommercialField control={control} selected={commercial} />}
        >
          <ReferentLink referent={commercial} />
        </RecordInlineField>
        <RecordInlineField
          field="reporter_id"
          label={t('quotes.detail.reporter')}
          icon={<UserRound />}
          inline={inline}
          editor={
            <QuoteReporterField
              control={control}
              setValue={setValue}
              selected={reporter}
              initialRewards={rewards}
              labels={quoteRelationLabels(t)}
            />
          }
        >
          <ReferentLink referent={reporter} />
          {rewardCount > 0 ? (
            <p className="text-xs text-muted-foreground">{t('quotes.detail.rewardsCount', { count: rewardCount })}</p>
          ) : null}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface QuoteTeamSectionProps {
  supervisor: UserProfileSummary | null
  /** The filled G.A. slots, each with its 1-based position; gaps are meaningful. */
  managers: QuoteManagerRef[]
  /** Spec 0080/0087 D-8: the slot denominations resolved from the product categories. */
  managerLabels: Record<string, string> | undefined
  /** D-7: the G.A. mirror the Opportunita's (a persisted offer only). */
  synchronized: boolean
  form: UseFormReturn<QuoteFormValues>
  inline: InlineEdit
}

/**
 * The Team is ONLY who manages the offer (user request 2026-08-31): the
 * Supervisore and the Gestori Account, with the SAME `RecordPerson` every
 * record's team uses. Two in-place rows (spec 0197): the Supervisore, and the
 * ordered "G.A. n" slots — one editor for all of them, since moving a person
 * between slots is one change. Each manager keeps the label its slot takes
 * from the product category (spec 0080), not a generic "G.A. n".
 */
export function QuoteTeamSection({
  supervisor,
  managers,
  managerLabels,
  synchronized,
  form,
  inline,
}: QuoteTeamSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  // Ordered by slot, not by how the server returned them: the position IS the role.
  const sortedManagers = [...managers].sort((a, b) => a.position - b.position)

  return (
    <RecordSection title={t('quotes.form.sections.team.title')} icon={<Users />}>
      <RecordFieldList>
        <RecordInlineField
          field="supervisor_id"
          label={t('quotes.detail.supervisor')}
          inline={inline}
          className={RECORD_PERSON_ROW_CLASS}
          editor={<QuoteSupervisorField control={control} selected={supervisor} />}
        >
          {supervisor ? <RecordPerson user={supervisor} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="manager_slots"
          label={t('quotes.form.managers')}
          inline={inline}
          editor={
            <QuoteManagersField
              control={control}
              selectedItems={managers.map((manager) => ({ id: manager.id, label: manager.name }))}
              synchronized={synchronized}
            />
          }
        >
          {sortedManagers.length > 0 ? (
            <ul className="flex min-w-0 flex-col gap-1.5">
              {sortedManagers.map((manager) => (
                // `position` is the key, not `id`: the slot is unique, the same person may fill two.
                <li key={manager.position} className="flex min-w-0 flex-col gap-0.5">
                  <span className="text-xs text-muted-foreground">
                    {managerPositionLabel(t, manager.position, managerLabels)}
                  </span>
                  <RecordPerson user={manager} />
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
