import { useTranslation } from 'react-i18next'
import { Form } from '@/components/ui/form'
import { RecordBody } from '@/components/detail/record-body'
import { RecordCanvas, RecordMeta } from '@/components/detail/record-panel'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ProductDetailHeader, ProductStatsStrip } from '@/features/products/product-detail-header'
import { effectiveAmountOfRows } from '@/features/products/product-supplier-rows'
import { ProductDetailSections } from '@/features/products/product-detail-sections'
import { useProductInlineEdit } from '@/features/products/use-product-inline-edit'
import type { ProductDetailWithPermissions } from '@/features/products/types'

interface ProductDetailViewProps {
  product: ProductDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the product (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single product, rendered as the same enterprise-CRM record as
 * every other detail (`RecordBody`): identity band, the price/cost/margin KPI
 * strip, the spec-sheet sections, the category's attributes and the custom
 * fields; the activity log takes the side column when the actor may read it.
 *
 * There is no edit page (spec 0218): the sections' fields edit IN PLACE, one
 * at a time (`RecordInlineField`, driven by `useProductInlineEdit`), each save
 * a PATCH of that field alone.
 */
export function ProductDetailView(props: ProductDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.product.permissions}>
      <ProductDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function ProductDetailContent({ product, onChanged }: ProductDetailViewProps) {
  const { t } = useTranslation()
  const editor = useProductInlineEdit(product, onChanged)
  const createdAt = formatDateTime(product.created_at)
  const collaborationTabs: RecordCollaborationTab[] = product.permissions.actions.view_activity
    ? [activityLogTab('products', product.id, t('activityLog.title'))]
    : []

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <ProductDetailHeader product={product} />
            <ProductStatsStrip
              cost={effectiveAmountOfRows(product.suppliers ?? [], 'cost', product.cost)}
              price={effectiveAmountOfRows(product.suppliers ?? [], 'price', product.price)}
            />
          </>
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...editor.state.form}>
          <ProductDetailSections product={product} editor={editor} />
        </Form>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('products.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
