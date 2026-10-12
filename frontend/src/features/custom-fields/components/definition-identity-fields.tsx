import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { FormControl, FormDescription } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'
import type { CustomFieldEntity } from '@/features/custom-fields/api'
import { useCustomFieldEntities } from '@/features/custom-fields/use-custom-field-entities'

/**
 * One control per scalar field of a custom field DEFINITION (spec 0231): the
 * editor of a detail row and of its closed twin on the create form. Each is
 * a `MetaField`, so the field permissions decide hidden/disabled/read-only.
 */

const NO_ENTITIES: CustomFieldEntity[] = []

interface FieldProps {
  control: Control<CustomFieldDefinitionFormValues>
}

/** Fetches the module catalogue itself, so it is only requested while the picker is on screen. */
export function DefinitionEntityTypeField({ control }: FieldProps) {
  const { t } = useTranslation()
  const entities = useCustomFieldEntities().data ?? NO_ENTITIES

  return (
    <MetaField
      control={control}
      name="entity_type"
      metaKey="entity_type"
      label={t('customFields.form.entityType')}
      description={<FormDescription>{t('customFields.form.entityTypeHelp')}</FormDescription>}
    >
      {({ field, disabled }) => (
        <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
          <FormControl>
            <SelectTrigger className="w-full">
              <SelectValue placeholder={t('customFields.form.entityTypePlaceholder')} />
            </SelectTrigger>
          </FormControl>
          <SelectContent>
            {entities.map((entity) => (
              <SelectItem key={entity.entity_type} value={entity.entity_type}>
                {t(entity.label)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </MetaField>
  )
}

export function DefinitionKeyField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="key"
      metaKey="key"
      label={t('customFields.form.key')}
      description={<FormDescription>{t('customFields.form.keyHelp')}</FormDescription>}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" placeholder="loyalty_tier" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function DefinitionLabelField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="label"
      metaKey="label"
      label={t('customFields.form.label')}
      description={<FormDescription>{t('customFields.form.labelHelp')}</FormDescription>}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function DefinitionGroupField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="group"
      metaKey="group"
      label={t('customFields.form.group')}
      description={<FormDescription>{t('customFields.form.groupHelp')}</FormDescription>}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
        </FormControl>
      )}
    </MetaField>
  )
}

export function DefinitionSortOrderField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="sort_order"
      metaKey="sort_order"
      label={t('customFields.form.sortOrder')}
      description={<FormDescription>{t('customFields.form.sortOrderHelp')}</FormDescription>}
    >
      {({ field, disabled, readOnly }) => (
        <FormControl>
          <Input
            type="number"
            disabled={disabled}
            readOnly={readOnly}
            value={field.value}
            onChange={(event) => field.onChange(event.target.value === '' ? 0 : Number(event.target.value))}
            onBlur={field.onBlur}
            name={field.name}
            ref={field.ref}
          />
        </FormControl>
      )}
    </MetaField>
  )
}

export function DefinitionIsActiveField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
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
  )
}

/** Hidden by the caller for `table` (the server rejects `is_indexed=true`, spec 0180 AC-004); `true` dispatches `PromoteCustomFieldIndexJob`. */
export function DefinitionIsIndexedField({ control }: FieldProps) {
  const { t } = useTranslation()

  return (
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
  )
}
