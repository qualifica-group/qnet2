import { useTranslation } from 'react-i18next'
import { Contact, Database, Info, Package } from 'lucide-react'
import { ContactChips } from '@/components/detail/contact-chips'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import { RecordField, RecordFieldList, RecordSection } from '@/components/detail/record-panel'
import { NotesCalloutRow } from '@/components/record-form/notes-callout-row'
import { RecordInlineField } from '@/components/record-form/record-inline-field'
import { useCascadeEditable } from '@/components/record-form/use-cascade-editable'
import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import type { PrimaryContact } from '@/features/table/types'
import { ExtraFieldsEditor } from '@/features/leads/extra-fields-editor'
import {
  LeadCampaignField,
  LeadNotesField,
  LeadOperatorField,
  LeadProductsField,
  LeadRegistryField,
  LeadSiteField,
  LeadSourceField,
} from '@/features/leads/lead-fields'
import { campaignLabel, type LeadRecordSectionProps } from '@/features/leads/lead-record'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** A `RecordField` row whose value is a person: the avatar makes it taller than a text row, so it centers on the label. */
const PERSON_ROW_CLASS = '@md:items-center'

/** Stable empty default: a registry without primary contacts reads the same as `[]`. */
const EMPTY_CONTACTS: PrimaryContact[] = []

/** "Note" in the amber callout, written inside it (spec 0217 D-2). */
export function LeadNotesRow({ values, form, inline, className }: Omit<LeadRecordSectionProps, 'cascades'> & { className?: string }) {
  const { t } = useTranslation()
  return (
    <NotesCalloutRow
      field="notes"
      title={t('leads.form.notes')}
      notes={values.notes}
      placeholder={t('leads.form.notesPlaceholder')}
      inline={inline}
      editor={<LeadNotesField control={form.control} />}
      className={className}
    />
  )
}

/**
 * "Anagrafica e campagna": the lead's identity and the campaign that
 * generated it. A Campaign pick prefills Sede and Fonte and may prune the
 * products: its row opens only when those are editable too.
 */
export function LeadContactRecordSection({ values, form, inline, cascades }: LeadRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const campaignCascadeEditable = useCascadeEditable(['operational_site_id', 'source_id', 'products_of_interest'])

  return (
    <RecordSection title={t('leads.form.sections.contact.title')} icon={<Contact />}>
      <RecordFieldList>
        <RecordInlineField
          field="registry_id"
          label={t('leads.form.registry')}
          inline={inline}
          editor={<LeadRegistryField control={control} selected={values.registry} />}
        >
          {values.registry ? (
            <div className="flex flex-col gap-1.5">
              <RecordLink domain="registries" id={values.registry.id}>
                {values.registry.name}
              </RecordLink>
              <ContactChips contacts={values.registry.primary_contacts ?? EMPTY_CONTACTS} />
            </div>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="campaign_id"
          label={t('leads.form.campaign')}
          inline={inline}
          canEdit={campaignCascadeEditable}
          editor={
            <LeadCampaignField
              control={control}
              selected={values.campaign ? { id: values.campaign.id, name: values.campaign.name } : null}
              onItemChange={(item) => void cascades.handleCampaignItemChange(item)}
            />
          }
        >
          {values.campaign ? (
            <RecordLink domain="campaigns" id={values.campaign.id}>
              {campaignLabel(values.campaign)}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/**
 * "Dettagli": Sede, Operatore and Fonte. Sede and Operatore are linked (a Sede
 * change clears the Operatore, an Operatore fills an empty Sede), so each row
 * opens only when the field its cascade writes is editable.
 */
export function LeadDetailsRecordSection({ values, form, inline, cascades }: LeadRecordSectionProps) {
  const { t } = useTranslation()
  const { control } = form
  const operatorEditable = useCascadeEditable(['operator_id'])
  const siteEditable = useCascadeEditable(['operational_site_id'])
  const site = values.operational_site

  return (
    <RecordSection title={t('leads.form.sections.details.title')} icon={<Info />}>
      <RecordFieldList>
        <RecordInlineField
          field="operational_site_id"
          label={t('leads.form.operationalSite')}
          inline={inline}
          canEdit={operatorEditable}
          editor={
            <LeadSiteField
              control={control}
              selected={site ? { id: site.id, name: site.label } : null}
              onItemChange={cascades.handleSiteItemChange}
            />
          }
        >
          {site && site.label ? (
            <RecordLink domain="operational-sites" id={site.id}>
              {site.label}
            </RecordLink>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        {/* The operator is a PERSON: the hover-card idiom Opportunita' uses for its team, not a `RecordLink`. */}
        <RecordInlineField
          field="operator_id"
          label={t('leads.form.operator')}
          inline={inline}
          className={PERSON_ROW_CLASS}
          canEdit={site !== null || siteEditable}
          editor={
            <LeadOperatorField
              control={control}
              selected={values.operator}
              onItemChange={cascades.handleOperatorItemChange}
            />
          }
        >
          {values.operator ? (
            <UserProfileHoverCard user={values.operator} triggerClassName="rounded-md">
              <UserAvatar name={values.operator.name} src={null} className="shrink-0" />
              <span className="truncate text-sm text-foreground">{values.operator.name}</span>
            </UserProfileHoverCard>
          ) : (
            <DetailEmpty />
          )}
        </RecordInlineField>
        <RecordInlineField
          field="source_id"
          label={t('leads.form.source')}
          inline={inline}
          editor={<LeadSourceField control={control} selected={values.source} />}
        >
          {values.source?.name ?? <DetailEmpty />}
        </RecordInlineField>
      </RecordFieldList>
    </RecordSection>
  )
}

/** "Prodotti di interesse" across the record's width: one editor for the whole set (spec 0094). */
export function LeadProductsRecordSection({ values, form, inline, cascades }: LeadRecordSectionProps) {
  const { t } = useTranslation()
  const products = values.products_of_interest
  const title = t('leads.form.sections.productsOfInterest.title')

  return (
    <RecordSection title={title} icon={<Package />} className={FULL_WIDTH_SECTION_CLASS}>
      <RecordInlineField
        field="products_of_interest"
        label={title}
        inline={inline}
        layout="block"
        editor={
          <LeadProductsField
            control={form.control}
            knownProducts={products}
            fallbackCategoryIds={cascades.campaignCategoryIds}
          />
        }
      >
        {products.length > 0 ? (
          <ul className="flex flex-col gap-1 text-sm">
            {products.map((product) => (
              <li key={product.id} className="min-w-0">
                <RecordLink domain="products" id={product.id} className="font-medium">
                  {product.name}
                </RecordLink>
                {product.product_category ? (
                  <span className="text-muted-foreground"> — {product.product_category.name}</span>
                ) : null}
              </li>
            ))}
          </ul>
        ) : (
          <DetailEmpty />
        )}
      </RecordInlineField>
    </RecordSection>
  )
}

/** "Dati importati" (spec 0033): the free key/value pairs, edited all together. */
export function LeadExtraFieldsRecordSection({ values, form, inline }: Omit<LeadRecordSectionProps, 'cascades'>) {
  const { t } = useTranslation()
  const entries = Object.entries(values.extra_fields ?? {})
  const title = t('leads.detail.importedData.title')

  return (
    <RecordSection title={title} icon={<Database />} className={FULL_WIDTH_SECTION_CLASS}>
      <RecordInlineField
        field="extra_fields"
        label={title}
        inline={inline}
        layout="block"
        editor={<ExtraFieldsEditor control={form.control} />}
      >
        {entries.length > 0 ? (
          <RecordFieldList>
            {entries.map(([key, value]) => (
              <RecordField key={key} label={key}>
                {value}
              </RecordField>
            ))}
          </RecordFieldList>
        ) : (
          <DetailEmpty />
        )}
      </RecordInlineField>
    </RecordSection>
  )
}
