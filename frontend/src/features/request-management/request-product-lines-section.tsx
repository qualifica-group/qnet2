import { useTranslation } from 'react-i18next'
import { Boxes } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { MetaField } from '@/features/authorization/MetaField'
import { ProductLinesField } from '@/features/product-lines/product-lines-field'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestProductLine } from '@/features/request-management/types'

interface RequestProductLinesSectionProps {
  control: Control<RequestWorkFormValues>
  /** The persisted rows, whose `{id, name}` projections label the selects without a fetch. */
  productLines: RequestProductLine[]
}

/**
 * "Funzione aziendale" + "categoria prodotto" of an existing request (user
 * directive 2026-07-31): the commercials change them from the work panel, not
 * only while creating the request. Rendered with the SAME `ProductLinesField`
 * the create form and the opportunity form use, wrapped in `MetaField` like
 * every other field here so its gating comes from the server-derived
 * permissions.
 */
export function RequestProductLinesSection({ control, productLines }: RequestProductLinesSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={Boxes}
      title={t('requestManagement.workPanel.productLines.title')}
      description={t('requestManagement.workPanel.productLines.description')}
    >
      <MetaField
        control={control}
        name="product_lines"
        metaKey="product_lines"
        label={t('requestManagement.workPanel.productLines.fieldLabel')}
        hint={t('requestManagement.workPanel.productLines.hint')}
      >
        {({ field, disabled }) => (
          <ProductLinesField
            value={field.value}
            onChange={field.onChange}
            knownLines={productLines}
            disabled={disabled}
          />
        )}
      </MetaField>
    </FormSection>
  )
}
