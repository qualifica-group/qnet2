import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { InvoiceDocumentSections } from '@/features/invoices/invoice-document-sections'
import { useInvoice } from '@/features/invoices/use-invoice-queries'
import type { TableRow } from '@/features/table/types'

type InvoiceDetailPanelProps = ICellRendererParams<TableRow> & {
  /** Refreshes the master grid after a collection changed the totals. */
  onChanged: () => void
}

/**
 * Master/detail row of the invoices grid: lazily loads the document and shows
 * its lines and installments. The panel loads after AG Grid measured the
 * detail row, so its real height is pushed back through a ResizeObserver
 * (same mechanism as the Opportunities quotes panel).
 */
export function InvoiceDetailPanel({ data, node, api, onChanged }: InvoiceDetailPanelProps) {
  const { t } = useTranslation()
  const invoiceId = typeof data?.id === 'number' ? data.id : null
  const { data: invoice, isError, refetch } = useInvoice(invoiceId)

  const contentRef = useRef<HTMLDivElement>(null)
  const isReady = invoice !== undefined
  useEffect(() => {
    const element = contentRef.current
    if (!element || !node || !api || typeof ResizeObserver === 'undefined') {
      return
    }
    const observer = new ResizeObserver(() => {
      node.setRowHeight(element.offsetHeight)
      api.onRowHeightChanged()
    })
    observer.observe(element)
    return () => observer.disconnect()
  }, [node, api, isReady])

  if (invoiceId === null) {
    return null
  }
  if (isError) {
    return (
      <div className="flex flex-col items-start gap-2 p-4">
        <p className="text-sm text-destructive">{t('invoices.detail.loadError')}</p>
        <Button type="button" variant="outline" size="sm" className="bg-card" onClick={() => void refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }
  if (!invoice) {
    return <Skeleton className="m-3 h-24" />
  }
  return (
    <div ref={contentRef} className="p-3">
      <InvoiceDocumentSections invoice={invoice} onChanged={onChanged} />
    </div>
  )
}
