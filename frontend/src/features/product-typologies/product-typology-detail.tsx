import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { Shapes } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import {
  RecordCanvas,
  RecordCardHeader,
  RecordFieldList,
  RecordMeta,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import { useInlineEditingField, useRecordInlineEdit } from '@/components/record-form/use-record-inline-edit'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import {
  ProductTypologyCommissionRow,
  ProductTypologyIdentityRows,
  ProductTypologyProformaKindRow,
} from '@/features/product-typologies/product-typology-rows'
import {
  useProductTypologyForm,
  type ProductTypologyFormValues,
} from '@/features/product-typologies/use-product-typology-form'
import { formatDateTime } from '@/features/table/cell-renderers'
import type {
  ProductTypologyDetailWithPermissions,
  ProductTypologyFormMode,
} from '@/features/product-typologies/types'

interface ProductTypologyDetailViewProps {
  productTypology: ProductTypologyDetailWithPermissions
  /** Called after an in-place save, so the host refreshes whatever lists the record (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single product typology on the record kit: identity band
 * (`code` as the subtitle), the rows, the activity card, a metadata footer.
 * There is no edit page (wave 3 of the "no edit pages" plan): every row
 * edits IN PLACE, one at a time, each save a PATCH of that field alone.
 */
export function ProductTypologyDetailView(props: ProductTypologyDetailViewProps) {
  // The rows read the field permissions, so the provider wraps the whole detail.
  return (
    <ResourcePermissionsProvider permissions={props.productTypology.permissions}>
      <ProductTypologyDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function ProductTypologyDetailContent({ productTypology, onChanged }: ProductTypologyDetailViewProps) {
  const { t } = useTranslation()
  const editing = useInlineEditingField()
  // Stable per saved record: the form re-syncs its values on a new one.
  const mode = useMemo<ProductTypologyFormMode>(() => ({ type: 'edit', productTypology }), [productTypology])
  const { form, onSubmit, serverError, clearServerError } = useProductTypologyForm({
    mode,
    onSuccess: () => {
      editing.close()
      onChanged?.()
    },
  })
  const inline = useRecordInlineEdit<ProductTypologyFormValues>({
    editing,
    form,
    onSubmit,
    serverError,
    clearServerError,
    genericErrorMessage: t('productTypologies.form.genericError'),
  })
  const canEdit = productTypology.permissions.resource.update
  const createdAt = formatDateTime(productTypology.created_at)
  const updatedAt = formatDateTime(productTypology.updated_at)
  const canViewActivity = productTypology.permissions.actions.view_activity

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={canViewActivity ? [activityLogTab('product-typologies', productTypology.id, t('activityLog.title'))] : []}
        header={
          <RecordCardHeader
            media={
              <DetailMonogram
                name={productTypology.name}
                icon={<Shapes />}
                className="size-10 text-base [&>svg]:size-5"
              />
            }
            title={productTypology.name}
            subtitle={productTypology.code}
          />
        }
      >
        {/* Provider only (no DOM): the in-place rows share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <RecordSection title={t('productTypologies.form.sections.identity.title')} full>
              <RecordFieldList>
                <ProductTypologyIdentityRows inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('productTypologies.form.sections.commission.title')} full>
              <RecordFieldList>
                <ProductTypologyCommissionRow inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
            <RecordSection title={t('productTypologies.form.sections.billing.title')} full>
              <RecordFieldList>
                <ProductTypologyProformaKindRow inline={inline} canEdit={canEdit} />
              </RecordFieldList>
            </RecordSection>
          </RecordSectionsGrid>
        </Form>
      </RecordBody>

      <RecordMeta>
        {createdAt ? (
          <span>
            <span className="font-medium">{t('productTypologies.detail.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        ) : null}
        {updatedAt ? (
          <span>
            <span className="font-medium">{t('productTypologies.detail.updated_at')}</span>{' '}
            <span aria-hidden="true">·</span> {updatedAt}
          </span>
        ) : null}
      </RecordMeta>
    </RecordCanvas>
  )
}
