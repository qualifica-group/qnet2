import { useTranslation } from 'react-i18next'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import { Handshake } from 'lucide-react'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { Can } from '@/features/auth/can'
import { LeadConvertField } from '@/features/leads/lead-fields'
import {
  LeadContactRecordSection,
  LeadDetailsRecordSection,
  LeadExtraFieldsRecordSection,
  LeadNotesRow,
  LeadProductsRecordSection,
} from '@/features/leads/lead-record-sections'
import type { LeadRecordValues } from '@/features/leads/lead-record'
import type { LeadCascades } from '@/features/leads/use-lead-cascades'
import type { LeadFormValues } from '@/features/leads/use-lead-form'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

interface LeadCreateSectionsProps {
  /** The draft as the closed rows read it (`useLeadDraftValues`, shared with the header). */
  values: LeadRecordValues
  form: UseFormReturn<LeadFormValues>
  draft: InlineEdit
  cascades: LeadCascades
}

/**
 * "Conversione in opportunita'" (spec 0044): create only, and only for an
 * actor who may create opportunities — the flag defaults ON for them.
 */
function LeadConversionRecordSection({ form, draft }: Pick<LeadCreateSectionsProps, 'form' | 'draft'>) {
  const { t } = useTranslation()
  const convert = useWatch({ control: form.control, name: 'convert_to_opportunity' })

  return (
    <Can permission="opportunities.create">
      <RecordSection
        title={t('leads.form.sections.conversion.title')}
        icon={<Handshake />}
        className={FULL_WIDTH_SECTION_CLASS}
      >
        <RecordFieldList>
          <RecordInlineField
            field="convert_to_opportunity"
            label={t('leads.form.convertToOpportunity')}
            inline={draft}
            editor={<LeadConvertField control={form.control} />}
          >
            {convert ? t('common.yes') : t('common.no')}
          </RecordInlineField>
        </RecordFieldList>
      </RecordSection>
    </Can>
  )
}

/**
 * The create form as a replica of the lead detail (spec 0217 D-4): the
 * detail's sections, rows, labels and order, every row CLOSED until clicked
 * and opening on the same component the detail edits in place — cascades
 * included — with the conversion choice in front. Nothing is saved per row:
 * the header's Salva validates and creates the whole draft.
 */
export function LeadCreateSections({ values, form, draft, cascades }: LeadCreateSectionsProps) {
  const sectionProps = { values, form, inline: draft, cascades }

  return (
    <RecordSectionsGrid>
      <LeadConversionRecordSection form={form} draft={draft} />
      <LeadNotesRow {...sectionProps} className={FULL_WIDTH_SECTION_CLASS} />
      <LeadContactRecordSection {...sectionProps} />
      <LeadDetailsRecordSection {...sectionProps} />
      <LeadProductsRecordSection {...sectionProps} />
      <LeadExtraFieldsRecordSection {...sectionProps} />
    </RecordSectionsGrid>
  )
}
