import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { Package } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FIELD_GRID_CLASS } from '@/components/record-form/layout'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { FormControl } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { ProductFormValues } from '@/features/products/use-product-form'

/** Placeholder of the manual `code` field, declaring the server-generation fallback (spec 0065, mirrors `ProjectFormBody`). */
const CODE_PLACEHOLDER_KEY = 'products.form.codePlaceholder'

interface ProductIdentitySectionProps {
  control: Control<ProductFormValues>
}

/**
 * What the product IS: code and name side by side on the shared field grid,
 * the description under them at full width because it is prose, not a cell.
 *
 * Renders nothing when the actor may see none of these fields — an empty card
 * reads as missing data rather than as withheld data.
 */
export function ProductIdentitySection({ control }: ProductIdentitySectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()

  const visible =
    fieldPermission('code').visible ||
    fieldPermission('name').visible ||
    fieldPermission('description').visible

  if (!visible) {
    return null
  }

  return (
    <FormSection
      icon={Package}
      title={t('products.form.sections.identity.title')}
      description={t('products.form.sections.identity.description')}
    >
      <div className={FIELD_GRID_CLASS}>
        <MetaField
          control={control}
          name="code"
          metaKey="code"
          label={t('products.form.code')}
          hint={t('products.form.hints.code')}
          hintLabel={t('products.form.code')}
        >
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                autoComplete="off"
                disabled={disabled}
                readOnly={readOnly}
                placeholder={t(CODE_PLACEHOLDER_KEY)}
                {...field}
                value={field.value ?? ''}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="name" metaKey="name" label={t('products.form.name')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>
      </div>

      <MetaField
        control={control}
        name="description"
        metaKey="description"
        label={t('products.form.description')}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Textarea
              disabled={disabled}
              readOnly={readOnly}
              value={field.value ?? ''}
              onChange={(event) => field.onChange(event.target.value || null)}
              onBlur={field.onBlur}
              name={field.name}
              ref={field.ref}
            />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}
