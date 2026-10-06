import { useTranslation } from 'react-i18next'
import { Building2, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RECORD_PERSON_ROW_CLASS, RecordPerson } from '@/components/detail/record-person'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { managerPositionLabel } from '@/features/shared/manager-position-label'
import { PersonContactLines, PersonField, RegistryReferents } from '@/features/registries/registry-detail-people'
import {
  RegistryManagersField,
  RegistryReferentRoleField,
  RegistryReferentsField,
  RegistrySectorsField,
  RegistrySourceField,
  RegistrySupervisorField,
} from '@/features/registries/registry-relation-fields'
import type { RegistryRecordSectionProps } from '@/features/registries/registry-record'
import type { ManagerRef, ReferenceRef } from '@/features/registries/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/**
 * "Relazioni" of the anagrafica record (spec 0200): where it came from and
 * its commercial people, every row editing in place on its own control.
 * Commerciale and Segnalatore are REFERENTI, not users: they stay here, out
 * of the Team.
 */
export function RegistryRelationsRecordSection({ values, form, inline }: RegistryRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('registries.form.sections.relations.title')} icon={<Building2 />}>
      <RecordFieldList>
        <RecordInlineField
          field="source_id"
          label={t('registries.form.source')}
          inline={inline}
          editor={<RegistrySourceField control={control} selected={values.source} />}
        >
          {values.source?.name ?? <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="sector_ids"
          label={t('registries.form.sectors')}
          inline={inline}
          editor={<RegistrySectorsField control={control} selected={values.sectors} />}
        >
          {values.sectors.length > 0 ? values.sectors.map((sector) => sector.name).join(', ') : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="commercial_id"
          label={t('registries.form.commercial')}
          inline={inline}
          editor={
            <RegistryReferentRoleField
              control={control}
              name="commercial_id"
              label={t('registries.form.commercial')}
              placeholder={t('registries.form.commercialPlaceholder')}
              selected={values.commercial}
            />
          }
        >
          <PersonField person={values.commercial} />
        </RecordInlineField>
        <RecordInlineField
          field="reporter_id"
          label={t('registries.form.reporter')}
          inline={inline}
          editor={
            <RegistryReferentRoleField
              control={control}
              name="reporter_id"
              label={t('registries.form.reporter')}
              placeholder={t('registries.form.reporterPlaceholder')}
              selected={values.reporter}
            />
          }
        >
          <PersonField person={values.reporter} />
        </RecordInlineField>
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

/**
 * The G.A. slots as the closed row lists them: each "G.A. n" above its
 * person. An EMPTY slot stays visible as a muted placeholder — the gap is a
 * deliberate arrangement (`manager_slots` is gap-aware), and dropping it would
 * renumber every G.A. below it.
 */
function ManagerSlotsList({ slots, managers }: { slots: (number | null)[]; managers: ManagerRef[] }) {
  const { t } = useTranslation()
  const managersById = new Map<number, ManagerRef>(managers.map((manager) => [manager.id, manager]))

  if (slots.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex min-w-0 flex-col gap-1.5">
      {slots.map((id, index) => {
        const manager = id === null ? null : (managersById.get(id) ?? null)
        return (
          // The slot's identity IS its position: the index is the correct key.
          <li key={index} className="flex min-w-0 flex-col gap-0.5">
            <span className="text-xs text-muted-foreground">{managerPositionLabel(t, index + 1, undefined)}</span>
            {manager ? (
              <TeamMember person={manager} />
            ) : (
              <span className="text-sm text-muted-foreground">{t('registries.form.managerSlotEmpty')}</span>
            )}
          </li>
        )
      })}
    </ul>
  )
}

/**
 * "Team" of the anagrafica record (spec 0200), with the SAME `RecordPerson`
 * Opportunita' and Offerta use: the Supervisore, then the G.A. slots as ONE
 * row — one editor for all of them, since moving a person between slots is
 * one change. Each person keeps their primary contacts under the name.
 */
export function RegistryTeamRecordSection({ values, form, inline }: RegistryRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form

  return (
    <RecordSection title={t('registries.form.sections.team.title')} icon={<Users />}>
      <RecordFieldList>
        <RecordInlineField
          field="supervisor_id"
          label={t('registries.form.supervisor')}
          inline={inline}
          className={RECORD_PERSON_ROW_CLASS}
          editor={<RegistrySupervisorField control={control} selected={values.supervisor} />}
        >
          {values.supervisor ? <TeamMember person={values.supervisor} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="manager_slots"
          label={t('registries.form.managers')}
          inline={inline}
          editor={<RegistryManagersField control={control} selected={values.managers} />}
        >
          <ManagerSlotsList slots={values.manager_slots} managers={values.managers} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Referenti" across the record's full width: they are cards, not label/value
 * rows, and the block a commercial actually comes here for. One editor for
 * the whole set, named by the section title.
 */
export function RegistryReferentsRecordSection({ values, form, inline }: RegistryRecordSectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('registries.form.referents')} icon={<Users />} className={FULL_WIDTH_SECTION_CLASS}>
      <RecordInlineField
        field="referent_ids"
        label={t('registries.form.referents')}
        inline={inline}
        layout="block"
        editor={<RegistryReferentsField control={form.control} selected={values.referents} />}
      >
        <RegistryReferents referents={values.referents} />
      </RecordInlineField>
    </RecordSection>
  )
}
