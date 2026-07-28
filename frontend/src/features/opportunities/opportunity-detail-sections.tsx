import { useTranslation } from 'react-i18next'
import { Award, Building2, Contact, StickyNote, Users } from 'lucide-react'
import { DetailEmpty } from '@/components/detail/detail-panel'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { UserAvatar } from '@/components/user-avatar'
import { CollectedAttributesSection } from '@/features/opportunities/opportunity-detail-attributes'
import { RewardChip } from '@/features/rewards/reward-chip'
import type {
  ApplicableAttributeSummary,
  OpportunityDetailWithPermissions as OpportunityDetailData,
  OpportunityProductLine,
  OpportunityProductOfInterest,
} from '@/features/opportunities/types'

/** Stable empty defaults (spec 0049 D-8): a missing key on older fixtures reads the same as `[]`/`{}`. */
const EMPTY_APPLICABLE_ATTRIBUTES: ApplicableAttributeSummary[] = []
const EMPTY_ATTRIBUTE_VALUES: Record<string, unknown> = {}
const EMPTY_PRODUCTS_OF_INTEREST: OpportunityProductOfInterest[] = []

/** Read-only list of the opportunity's business-function + product-category rows (spec 0040 amendment rev.3, AC-101). */
function ProductLinesList({ lines }: { lines: OpportunityProductLine[] }) {
  if (lines.length === 0) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex flex-col gap-1">
      {lines.map((line) => (
        <li key={line.id}>
          <span className="font-medium">{line.business_function.name}</span>
          <span className="text-muted-foreground"> — {line.product_category.name}</span>
        </li>
      ))}
    </ul>
  )
}

/**
 * Read-only list of the opportunity's "prodotti di interesse" (user directive
 * 2026-07-22), each with the category it belongs to — the same pairing the
 * picker shows while selecting them.
 */
function ProductsOfInterestList({ products }: { products: OpportunityProductOfInterest[] }) {
  if (products.length === 0) {
    return <DetailEmpty />
  }
  return (
    <ul className="flex flex-col gap-1">
      {products.map((product) => (
        <li key={product.id}>
          <span className="font-medium">{product.name}</span>
          {product.product_category ? (
            <span className="text-muted-foreground"> — {product.product_category.name}</span>
          ) : null}
        </li>
      ))}
    </ul>
  )
}

interface OpportunityDetailSectionsProps {
  opportunity: OpportunityDetailData
}

/**
 * The record's `RecordSectionsGrid` body: registry/contacts, classification,
 * team, rewards, general notes and the collected-Attribute values — each an
 * independent `RecordSection`, absent when its own data is empty (spec 0064
 * enterprise-CRM record layout).
 */
export function OpportunityDetailSections({ opportunity }: OpportunityDetailSectionsProps) {
  const { t } = useTranslation()
  const sortedManagers = [...opportunity.managers].sort((a, b) => a.position - b.position)
  const rewards = opportunity.rewards ?? []

  return (
    <RecordSectionsGrid>
      <RecordSection title={t('opportunities.form.sections.identity.title')} icon={<Contact />}>
        <RecordFieldList>
          <RecordField label={t('opportunities.form.registry')}>
            {opportunity.registry?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.referent')}>
            {opportunity.referent?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.commercial')}>
            {opportunity.commercial?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.reporter')}>
            {opportunity.reporter?.name ?? <DetailEmpty />}
          </RecordField>
          {opportunity.lead ? (
            <RecordField label={t('opportunities.detail.sourceLead')}>{opportunity.lead.label}</RecordField>
          ) : null}
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('opportunities.form.sections.classification.title')} icon={<Building2 />}>
        <RecordFieldList>
          <RecordField label={t('opportunities.form.source')}>
            {opportunity.source?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.operationalSite')}>
            {opportunity.operational_site?.label ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.state')}>
            {opportunity.state?.name ?? <DetailEmpty />}
          </RecordField>
          <RecordField label={t('opportunities.form.sections.productLines.title')}>
            <ProductLinesList lines={opportunity.product_lines} />
          </RecordField>
          <RecordField label={t('products.ofInterest.sectionTitle')}>
            <ProductsOfInterestList
              products={opportunity.products_of_interest ?? EMPTY_PRODUCTS_OF_INTEREST}
            />
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('opportunities.form.sections.team.title')} icon={<Users />}>
        <RecordFieldList>
          <RecordField label={t('opportunities.form.supervisor')}>
            {opportunity.supervisor?.name ?? <DetailEmpty />}
          </RecordField>
        </RecordFieldList>
        <div className="flex flex-col gap-2">
          <span className="text-xs font-medium text-muted-foreground">{t('opportunities.form.managers')}</span>
          {sortedManagers.length > 0 ? (
            <ul className="flex flex-col gap-2">
              {sortedManagers.map((manager) => (
                <li key={manager.id} className="flex items-center gap-2">
                  <span className="w-5 shrink-0 text-xs font-semibold text-muted-foreground">
                    {manager.position}
                  </span>
                  <UserAvatar name={manager.name} size="sm" />
                  <span className="truncate text-sm text-foreground">{manager.name}</span>
                </li>
              ))}
            </ul>
          ) : (
            <DetailEmpty />
          )}
        </div>
      </RecordSection>

      {rewards.length > 0 ? (
        <RecordSection title={t('opportunities.detail.rewards')} icon={<Award />}>
          <div className="flex flex-wrap gap-1.5">
            {rewards.map((reward) => (
              <RewardChip key={reward.id} rewardType={reward.reward_type} />
            ))}
          </div>
        </RecordSection>
      ) : null}

      {opportunity.general_notes ? (
        <RecordSection title={t('opportunities.form.sections.generalNotes.title')} icon={<StickyNote />} full>
          <p className="text-sm break-words whitespace-pre-wrap text-foreground">
            {opportunity.general_notes}
          </p>
        </RecordSection>
      ) : null}

      <CollectedAttributesSection
        attributes={opportunity.applicable_attributes ?? EMPTY_APPLICABLE_ATTRIBUTES}
        values={opportunity.attribute_values ?? EMPTY_ATTRIBUTE_VALUES}
      />
    </RecordSectionsGrid>
  )
}
