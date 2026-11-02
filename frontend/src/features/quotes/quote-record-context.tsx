import { useTranslation } from 'react-i18next'
import { Handshake } from 'lucide-react'
import type { UseFormReturn } from 'react-hook-form'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { RecordInlineField, type InlineEdit } from '@/components/record-form/record-inline-field'
import { ProductLinesReadOnlyList } from '@/features/product-lines/product-lines-read-only-list'
import type { ProductLine } from '@/features/product-lines/types'
import { QuoteSourceField } from '@/features/quotes/quote-relation-fields'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'

interface QuoteContextSectionProps {
  source: RelationFieldRef | null
  /** The Opportunita''s classification rows; omitted on a create, where none exist yet. */
  productLines?: ProductLine[]
  form: UseFormReturn<QuoteFormValues>
  inline: InlineEdit
}

/**
 * "Contesto" of the offer record: the Fonte, the offer's own field (spec 0332,
 * editable in place; on create prefilled from the Opportunita''s lead), and
 * the Linee prodotto the Opportunita' classifies, read-only.
 */
export function QuoteContextSection({ source, productLines, form, inline }: QuoteContextSectionProps) {
  const { t } = useTranslation()

  return (
    <RecordSection title={t('quotes.detail.sections.context')} icon={<Handshake />}>
      <RecordFieldList>
        <RecordInlineField
          field="source_id"
          label={t('quotes.detail.source')}
          inline={inline}
          editor={<QuoteSourceField control={form.control} selected={source} />}
        >
          {source ? source.name : <DetailEmpty />}
        </RecordInlineField>
        {productLines ? (
          <RecordField label={t('quotes.detail.productLines')}>
            <ProductLinesReadOnlyList lines={productLines} />
          </RecordField>
        ) : null}
      </RecordFieldList>
    </RecordSection>
  )
}
