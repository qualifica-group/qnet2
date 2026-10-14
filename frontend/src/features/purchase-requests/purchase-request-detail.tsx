import { useTranslation } from 'react-i18next'
import { ArrowLeft, Paperclip, ShoppingCart } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCard,
  RecordCardHeader,
  RecordSection,
  RecordSectionsGrid,
  RecordStat,
  RecordStatStrip,
} from '@/components/detail/record-panel'
import { Button } from '@/components/ui/button'
import { Form } from '@/components/ui/form'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { PurchaseRequestFormActions } from '@/features/purchase-requests/purchase-request-form-actions'
import { PurchaseRequestLinesRecord } from '@/features/purchase-requests/purchase-request-lines-record'
import { toResourcePermissions } from '@/features/purchase-requests/purchase-request-permissions'
import { PurchaseRequestRecordSections } from '@/features/purchase-requests/purchase-request-record-sections'
import { RequestDocuments } from '@/features/purchase-requests/purchase-request-documents'
import { RequestStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import { usePurchaseRequestInlineEdit } from '@/features/purchase-requests/use-purchase-request-inline-edit'
import type { PurchaseRequest } from '@/features/purchase-requests/types'

interface PurchaseRequestDetailViewProps {
  request: PurchaseRequest
  onBack: () => void
  onDeleted: () => void
  /** After an in-place save or a closure, so the host refreshes what lists the RDA. */
  onChanged?: () => void
}

/**
 * The RDA record (spec 0256): identity band with the record actions, the
 * totals strip, then header, organization, notes, lines and documents. There
 * is no Save button: every field and every line edits in place, one at a time
 * (`usePurchaseRequestInlineEdit`). A closed RDA, or one the actor cannot
 * update, shows no pencil at all.
 */
export function PurchaseRequestDetailView(props: PurchaseRequestDetailViewProps) {
  // The inline editors read the field permissions, so the provider wraps the whole record.
  return (
    <ResourcePermissionsProvider permissions={toResourcePermissions(props.request)}>
      <PurchaseRequestDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function PurchaseRequestDetailContent({ request, onBack, onDeleted, onChanged }: PurchaseRequestDetailViewProps) {
  const { t } = useTranslation()
  const editor = usePurchaseRequestInlineEdit(request, onChanged)

  return (
    <RecordCanvas>
      <RecordCard>
        <RecordCardHeader
          media={<DetailMonogram name={request.subject} icon={<ShoppingCart />} className="size-10 text-base [&>svg]:size-5" />}
          title={request.subject}
          subtitle={t('purchaseRequests.form.editTitle', { id: request.id })}
          badges={<RequestStatusBadge status={request.status} />}
          actions={
            <Button type="button" variant="secondary" size="sm" onClick={onBack}>
              <ArrowLeft aria-hidden="true" />
              {t('common.back')}
            </Button>
          }
        />
        <div className="flex flex-wrap items-center gap-2 border-b px-4 py-2 empty:hidden">
          <PurchaseRequestFormActions request={request} onClosed={() => onChanged?.()} onDeleted={onDeleted} />
        </div>
        <RecordStatStrip>
          <RecordStat label={t('purchaseRequests.totals.taxable')} value={formatQuoteAmount(Number(request.taxable_total))} />
          <RecordStat label={t('purchaseRequests.totals.vat')} value={formatQuoteAmount(Number(request.vat_total))} />
          <RecordStat label={t('purchaseRequests.totals.grandTotal')} value={formatQuoteAmount(Number(request.grand_total))} />
          <RecordStat label={t('purchaseRequests.sections.lines')} value={request.lines.length} />
        </RecordStatStrip>

        {request.status === 'closed' ? (
          <p role="status" className="mx-4 mt-3 rounded-md border bg-surface px-3 py-2 text-xs">
            {t('purchaseRequests.form.closedNotice', { reason: request.close_reason ?? '—' })}
          </p>
        ) : null}

        {/* Provider only (no DOM): the inline editors share the RDA form. */}
        <Form {...editor.form}>
          <RecordSectionsGrid>
            <PurchaseRequestRecordSections request={request} editor={editor} />
            <PurchaseRequestLinesRecord request={request} editor={editor} />
            <RecordSection title={t('purchaseRequests.sections.documents')} icon={<Paperclip />} full>
              <RequestDocuments control={editor.form.control} requestId={request.id} />
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordCard>
    </RecordCanvas>
  )
}
