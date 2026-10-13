import { useTranslation } from 'react-i18next'
import { useWatch } from 'react-hook-form'
import { Shapes } from 'lucide-react'
import { DetailMonogram } from '@/components/detail/detail-panel'
import { RecordFieldList, RecordSection, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordCreateLayout } from '@/components/record-form/record-create-layout'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { Form } from '@/components/ui/form'
import {
  ProductTypologyCommissionRow,
  ProductTypologyIdentityRows,
  ProductTypologyProformaKindRow,
} from '@/features/product-typologies/product-typology-rows'
import { useProductTypologyForm } from '@/features/product-typologies/use-product-typology-form'
import type { ProductTypologyDetail } from '@/features/product-typologies/types'

/** DOM id bridging the header's and the footer's save actions to the RHF `<form>`. */
const PRODUCT_TYPOLOGY_FORM_ID = 'product-typology-form'

/** Create mode is static: a fresh object per render would rebuild the form's defaults. */
const CREATE_MODE = { type: 'create' } as const

interface ProductTypologyFormBodyProps {
  onSuccess: (productTypology: ProductTypologyDetail) => void
  onCancel: () => void
}

/**
 * The product typology create form as a replica of the detail (wave 3 of the
 * "no edit pages" plan): the same rows, closed until clicked (Fatto/
 * Ripristina, nothing saved per row), plus the create-only `code`; Salva
 * validates and creates everything at once.
 */
export function ProductTypologyFormBody({ onSuccess, onCancel }: ProductTypologyFormBodyProps) {
  const { t } = useTranslation()
  const { form, serverError, onSubmit } = useProductTypologyForm({ mode: CREATE_MODE, onSuccess })
  const draft = useDraftInlineEdit(form)
  const name = useWatch({ control: form.control, name: 'name' })
  const heading = name.trim() || t('productTypologies.form.createTitle')

  return (
    <Form {...form}>
      <RecordCreateLayout
        formId={PRODUCT_TYPOLOGY_FORM_ID}
        onSubmit={form.handleSubmit(onSubmit)}
        title={heading}
        subtitle={t('productTypologies.form.createSubtitle')}
        media={<DetailMonogram name={heading} icon={<Shapes />} className="size-10 text-base [&>svg]:size-5" />}
        isSubmitting={form.formState.isSubmitting}
        serverError={serverError}
        onCancel={onCancel}
        labels={{
          save: t('productTypologies.form.save'),
          saving: t('productTypologies.form.saving'),
          cancel: t('productTypologies.form.cancel'),
        }}
      >
        <RecordSectionsGrid>
          <RecordSection title={t('productTypologies.form.sections.identity.title')} full>
            <RecordFieldList>
              <ProductTypologyIdentityRows inline={draft} canEdit withCode />
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('productTypologies.form.sections.commission.title')} full>
            <RecordFieldList>
              <ProductTypologyCommissionRow inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
          <RecordSection title={t('productTypologies.form.sections.billing.title')} full>
            <RecordFieldList>
              <ProductTypologyProformaKindRow inline={draft} canEdit />
            </RecordFieldList>
          </RecordSection>
        </RecordSectionsGrid>
      </RecordCreateLayout>
    </Form>
  )
}
