import { RecordSectionsGrid } from '@/components/detail/record-panel'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { persistedRegistryValues } from '@/features/registries/registry-record'
import { RegistryBusinessRecordSection } from '@/features/registries/registry-record-business'
import { RegistryIdentityRecordSection } from '@/features/registries/registry-record-identity'
import {
  RegistryReferentsRecordSection,
  RegistryRelationsRecordSection,
  RegistryTeamRecordSection,
} from '@/features/registries/registry-record-relations'
import type { RegistryDetailEditor } from '@/features/registries/use-registry-inline-edit'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'

interface RegistryDetailSectionsProps {
  registry: RegistryDetailWithPermissions
  editor: RegistryDetailEditor
}

/**
 * The anagrafica record's `RecordSectionsGrid` body, every user-written field
 * editable in place (spec 0200): the fiscal identity, relations, commercial
 * data, team, the referenti across the full width, then the custom fields
 * ("campi flessibili") grouped as the form groups them. The create form
 * renders the same sections in the same order (`RegistryCreateSections`).
 */
export function RegistryDetailSections({ registry, editor }: RegistryDetailSectionsProps) {
  const { form, inline, card } = editor
  const values = persistedRegistryValues(registry)
  const sectionProps = { values, form, inline }

  return (
    <RecordSectionsGrid>
      <RegistryIdentityRecordSection card={registry.personal_data} buffer={card} inline={inline} />
      <RegistryRelationsRecordSection {...sectionProps} />
      <RegistryBusinessRecordSection {...sectionProps} />
      <RegistryTeamRecordSection {...sectionProps} />
      <RegistryReferentsRecordSection {...sectionProps} />
      <CustomFieldRecordSections
        resource="registries"
        values={values.custom_fields}
        inline={inline}
        control={form.control}
      />
    </RecordSectionsGrid>
  )
}
