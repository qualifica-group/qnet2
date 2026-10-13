import { useState } from 'react'
import { Plus } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { VatRateRef } from '@/features/invoices/types'
import { productCostTypeOf } from '@/features/products/for-select-api'
import type { PurchaseInvoiceLineFormValues } from '@/features/purchase-invoices/purchase-invoice-schema'
import { QuoteProductSelect, type QuoteProductForSelectItem } from '@/features/quotes/quote-product-select'

type LineKind = 'catalog' | 'free'
const LINE_KINDS: readonly LineKind[] = ['catalog', 'free']

export type NewLineFields = Partial<PurchaseInvoiceLineFormValues> & Pick<PurchaseInvoiceLineFormValues, 'description'>

interface PurchaseInvoiceEditorAddLineProps {
  onAdd: (fields: NewLineFields) => void
  onRememberVat: (ref: VatRateRef) => void
}

/** Adds a row from the catalog (name, cost and VAT precompiled) or a free-text one. */
export function PurchaseInvoiceEditorAddLine({ onAdd, onRememberVat }: PurchaseInvoiceEditorAddLineProps) {
  const { t } = useTranslation()
  const [kind, setKind] = useState<LineKind>('catalog')
  const [description, setDescription] = useState('')

  const addCatalog = (productId: number | null, item: QuoteProductForSelectItem | null) => {
    if (productId === null || item === null) {
      return
    }
    const { meta } = item
    if (meta.vat_rate_id !== null && meta.vat_rate !== null) {
      onRememberVat({ id: meta.vat_rate_id, name: meta.vat_rate_name ?? meta.vat_rate, rate: meta.vat_rate })
    }
    const costType = productCostTypeOf(item)
    onAdd({
      product_id: productId,
      ...(costType === null ? {} : { cost_type_id: costType.id }),
      description: item.label,
      unit_price: Number(meta.cost ?? 0),
      ...(meta.vat_rate_id === null ? {} : { vat_rate_id: meta.vat_rate_id }),
    })
  }

  const addFree = () => {
    const trimmed = description.trim()
    if (trimmed !== '') {
      onAdd({ description: trimmed })
      setDescription('')
    }
  }

  return (
    <div className="flex flex-wrap items-end gap-2 rounded-md border border-dashed bg-surface p-2">
      <div role="group" aria-label={t('purchaseInvoices.editor.lines.lineKind')} className="flex gap-1">
        {LINE_KINDS.map((option) => (
          <Button
            key={option}
            type="button"
            size="xs"
            variant={kind === option ? 'default' : 'outline'}
            aria-pressed={kind === option}
            onClick={() => setKind(option)}
          >
            {t(`purchaseInvoices.editor.lines.${option}`)}
          </Button>
        ))}
      </div>
      {kind === 'catalog' ? (
        <div className="min-w-48 flex-1">
          <QuoteProductSelect
            value={null}
            onChange={addCatalog}
            usage="COST"
            triggerLabel={t('purchaseInvoices.editor.lines.product')}
          />
        </div>
      ) : (
        <>
          <div className="grid min-w-48 flex-1 gap-1">
            <Label htmlFor="purchase-invoice-editor-free-description" className="text-xs">
              {t('purchaseInvoices.editor.lines.freeDescription')}
            </Label>
            <Input
              id="purchase-invoice-editor-free-description"
              className="h-8 text-xs"
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === 'Enter') {
                  event.preventDefault()
                  addFree()
                }
              }}
            />
          </div>
          <Button type="button" size="xs" variant="secondary" disabled={description.trim() === ''} onClick={addFree}>
            <Plus />
            {t('purchaseInvoices.editor.lines.addFreeLine')}
          </Button>
        </>
      )}
    </div>
  )
}
