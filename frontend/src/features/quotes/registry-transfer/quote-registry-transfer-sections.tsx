import type { Control } from 'react-hook-form'
import { ArrowRight, Building2, FileText, ListChecks, MapPinned } from 'lucide-react'
import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { FormSection } from '@/components/form-section'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { AttributeLayoutField } from '@/features/attributes/attribute-layout-field'
import type { EffectiveAttribute } from '@/features/product-categories/types'
import type { CustomFieldType } from '@/features/custom-fields/types'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import type { QuoteRegistryTransferFormValues } from '@/features/quotes/registry-transfer/schema'

/** Field types that need the whole row of the two-column grid. */
const FULL_WIDTH_TYPES: ReadonlySet<CustomFieldType> = new Set<CustomFieldType>(['textarea', 'table'])

interface SectionProps {
  control: Control<QuoteRegistryTransferFormValues>
  disabled: boolean
}

interface DestinationSectionProps extends SectionProps {
  currentRegistryName: string | null
}

/**
 * "Da -> A": the current anagrafica, read-only, beside the destination picker.
 * The left side uses the same Label + field primitives as the FormItem on the
 * right, so the two labels and the two fields share one baseline and height.
 */
export function QuoteRegistryTransferDestinationSection({
  control,
  disabled,
  currentRegistryName,
}: DestinationSectionProps) {
  const { t } = useTranslation()
  const currentRegistryId = useId()

  return (
    <FormSection
      icon={MapPinned}
      title={t('quotes.registryTransfer.destination')}
      description={t('quotes.registryTransfer.destinationHint')}
    >
      <div className="grid items-start gap-3 md:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
        <div className="grid gap-2">
          <Label htmlFor={currentRegistryId}>{t('quotes.registryTransfer.currentRegistry')}</Label>
          <div className="relative">
            <Building2
              className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-muted-foreground"
              aria-hidden="true"
            />
            <Input
              id={currentRegistryId}
              readOnly
              value={currentRegistryName ?? '—'}
              className="truncate bg-muted/40 pl-8"
            />
          </div>
        </div>

        {/* Label (14px) + gap (8px) + half of the 36px field, minus half the icon: centred on the fields. */}
        <ArrowRight
          className="mx-auto size-4 rotate-90 text-muted-foreground md:mt-8 md:rotate-0"
          aria-hidden="true"
        />

        <FormField
          control={control}
          name="registry_id"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('quotes.registryTransfer.registry')}</FormLabel>
              <FormControl>
                <AsyncPaginatedSelect
                  resource={REGISTRIES_FOR_SELECT_RESOURCE}
                  value={field.value}
                  onChange={field.onChange}
                  disabled={disabled}
                  labels={{
                    placeholder: t('quotes.registryTransfer.registryPlaceholder'),
                    searchPlaceholder: t('quotes.registryTransfer.registrySearch'),
                    empty: t('quotes.registryTransfer.registryEmpty'),
                    error: t('quotes.registryTransfer.registryError'),
                    clearLabel: t('common.clear'),
                    triggerLabel: t('quotes.registryTransfer.registry'),
                    retry: t('common.retry'),
                  }}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
    </FormSection>
  )
}

/** The offer's title (B-D8), prefilled and editable. */
export function QuoteRegistryTransferOfferSection({ control, disabled }: SectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={FileText}
      title={t('quotes.registryTransfer.offer')}
      description={t('quotes.registryTransfer.offerHint')}
    >
      <FormField
        control={control}
        name="title"
        render={({ field }) => (
          <FormItem>
            <FormLabel>{t('quotes.form.title')}</FormLabel>
            <FormControl>
              <Input
                autoComplete="off"
                placeholder={t('quotes.form.titlePlaceholder')}
                disabled={disabled}
                {...field}
              />
            </FormControl>
            <FormDescription>{t('quotes.form.titleHint')}</FormDescription>
            <FormMessage />
          </FormItem>
        )}
      />
    </FormSection>
  )
}

interface AttributesSectionProps extends SectionProps {
  attributes: EffectiveAttribute[]
}

/** The "Informazioni aggiuntive" on two columns; long fields take the whole row. */
export function QuoteRegistryTransferAttributesSection({ control, disabled, attributes }: AttributesSectionProps) {
  const { t } = useTranslation()

  return (
    <FormSection
      icon={ListChecks}
      title={t('quotes.registryTransfer.attributes')}
      description={t('quotes.registryTransfer.attributesHint')}
    >
      <div className="grid gap-x-4 gap-y-3 md:grid-cols-2">
        {attributes.map((attribute) => (
          <div key={attribute.code} className={FULL_WIDTH_TYPES.has(attribute.type) ? 'md:col-span-2' : undefined}>
            <AttributeLayoutField control={control} attribute={attribute} disabled={disabled} readOnly={false} />
          </div>
        ))}
      </div>
    </FormSection>
  )
}
