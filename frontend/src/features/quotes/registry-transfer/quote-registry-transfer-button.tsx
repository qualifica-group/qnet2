import { useState } from 'react'
import { ArrowRightLeft } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { quoteDetailQueryKey } from '@/features/quotes/api'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'
import {
  QuoteRegistryTransferDialog,
  type QuoteRegistryTransferDialogProps,
} from '@/features/quotes/registry-transfer/quote-registry-transfer-dialog'

/** The Offerte route of the transfer (the request modules pass their own base path). */
const QUOTES_BASE_PATH = '/quotes'

type QuoteRegistryTransferButtonProps = Omit<QuoteRegistryTransferDialogProps, 'onClose'>

/** The record action opening the transfer dialog; the caller gates it on `transfer_registry`. */
export function QuoteRegistryTransferButton({ onTransferred, ...dialogProps }: QuoteRegistryTransferButtonProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <>
      <Button type="button" variant="secondary" size="sm" onClick={() => setOpen(true)}>
        <ArrowRightLeft aria-hidden="true" />
        {t('quotes.registryTransfer.button')}
      </Button>
      {open ? (
        <QuoteRegistryTransferDialog
          {...dialogProps}
          onClose={() => setOpen(false)}
          onTransferred={() => {
            setOpen(false)
            onTransferred()
          }}
        />
      ) : null}
    </>
  )
}

interface QuoteDetailRegistryTransferProps {
  quote: QuoteDetailWithPermissions
  onChanged?: () => void
}

/** The offer detail's wiring: the Offerte route, then the record and its lists reload. */
export function QuoteDetailRegistryTransfer({ quote, onChanged }: QuoteDetailRegistryTransferProps) {
  const queryClient = useQueryClient()

  return (
    <QuoteRegistryTransferButton
      basePath={QUOTES_BASE_PATH}
      quoteId={quote.id}
      currentRegistry={quote.registry ?? null}
      attributes={quote.applicable_attributes}
      values={quote.attribute_values}
      onTransferred={() => {
        void queryClient.invalidateQueries({ queryKey: quoteDetailQueryKey(quote.id) })
        onChanged?.()
      }}
    />
  )
}
