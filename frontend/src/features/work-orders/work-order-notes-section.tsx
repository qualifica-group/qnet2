import { FileText } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'

interface WorkOrderNotesSectionProps {
  control: Control<WorkOrderFormValues>
}

/** "Descrizione e note" (`description`/`internal_notes`): both free-text, both nullable. */
export function WorkOrderNotesSection({ control }: WorkOrderNotesSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()

  if (!fieldPermission('description').visible && !fieldPermission('internal_notes').visible) {
    return null
  }

  return (
    <FormSection
      icon={FileText}
      title={t('workOrders.form.sections.notes.title')}
      description={t('workOrders.form.sections.notes.description')}
    >
      <MetaField control={control} name="description" metaKey="description" label={t('workOrders.form.description')}>
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

      <MetaField
        control={control}
        name="internal_notes"
        metaKey="internal_notes"
        label={t('workOrders.form.internalNotes')}
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
