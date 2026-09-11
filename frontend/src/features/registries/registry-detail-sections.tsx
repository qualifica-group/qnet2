import { useTranslation } from 'react-i18next'
import { Building2, IdCard, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { enumLabelOf } from '@/features/config/enum-label'
import { PersonalDataIdentityRows } from '@/features/personal-data/personal-data-identity-rows'
import { PersonField, RegistryReferents } from '@/features/registries/registry-detail-people'
import { RegistryTeamSection } from '@/features/registries/registry-detail-team'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

interface RegistryDetailSectionsProps {
  registry: RegistryDetailWithPermissions
}

/**
 * The record's `RecordSectionsGrid` body. The sections MIRROR the form's own
 * (`registries.form.sections.*`), title for title: an operator who reads the
 * card and then opens the form finds the same blocks in the same order,
 * instead of a "Dettagli" bag on one side and three sections on the other.
 *
 * People (referenti + G.A.) take the full width: they are cards, not
 * label/value rows, and they are the block a commercial actually comes here
 * for.
 */
export function RegistryDetailSections({ registry }: RegistryDetailSectionsProps) {
  const { t } = useTranslation()

  return (
    <RecordSectionsGrid>
      {registry.personal_data ? (
        <RecordSection title={t('registries.form.sections.identity.title')} icon={<IdCard />}>
          <PersonalDataIdentityRows card={registry.personal_data} />
        </RecordSection>
      ) : null}

      <RecordSection title={t('registries.form.sections.relations.title')} icon={<Building2 />}>
        <RecordFieldList>
          <RecordField label={t('registries.form.source')}>
            {registry.source?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('registries.form.sectors')}>
            {registry.sectors.length > 0
              ? registry.sectors.map((sector) => sector.name).join(', ')
              : <DetailEmpty />}
          </RecordField>
          <RecordField label={t('registries.form.commercial')}>
            <PersonField person={registry.commercial} />
          </RecordField>
          <RecordField label={t('registries.form.reporter')}>
            <PersonField person={registry.reporter} />
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('registries.form.sections.business.title')} icon={<Building2 />}>
        <RecordFieldList>
          <RecordField label={t('registries.form.vatGroup')}>
            {registry.vat_group || <DetailEmpty />}
          </RecordField>
          <RecordField label={t('registries.form.sizeClass')}>
            {registry.size_class ? enumLabelOf('size_class', registry.size_class) : <DetailEmpty />}
          </RecordField>
          <RecordField label={t('registries.form.employeeCount')}>
            {registry.employee_count ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('registries.form.agreementStatus')}>
            {registry.agreement_status ? (
              enumLabelOf('agreement_status', registry.agreement_status)
            ) : (
              <DetailEmpty />
            )}
          </RecordField>
          <RecordField label={t('registries.form.agreementNotes')}>
            {registry.agreement_notes || <DetailEmpty />}
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      {/* Same block Opportunità and Offerta carry, same component inside it
          (user directive 2026-09-11): "un ruolo, una persona", una riga per
          ciascuno, tutte nella stessa lista. */}
      <RegistryTeamSection
        supervisor={registry.supervisor}
        managers={registry.managers}
        managerSlots={registry.manager_slots}
      />

      <RecordSection
        title={t('registries.form.referents')}
        icon={<Users />}
        className={FULL_WIDTH_SECTION_CLASS}
      >
        <RegistryReferents referents={registry.referents} />
      </RecordSection>
    </RecordSectionsGrid>
  )
}
