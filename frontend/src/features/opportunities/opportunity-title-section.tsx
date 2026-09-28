import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { MetaField } from '@/features/authorization/MetaField'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityTitleSectionProps {
  control: Control<OpportunityFormValues>
}

/**
 * The opportunity title (spec 0171) as the form's lead card, the same
 * prominent input the task form opens with. Prefilled with the current title
 * in edit; blank in create, where no quote exists yet to derive one from.
 * Blank always means "automatic": the server derives it from the quoted
 * products and keeps it in sync until the user types their own.
 */
export function OpportunityTitleSection({ control }: OpportunityTitleSectionProps) {
  const { t } = useTranslation()

  return (
    <section className="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-sm">
      <MetaField
        control={control}
        name="name"
        metaKey="name"
        label={t('opportunities.form.name')}
        hint={t('opportunities.form.nameHint')}
      >
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Input
              autoComplete="off"
              placeholder={t('opportunities.form.namePlaceholder')}
              className="h-10 text-base font-semibold md:text-base"
              disabled={disabled}
              readOnly={readOnly}
              {...field}
            />
          </FormControl>
        )}
      </MetaField>
    </section>
  )
}
