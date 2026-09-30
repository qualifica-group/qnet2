import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Database } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { Switch } from '@/components/ui/switch'
import { FormControl, FormDescription } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import type { CustomFieldType } from '@/features/custom-fields/types'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'

interface DefinitionFlagsFieldsProps {
  control: Control<CustomFieldDefinitionFormValues>
  type: CustomFieldType
}

/** `is_indexed`/`is_active` toggles (spec AC-025); `is_indexed` is hidden for `table` (server rejects it, spec 0180 AC-004). Flipping `is_indexed` to `true` dispatches `PromoteCustomFieldIndexJob` server-side. */
export function DefinitionFlagsFields({ control, type }: DefinitionFlagsFieldsProps) {
  const { t } = useTranslation()

  return (
    <FormSection icon={Database} title={t('customFields.form.sections.flags.title')}>
      <MetaField
        control={control}
        name="is_active"
        metaKey="is_active"
        label={t('customFields.form.isActive')}
        description={<FormDescription>{t('customFields.form.isActiveHint')}</FormDescription>}
      >
        {({ field, disabled }) => (
          <FormControl>
            <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
          </FormControl>
        )}
      </MetaField>

      {type !== 'table' && (
        <MetaField
          control={control}
          name="is_indexed"
          metaKey="is_indexed"
          label={t('customFields.form.isIndexed')}
          description={<FormDescription>{t('customFields.form.isIndexedHint')}</FormDescription>}
        >
          {({ field, disabled }) => (
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
            </FormControl>
          )}
        </MetaField>
      )}
    </FormSection>
  )
}
