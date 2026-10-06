import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { RECORD_PERSON_ROW_CLASS, RecordPerson } from '@/components/detail/record-person'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { managerPositionLabel } from '@/features/shared/manager-position-label'
import { useCascadeEditable, type OpportunityRecordSectionProps } from '@/features/opportunities/opportunity-record'
import {
  OpportunityManagersField,
  OpportunityProductLinesFormField,
  OpportunityProductsOfInterestFormField,
  OpportunitySourceField,
  OpportunitySupervisorField,
} from '@/features/opportunities/opportunity-relation-fields'
import type { OpportunityProductOfInterest } from '@/features/opportunities/types'

/** What re-pointing a classification row writes besides itself: the products it no longer covers. */
const PRODUCT_LINES_CASCADE_FIELDS = ['products_of_interest']

/**
 * Read-only list of the opportunity's "prodotti di interesse" (user directive
 * 2026-07-22), each with the category it belongs to when known — the same
 * pairing the picker shows while selecting them.
 */
function ProductsOfInterestList({ products }: { products: OpportunityProductOfInterest[] }) {
  if (products.length === 0) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex flex-col gap-1">
      {products.map((product) => (
        <li key={product.id}>
          <RecordLink domain="products" id={product.id} className="font-medium">
            {product.name}
          </RecordLink>
          {product.product_category ? (
            <span className="text-muted-foreground"> — {product.product_category.name}</span>
          ) : null}
        </li>
      ))}
    </ul>
  )
}

interface OpportunityClassificationRecordSectionProps extends OpportunityRecordSectionProps {
  /**
   * The classification rows as the reader sees them: the persisted rows'
   * hierarchy on the detail, the draft's category paths on create.
   */
  productLines: ReactNode
}

/**
 * "Classificazione" of the opportunity record (spec 0198): the Fonte (never
 * editable when derived from a Lead, BR-2), the classification rows and the
 * products of interest they scope. The rows' editor prunes the products a
 * change no longer covers, so it opens only where that pruning may be saved.
 * Sede operativa stays hidden (user directive 2026-08-05): its value
 * survives every save untouched.
 */
export function OpportunityClassificationRecordSection({
  values,
  form,
  inline,
  lockedFields,
  productLines,
}: OpportunityClassificationRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const cascadeEditable = useCascadeEditable(PRODUCT_LINES_CASCADE_FIELDS)

  return (
    <RecordSection title={t('opportunities.form.sections.classification.title')} icon={<Building2 />}>
      <RecordFieldList>
        <RecordInlineField
          field="source_id"
          label={t('opportunities.form.source')}
          inline={inline}
          canEdit={!lockedFields.has('source_id')}
          editor={<OpportunitySourceField control={control} selected={values.source} />}
        >
          {values.source?.name ?? <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="product_lines"
          label={t('opportunities.form.sections.productLines.title')}
          inline={inline}
          canEdit={cascadeEditable}
          editor={<OpportunityProductLinesFormField control={control} />}
        >
          {productLines}
        </RecordInlineField>
        <RecordInlineField
          field="products_of_interest"
          label={t('products.ofInterest.sectionTitle')}
          inline={inline}
          editor={
            <OpportunityProductsOfInterestFormField
              control={control}
              selected={values.products_of_interest.map((product) => ({
                id: product.id,
                label: product.name,
                subtitle: product.product_category?.name ?? null,
              }))}
            />
          }
        >
          <ProductsOfInterestList products={values.products_of_interest} />
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

interface OpportunityTeamRecordSectionProps extends OpportunityRecordSectionProps {
  /** Spec 0087 (D-7): the G.A. are kept identical to one of the Offerte's; `false` on a create. */
  managersSynchronized: boolean
}

/**
 * "Team" of the opportunity record (spec 0198), with the SAME `RecordPerson`
 * every record uses: the Supervisore, then the G.A. slots as ONE row — one
 * editor for all of them, since moving a person between slots is one change.
 * Each manager keeps its "G.A. n" denomination (spec 0080) above the person.
 */
export function OpportunityTeamRecordSection({
  values,
  form,
  inline,
  managersSynchronized,
}: OpportunityTeamRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const sortedManagers = [...values.managers].sort((a, b) => a.position - b.position)

  return (
    <RecordSection title={t('opportunities.form.sections.team.title')} icon={<Users />}>
      <RecordFieldList>
        <RecordInlineField
          field="supervisor_id"
          label={t('opportunities.form.supervisor')}
          inline={inline}
          className={RECORD_PERSON_ROW_CLASS}
          editor={<OpportunitySupervisorField control={control} selected={values.supervisor} />}
        >
          {values.supervisor ? <RecordPerson user={values.supervisor} /> : <DetailEmpty />}
        </RecordInlineField>
        <RecordInlineField
          field="manager_slots"
          label={t('opportunities.form.managers')}
          inline={inline}
          editor={
            <OpportunityManagersField
              control={control}
              selected={values.managers.map((manager) => ({ id: manager.id, label: manager.name }))}
              synchronized={managersSynchronized}
            />
          }
        >
          {sortedManagers.length > 0 ? (
            <ul className="flex min-w-0 flex-col gap-1.5">
              {sortedManagers.map((manager) => (
                // `position` is the key, not `id`: the slot is unique, the same person may fill two.
                <li key={manager.position} className="flex min-w-0 flex-col gap-0.5">
                  <span className="text-xs text-muted-foreground">
                    {managerPositionLabel(t, manager.position, values.manager_labels)}
                  </span>
                  <RecordPerson user={manager} />
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}
