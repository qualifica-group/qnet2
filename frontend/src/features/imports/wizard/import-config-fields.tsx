import { type Control, type FieldPath } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { ImportConfigRelationSelect } from '@/features/imports/wizard/import-config-relation-select'
import type { ImportMappingFormValues } from '@/features/imports/wizard/import-mapping-schema'
import type { ImportGlobalFieldDescriptor } from '@/features/imports/wizard/types'

interface ImportConfigFieldsProps {
  globalFields: ImportGlobalFieldDescriptor[]
  control: Control<ImportMappingFormValues>
}

/**
 * The global-configuration controls (AC-021) — one per `global_fields` entry
 * of the run's definition (a relation select when `for_select_resource` is
 * set, a plain number input otherwise). Rendered inside the mapping step's
 * form under the `global_config.<id>` path, so the single mapping submit
 * persists these together with the column mapping and dedup strategy.
 */
export function ImportConfigFields({ globalFields, control }: ImportConfigFieldsProps) {
  // Global-field labels arrive from the backend as default-namespace i18n keys
  // (`imports.leads.global.*`) — resolve them through the default translator.
  const { t: tLabel } = useTranslation()

  return (
    <div className="grid gap-4 sm:grid-cols-2">
      {globalFields.map((globalField) => (
        <FormField
          key={globalField.id}
          control={control}
          name={`global_config.${globalField.id}` as FieldPath<ImportMappingFormValues>}
          render={({ field }) => (
            <FormItem>
              <FormLabel required={globalField.required}>{tLabel(globalField.label)}</FormLabel>
              <FormControl>
                {globalField.for_select_resource ? (
                  <ImportConfigRelationSelect
                    resource={globalField.for_select_resource}
                    value={(field.value as number | null) ?? null}
                    onChange={(next) => field.onChange(next)}
                    triggerLabel={tLabel(globalField.label)}
                  />
                ) : (
                  <Input
                    type="number"
                    value={(field.value as number | null) ?? ''}
                    onChange={(event) =>
                      field.onChange(event.target.value === '' ? null : Number(event.target.value))
                    }
                  />
                )}
              </FormControl>
              <FormMessage role="alert" />
            </FormItem>
          )}
        />
      ))}
    </div>
  )
}
