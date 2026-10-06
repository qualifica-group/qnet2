import type { UseFormReturn } from 'react-hook-form'
import { RecordSectionsGrid } from '@/components/detail/record-panel'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import { anagraphicSectionProps } from '@/features/personal-data/use-reveal-blocked-section'
import { RegistryBusinessRecordSection } from '@/features/registries/registry-record-business'
import { RegistryIdentityCreateSection, type RegistryCardBuffer } from '@/features/registries/registry-record-identity'
import {
  RegistryReferentsRecordSection,
  RegistryRelationsRecordSection,
  RegistryTeamRecordSection,
} from '@/features/registries/registry-record-relations'
import { useRegistryDraftValues } from '@/features/registries/use-registry-draft-values'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

interface RegistryCreateSectionsProps {
  form: UseFormReturn<RegistryFormValues>
  draft: InlineEdit
  card: RegistryCardBuffer
}

/**
 * The create form as a replica of the anagrafica detail (spec 0200 D-5): the
 * detail's sections, rows, labels and order, every row CLOSED until clicked
 * and opening on the same field component the detail edits in place. Nothing
 * is saved per row: the header's Salva validates and creates the whole draft.
 *
 * The "Dati anagrafici" stay open: the card names the anagrafica, and it is
 * the one block every create has to fill.
 */
export function RegistryCreateSections({ form, draft, card }: RegistryCreateSectionsProps) {
  const values = useRegistryDraftValues(form.control)
  const sectionProps = { values, form, inline: draft }

  return (
    <RecordSectionsGrid>
      {/* The grid item itself, so a refused save can scroll it into view. */}
      <div {...anagraphicSectionProps('card')} className="flex min-w-0 flex-col">
        <RegistryIdentityCreateSection buffer={card} />
      </div>
      <RegistryRelationsRecordSection {...sectionProps} />
      <RegistryBusinessRecordSection {...sectionProps} />
      <RegistryTeamRecordSection {...sectionProps} />
      <RegistryReferentsRecordSection {...sectionProps} />
      <CustomFieldRecordSections
        resource="registries"
        values={values.custom_fields}
        inline={draft}
        control={form.control}
      />
    </RecordSectionsGrid>
  )
}
