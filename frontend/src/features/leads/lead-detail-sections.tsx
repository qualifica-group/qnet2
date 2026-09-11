import { useTranslation } from 'react-i18next'
import { Contact, Database, Info, Package } from 'lucide-react'
import { ContactChips } from '@/components/detail/contact-chips'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { RecordLink } from '@/components/detail/record-link'
import {
  RecordField,
  RecordFieldList,
  RecordSection,
  RecordSectionsGrid,
} from '@/components/detail/record-panel'
import { GeneralNotesCallout } from '@/components/record-form/general-notes-callout'
import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import type {
  LeadDetailWithPermissions as LeadDetailData,
  LeadProductOfInterest,
} from '@/features/leads/types'
import type { PrimaryContact } from '@/features/table/types'

/** Spans both columns of `RecordSectionsGrid` — same rule `RecordSection`'s own `full` prop applies. */
const FULL_WIDTH_SECTION_CLASS = '@2xl:col-span-2'

/** A `RecordField` row whose value is a person: the avatar makes it taller than a text row, so it centers on the label. */
const PERSON_ROW_CLASS = '@md:items-center'

/** Stable empty defaults: a missing key on an older fixture reads the same as `[]`. */
const EMPTY_PRODUCTS_OF_INTEREST: LeadProductOfInterest[] = []
const EMPTY_CONTACTS: PrimaryContact[] = []

interface LeadDetailSectionsProps {
  lead: LeadDetailData
}

/**
 * The record's `RecordSectionsGrid` body.
 *
 * Sections are named after the module's OWN `form.sections.*`, like every other
 * detail in the app (campaigns, projects, opportunities): a related record is
 * shown inside the section it belongs to and simply rendered as a link, rather
 * than pulled out into a "links" group that exists nowhere else (user directive
 * 2026-09-11).
 */
export function LeadDetailSections({ lead }: LeadDetailSectionsProps) {
  const { t } = useTranslation()
  const products = lead.products_of_interest ?? EMPTY_PRODUCTS_OF_INTEREST
  const extraFieldEntries = Object.entries(lead.extra_fields ?? {})

  return (
    <RecordSectionsGrid>
      {/* The same callout the form's Note field wears while it is being written
          (the treatment Opportunita' already carries): it brings its own
          micro-title, so it stands outside a `RecordSection`, and renders
          nothing at all when the lead has no note. */}
      <GeneralNotesCallout
        title={t('leads.form.notes')}
        notes={lead.notes}
        className={FULL_WIDTH_SECTION_CLASS}
      />

      <RecordSection title={t('leads.form.sections.contact.title')} icon={<Contact />}>
        <RecordFieldList>
          <RecordField label={t('leads.form.registry')}>
            {lead.registry ? (
              <div className="flex flex-col gap-1.5">
                <RecordLink domain="registries" id={lead.registry.id}>
                  {lead.registry.name}
                </RecordLink>
                <ContactChips contacts={lead.registry.primary_contacts ?? EMPTY_CONTACTS} />
              </div>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>

          <RecordField label={t('leads.form.campaign')}>
            {lead.campaign ? (
              <RecordLink domain="campaigns" id={lead.campaign.id}>
                {`${lead.campaign.code} — ${lead.campaign.name}`}
              </RecordLink>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      <RecordSection title={t('leads.form.sections.details.title')} icon={<Info />}>
        <RecordFieldList>
          <RecordField label={t('leads.form.operationalSite')}>
            {lead.operational_site && lead.operational_site.label ? (
              <RecordLink domain="operational-sites" id={lead.operational_site.id}>
                {lead.operational_site.label}
              </RecordLink>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>

          <RecordField label={t('leads.form.state')}>
            {lead.state?.name ?? <DetailEmpty />}
          </RecordField>

          <RecordField label={t('leads.form.source')}>
            {lead.source?.name ?? <DetailEmpty />}
          </RecordField>

          {/*
            The operator is a PERSON, so it gets the person idiom Opportunita'
            uses for its team (hover card + the shared read-only user Sheet),
            NOT a `RecordLink`.
          */}
          <RecordField label={t('leads.form.operator')} className={PERSON_ROW_CLASS}>
            {lead.operator ? (
              <UserProfileHoverCard user={lead.operator} triggerClassName="rounded-md">
                <UserAvatar name={lead.operator.name} src={null} className="shrink-0" />
                <span className="truncate text-sm text-foreground">{lead.operator.name}</span>
              </UserProfileHoverCard>
            ) : (
              <DetailEmpty />
            )}
          </RecordField>
        </RecordFieldList>
      </RecordSection>

      {products.length > 0 ? (
        <RecordSection title={t('leads.form.sections.productsOfInterest.title')} icon={<Package />}>
          <ul className="flex flex-col gap-1 text-sm">
            {products.map((product) => (
              <li key={product.id} className="min-w-0">
                <span className="font-medium">{product.name}</span>
                {product.product_category ? (
                  <span className="text-muted-foreground"> — {product.product_category.name}</span>
                ) : null}
              </li>
            ))}
          </ul>
        </RecordSection>
      ) : null}

      {extraFieldEntries.length > 0 ? (
        <RecordSection
          title={t('leads.detail.importedData.title')}
          icon={<Database />}
          className={FULL_WIDTH_SECTION_CLASS}
        >
          <RecordFieldList>
            {extraFieldEntries.map(([key, value]) => (
              <RecordField key={key} label={key}>
                {value}
              </RecordField>
            ))}
          </RecordFieldList>
        </RecordSection>
      ) : null}
    </RecordSectionsGrid>
  )
}
