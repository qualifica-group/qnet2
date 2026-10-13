import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { RecordCanvas, RecordMeta, RecordSectionsGrid } from '@/components/detail/record-panel'
import { RecordBody } from '@/components/detail/record-body'
import type { RecordCollaborationTab } from '@/components/detail/record-collaboration-card'
import { Form } from '@/components/ui/form'
import { activityLogTab } from '@/features/activity-log/activity-log-tab'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { CustomFieldRecordSections } from '@/features/custom-fields/custom-field-record-sections'
import type { CustomFieldValue } from '@/features/custom-fields/types'
import {
  CompanySiteIdentitySection,
  CompanySitePersistedChildSections,
} from '@/features/company-sites/company-site-card-sections'
import {
  CompanySiteDetailHeader,
  CompanySiteDetailStats,
} from '@/features/company-sites/company-site-detail-header'
import {
  CompanySiteBanksSection,
  CompanySiteCompanySection,
  CompanySiteGeneralSection,
} from '@/features/company-sites/company-site-rows'
import { companySiteDetailQueryKey } from '@/features/company-sites/api'
import { useCompanySiteActions } from '@/features/company-sites/use-company-site-actions'
import { useCompanySiteInlineEdit } from '@/features/company-sites/use-company-site-inline-edit'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { CompanySiteDetailWithPermissions } from '@/features/company-sites/types'

/** Stable empty set: a fresh object per render would break the sections' memo. */
const NO_CUSTOM_FIELDS: Record<string, CustomFieldValue> = {}

interface CompanySiteDetailViewProps {
  companySite: CompanySiteDetailWithPermissions
  /** Called after an in-place save, a logo change or a set-default, so the host refreshes whatever lists the site (spec 0195 D-7). */
  onChanged?: () => void
}

/**
 * Detail of a single company site, rendered as an enterprise-CRM record on the
 * same kit Opportunita', Lead, Campagne, Progetti e Sedi use: identity/KPI
 * header, the sections, the activity card on the right, a metadata footer.
 *
 * There is no edit page (wave 3 of the "no edit pages" plan): the name, notes,
 * company, the anagraphic card, the banks and the custom fields edit IN PLACE,
 * one row at a time, each save a PATCH of that field alone; contacts, the
 * address, the logo and "Società di Default" act at once.
 */
export function CompanySiteDetailView(props: CompanySiteDetailViewProps) {
  // The edit form reads the field permissions while it is built, so the
  // provider wraps the whole detail, not just the sections.
  return (
    <ResourcePermissionsProvider permissions={props.companySite.permissions}>
      <CompanySiteDetailContent {...props} />
    </ResourcePermissionsProvider>
  )
}

function CompanySiteDetailContent({ companySite, onChanged }: CompanySiteDetailViewProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const editor = useCompanySiteInlineEdit(companySite, onChanged)
  const actions = useCompanySiteActions(companySite, onChanged)
  const { form, inline, card, banks } = editor
  const createdAt = formatDateTime(companySite.created_at)
  const collaborationTabs: RecordCollaborationTab[] = companySite.permissions.actions.view_activity
    ? [activityLogTab('company-sites', companySite.id, t('activityLog.title'))]
    : []

  // A contact/address persisted on its own: refetch the record and tell the host.
  const handleCardChanged = () => {
    void queryClient.invalidateQueries({ queryKey: companySiteDetailQueryKey(companySite.id) })
    onChanged?.()
  }

  return (
    <RecordCanvas>
      <RecordBody
        collaborationTabs={collaborationTabs}
        header={
          <>
            <CompanySiteDetailHeader
              site={companySite}
              onSetDefault={actions.canSetDefault ? () => void actions.setDefault() : undefined}
              isSettingDefault={actions.settingDefault}
            />
            <CompanySiteDetailStats site={companySite} />
          </>
        }
      >
        {/* Provider only (no DOM): the inline editors share the edit form. */}
        <Form {...form}>
          <RecordSectionsGrid>
            <CompanySiteGeneralSection
              form={form}
              inline={inline}
              logo={{
                mode: 'immediate',
                url: companySite.logo_url,
                onUpload: actions.uploadLogo,
                onRemove: actions.removeLogo,
                canUpload: actions.canUploadLogo,
                canRemove: actions.canRemoveLogo,
              }}
            />
            <CompanySiteIdentitySection identity={companySite.personal_data} buffer={card} inline={inline} />
            <CompanySiteCompanySection form={form} inline={inline} company={companySite.company} />
            <CustomFieldRecordSections
              resource="company-sites"
              values={companySite.custom_fields ?? NO_CUSTOM_FIELDS}
              inline={inline}
              control={form.control}
            />
            <CompanySiteBanksSection inline={inline} banks={banks.draft} onChange={banks.setDraft} />
          </RecordSectionsGrid>
        </Form>
        <RecordSectionsGrid className="border-t">
          <CompanySitePersistedChildSections
            card={companySite.personal_data}
            fieldPermission={card.fieldPermission}
            onChanged={handleCardChanged}
          />
        </RecordSectionsGrid>
      </RecordBody>

      {createdAt ? (
        <RecordMeta>
          <span>
            <span className="font-medium">{t('companySites.columns.created_at')}</span>{' '}
            <span aria-hidden="true">·</span> {createdAt}
          </span>
        </RecordMeta>
      ) : null}
    </RecordCanvas>
  )
}
