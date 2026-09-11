import { useTranslation } from 'react-i18next'
import { Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RECORD_PERSON_ROW_CLASS, RecordPerson } from '@/components/detail/record-person'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { managerPositionLabel } from '@/features/shared/manager-position-label'
import { PersonContactLines } from '@/features/registries/registry-detail-people'
import type { ManagerRef, ReferenceRef } from '@/features/registries/types'

/**
 * The anagrafica's Team block — Supervisore and the ordered "G.A. n" slots —
 * rendered exactly as Opportunità and Offerta render theirs (user directive
 * 2026-09-11: "così graficamente è coerente"): "one role, one person, one
 * row", all in the SAME `RecordFieldList`, so the label column lines up and
 * the rows share their hairlines.
 *
 * Not a lookalike: it is the SAME `RecordPerson` those two records use, so the
 * avatar, the hover card and the keyboard path to the user Sheet cannot drift
 * between the three screens.
 *
 * Two things are this module's own, and both are data the other records do not
 * have:
 *  - an EMPTY slot stays visible as a muted placeholder. The gap is a
 *    deliberate arrangement (`manager_slots` is gap-aware), not a missing row,
 *    and dropping it would renumber every G.A. below it;
 *  - each person's PRIMARY contacts hang under the name. The anagrafica's
 *    resource carries them (`ReferenceRef.primary_contacts`) and they are why
 *    a commercial opens this card — losing them for the sake of a pixel-exact
 *    match with a record that has none would be a regression.
 *
 * `manager_labels` (spec 0080) do not exist on this module, so
 * `managerPositionLabel` is called with `undefined` and falls back to the
 * shared default — the same string `ManagerSlotsField` shows while editing.
 */

interface RegistryTeamSectionProps {
  supervisor: ReferenceRef | null
  managers: ManagerRef[]
  /** Gap-aware: index+1 = G.A. number, `null` = an empty slot that must stay visible. */
  managerSlots: (number | null)[]
}

export function RegistryTeamSection({ supervisor, managers, managerSlots }: RegistryTeamSectionProps) {
  const { t } = useTranslation()
  const managersById = new Map<number, ManagerRef>(managers.map((manager) => [manager.id, manager]))
  const slots = managerSlots.map((id) => (id === null ? null : (managersById.get(id) ?? null)))

  return (
    <RecordSection title={t('registries.form.sections.team.title')} icon={<Users />}>
      <RecordFieldList>
        <RecordField label={t('registries.form.supervisor')} className={RECORD_PERSON_ROW_CLASS}>
          {supervisor ? <TeamMember person={supervisor} /> : <DetailEmpty />}
        </RecordField>

        {slots.length > 0 ? (
          slots.map((manager, index) => (
            // The slot's identity IS its position: the index is the correct key.
            <RecordField
              key={index}
              label={managerPositionLabel(t, index + 1, undefined)}
              className={manager ? RECORD_PERSON_ROW_CLASS : undefined}
            >
              {manager ? (
                <TeamMember person={manager} />
              ) : (
                <span className="text-sm text-muted-foreground">
                  {t('registries.form.managerSlotEmpty')}
                </span>
              )}
            </RecordField>
          ))
        ) : (
          <RecordField label={t('registries.form.managers')}>
            <DetailEmpty />
          </RecordField>
        )}
      </RecordFieldList>
    </RecordSection>
  )
}

/** The shared person row, plus this module's primary-contact lines under it. */
function TeamMember({ person }: { person: ReferenceRef }) {
  return (
    <div className="flex min-w-0 flex-col gap-0.5">
      <RecordPerson user={person} />
      <PersonContactLines contacts={person.primary_contacts} />
    </div>
  )
}
