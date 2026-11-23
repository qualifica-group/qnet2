import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowRightLeft, Info, Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Form } from '@/components/ui/form'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import { toEffectiveAttribute } from '@/features/request-management/applicable-attribute-adapter'
import {
  QuoteRegistryTransferAttributesSection,
  QuoteRegistryTransferDestinationSection,
  QuoteRegistryTransferOfferSection,
} from '@/features/quotes/registry-transfer/quote-registry-transfer-sections'
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
  /** The offer's title, prefilled and editable (B-D8). */
  currentTitle: string
  /** The offer's "Informazioni aggiuntive" and their values, prefilled and editable (B-D2). */
  attributes: ApplicableAttributeSummary[]
  values: Record<string, CustomFieldValue>
  onClose: () => void
  onTransferred: () => void
}

/**
 * "Trasferisci ad altra anagrafica" (spec 0404, part B): pick the destination
 * anagrafica, review the offer's title and flexible fields. The server decides
 * the opportunity it lands on (the destination's open one, or a new one).
 *
 * Header / body / footer bands like the other CRM dialogs; the body scrolls on
 * an inner wrapper because `DialogContent` must never clip (the registry
 * picker's popup is portaled into it).
 */
export function QuoteRegistryTransferDialog({
  basePath,
  quoteId,
  currentRegistry,
  currentTitle,
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
    defaultValues: { registry_id: null, title: currentTitle, attribute_values: values },
  })
  const effectiveAttributes = useMemo(
    () => attributes.map((attribute) => toEffectiveAttribute(attribute, 'quote')),
    [attributes],
  )
  const { submit, isPending } = useQuoteRegistryTransfer(basePath, quoteId, currentTitle, onTransferred)

  return (
    <Dialog open onOpenChange={(next) => (!next ? onClose() : undefined)}>
      <DialogContent size="lg" className="gap-0 p-0">
        <div className="flex items-start gap-3 rounded-t-lg border-b bg-gradient-to-br from-card to-primary/[0.06] px-5 pt-5 pb-4">
          <span
            aria-hidden="true"
            className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15"
          >
            <ArrowRightLeft className="size-5" />
          </span>
          <DialogHeader className="min-w-0 flex-1 gap-1 pr-6">
            <DialogTitle>{t('quotes.registryTransfer.title')}</DialogTitle>
            <DialogDescription className="text-xs">{t('quotes.registryTransfer.subtitle')}</DialogDescription>
          </DialogHeader>
        </div>

        <Form {...form}>
          <form
            id={FORM_ID}
            className="flex max-h-[70vh] flex-col gap-4 overflow-y-auto px-5 py-5"
            onSubmit={(event) => void form.handleSubmit((formValues) => submit(formValues, form.setError))(event)}
          >
            <QuoteRegistryTransferDestinationSection
              control={form.control}
              disabled={isPending}
              currentRegistryName={currentRegistry?.name ?? null}
            />
            <QuoteRegistryTransferOfferSection control={form.control} disabled={isPending} />
            {effectiveAttributes.length > 0 ? (
              <QuoteRegistryTransferAttributesSection
                control={form.control}
                disabled={isPending}
                attributes={effectiveAttributes}
              />
            ) : null}

            <p className="flex items-start gap-2 rounded-lg border bg-card px-3 py-2.5 text-xs leading-relaxed text-muted-foreground">
              <Info className="mt-0.5 size-3.5 shrink-0 text-primary" aria-hidden="true" />
              {t('quotes.registryTransfer.description')}
            </p>
          </form>
        </Form>

        <DialogFooter className="rounded-b-lg border-t bg-gradient-to-t from-primary/[0.05] to-transparent px-5 py-3.5">
          <Button type="button" variant="outline" size="sm" className="bg-card" onClick={onClose} disabled={isPending}>
            {t('common.cancel')}
          </Button>
          <Button
            type="submit"
            form={FORM_ID}
            size="sm"
            className="gap-1.5 shadow-sm shadow-primary/20 sm:min-w-40"
            disabled={isPending}
          >
            {isPending ? (
              <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
            ) : (
              <ArrowRightLeft className="size-3.5" aria-hidden="true" />
            )}
            {t('quotes.registryTransfer.confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
