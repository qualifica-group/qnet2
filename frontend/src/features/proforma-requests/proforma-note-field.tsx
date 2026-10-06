import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import type { ProformaNoteFormValues } from '@/features/proforma-requests/proforma-note-schema'

interface ProformaNoteFieldProps {
  control: Control<ProformaNoteFormValues>
}

/**
 * "Note per la Contabilita'" textarea. `FormControl`/`FormMessage` wire the
 * accessible error triad (`aria-invalid`, `aria-describedby`, `role="alert"`).
 */
export function ProformaNoteField({ control }: ProformaNoteFieldProps) {
  const { t } = useTranslation()

  return (
    <FormField
      control={control}
      name="note"
      render={({ field }) => (
        <FormItem>
          <FormLabel>
            {t('proformaRequests.form.note')}
            <span className="ml-1 text-destructive" aria-hidden="true">
              *
            </span>
          </FormLabel>
          <FormControl>
            <Textarea rows={5} required {...field} />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  )
}
