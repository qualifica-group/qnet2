import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { AttributeLayoutField } from '@/features/attributes/attribute-layout-field'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import {
  buildQuoteRegistryTransferSchema,
  type QuoteRegistryTransferFormValues,
} from '@/features/quotes/registry-transfer/schema'
import { useQuoteRegistryTransfer } from '@/features/quotes/registry-transfer/use-quote-registry-transfer'
import type { ApplicableAttributeSummary } from '@/features/quotes/types'

const FORM_ID = 'quote-registry-transfer-form'

export interface QuoteRegistryTransferDialogProps {
  /** `/quotes` or the request module's base path: decides which permission set authorizes. */
  basePath: string
  quoteId: number
  currentRegistry: { id: number; name: string } | null
  /** The offer's "Informazioni aggiuntive" and their values, prefilled and editable (B-D2). */
  attributes: ApplicableAttributeSummary[]
  values: Record<string, CustomFieldValue>
  onClose: () => void
  onTransferred: () => void
}

/**
 * "Trasferisci ad altra anagrafica" (spec 0404, part B): pick the destination
 * anagrafica and review the offer's flexible fields. The server decides the
 * opportunity it lands on (the destination's open one, or a new one).
 */
export function QuoteRegistryTransferDialog({
  basePath,
  quoteId,
  currentRegistry,
  attributes,
  values,
  onClose,
  onTransferred,
}: QuoteRegistryTransferDialogProps) {
  const { t } = useTranslation()
  const currentRegistryId = currentRegistry?.id ?? null
  const schema = useMemo(() => buildQuoteRegistryTransferSchema(t, currentRegistryId), [t, currentRegistryId])
  const form = useForm<QuoteRegistryTransferFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { registry_id: null, attribute_values: values },
  })
  const effectiveAttributes = useMemo(
    () => attributes.map((attribute) => toEffectiveAttribute(attribute, 'quote')),
    [attributes],
  )
  const { submit, isPending } = useQuoteRegistryTransfer(basePath, quoteId, onTransferred)

  return (
    <Dialog open onOpenChange={(next) => (!next ? onClose() : undefined)}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{t('quotes.registryTransfer.title')}</DialogTitle>
          <DialogDescription>
            {t('quotes.registryTransfer.description', { registry: currentRegistry?.name ?? '—' })}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form
            id={FORM_ID}
            className="flex flex-col gap-3"
            onSubmit={(event) => void form.handleSubmit((formValues) => submit(formValues, form.setError))(event)}
          >
            <FormField
              control={form.control}
              name="registry_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('quotes.registryTransfer.registry')}</FormLabel>
                  <FormControl>
                    <AsyncPaginatedSelect
                      resource={REGISTRIES_FOR_SELECT_RESOURCE}
                      value={field.value}
                      onChange={field.onChange}
                      disabled={isPending}
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

            {effectiveAttributes.length > 0 ? (
              <fieldset className="flex flex-col gap-3">
                <legend className="mb-1 text-sm font-semibold">{t('quotes.registryTransfer.attributes')}</legend>
                {effectiveAttributes.map((attribute) => (
                  <AttributeLayoutField
                    key={attribute.code}
                    control={form.control}
                    attribute={attribute}
                    disabled={isPending}
                    readOnly={false}
                  />
                ))}
              </fieldset>
            ) : null}
          </form>
        </Form>

        <DialogFooter>
          <Button type="button" variant="outline" className="bg-card" onClick={onClose} disabled={isPending}>
            {t('common.cancel')}
          </Button>
          <Button type="submit" form={FORM_ID} disabled={isPending}>
            {t('quotes.registryTransfer.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
