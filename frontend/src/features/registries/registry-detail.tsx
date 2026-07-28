import { useTranslation } from 'react-i18next'
import type { ReactNode } from 'react'
import { History, IdCard, Info, MapPin, Phone, UserRound, Users } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { cn } from '@/lib/utils'
import { enumLabelOf } from '@/features/config/enum-label'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { cardToDraft } from '@/features/personal-data/drafts'
import type { PersonalDataFieldPermissionResolver } from '@/features/personal-data/types'
import type { PrimaryContact } from '@/features/table/types'
import type { ManagerRef, ReferenceRef, RegistryDetailWithPermissions } from '@/features/registries/types'

/**
 * Renders the (owner-agnostic, reused unchanged) contacts/addresses managers
 * in pure read-only mode: visible, never editable — no add/edit/remove
 * affordance shows up.
 */
const READ_ONLY_FIELD_PERMISSION: PersonalDataFieldPermissionResolver = () => ({
  visible: true,
  editable: false,
  required: false,
  disabled: false,
  readonly: true,
})

/** No-op change handler: the read-only managers never call it. */
function noopChange(): void {}

/** Comma-joined names of a relation list, or the empty-value placeholder. */
function refList(refs: ReferenceRef[]) {
  return refs.length > 0 ? refs.map((ref) => ref.name).join(', ') : <DetailEmpty />
}

/**
 * Compact person card: an optional badge (a manager's "G.A. n" slot), the name
 * and each of the person's PRIMARY contacts as a muted `type: value` line. An
 * empty G.A. slot renders `muted` with a placeholder name and no contacts.
 */
function PersonCard({
  name,
  contacts,
  badge,
  muted = false,
}: {
  name: string
  contacts?: PrimaryContact[]
  badge?: ReactNode
  muted?: boolean
}) {
  return (
    <div className="flex items-start gap-2 rounded-lg border p-2.5">
      {badge && (
        <Badge variant={muted ? 'outline' : 'secondary'} className="shrink-0">
          {badge}
        </Badge>
      )}
      <div className="flex min-w-0 flex-1 flex-col">
        <span className={cn('truncate text-sm', muted ? 'text-muted-foreground' : 'font-medium')}>
          {name}
        </span>
        {contacts?.map((contact) => (
          <span
            key={`${contact.type}-${contact.value}`}
            className="truncate text-xs text-muted-foreground"
          >
            {enumLabelOf('contact_type', contact.type)}: {contact.value}
          </span>
        ))}
      </div>
    </div>
  )
}

/**
 * A responsible person (supervisor/commercial/reporter): the name plus each of
 * their PRIMARY contacts (one per type) as a compact muted line, or the
 * empty-value placeholder when unset.
 */
function personField(ref: ReferenceRef | null) {
  if (!ref) {
    return <DetailEmpty />
  }
  return (
    <div className="flex flex-col gap-0.5">
      <span>{ref.name}</span>
      {ref.primary_contacts?.map((contact) => (
        <span
          key={`${contact.type}-${contact.value}`}
          className="truncate text-xs text-muted-foreground"
        >
          {enumLabelOf('contact_type', contact.type)}: {contact.value}
        </span>
      ))}
    </div>
  )
}

/** Formats an ISO date to a date-only, locale-aware string; blank when missing. */
function formatDate(value: string | null): string {
  if (!value) {
    return ''
  }
  const date = new Date(value)
  return Number.isNaN(date.getTime())
    ? ''
    : new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(date)
}

interface RegistryDetailViewProps {
  registry: RegistryDetailWithPermissions
}

/**
 * Read-only detail of a single registry, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered by the dedicated detail page. Contacts/addresses reuse the same managers as the
 * form (in read-only mode) so the card content never diverges from what the
 * edit form would show (spec 0020).
 */
export function RegistryDetailView({ registry }: RegistryDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(registry.created_at)
  const draft = registry.personal_data ? cardToDraft(registry.personal_data) : null

  // Rebuild the ordered "G.A. n" slots (with persistent empty gaps) from the
  // gap-aware `manager_slots` array + the hydrated manager cards.
  const managersById = new Map<number, ManagerRef>(registry.managers.map((m) => [m.id, m]))
  const managerSlots = registry.manager_slots.map((id) =>
    id === null ? null : (managersById.get(id) ?? null),
  )

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={registry.name} />}
        title={registry.name}
        badges={
          <>
            <Badge variant="secondary">
              {registry.is_supplier ? t('common.yes') : t('common.no')} — {t('registries.form.isSupplier')}
            </Badge>
            {registry.agreement_status && (
              <Badge variant="secondary">
                {enumLabelOf('agreement_status', registry.agreement_status)}
              </Badge>
            )}
          </>
        }
      />

      {registry.personal_data && (
        <DetailSection title={t('registries.form.sections.identity.title')} icon={<IdCard />}>
          <DetailGrid>
            {registry.personal_data.type === 'company' ? (
              <DetailField label={t('personalData.form.companyName')} full>
                {registry.personal_data.company_name || <DetailEmpty />}
              </DetailField>
            ) : (
              <>
                <DetailField label={t('personalData.form.firstName')}>
                  {registry.personal_data.first_name || <DetailEmpty />}
                </DetailField>
                <DetailField label={t('personalData.form.lastName')}>
                  {registry.personal_data.last_name || <DetailEmpty />}
                </DetailField>
              </>
            )}
            <DetailField label={t('personalData.form.taxCode')}>
              {registry.personal_data.tax_code || <DetailEmpty />}
            </DetailField>
            <DetailField label={t('personalData.form.vatNumber')}>
              {registry.personal_data.vat_number || <DetailEmpty />}
            </DetailField>
            {registry.personal_data.type === 'company' ? (
              <DetailField label={t('personalData.form.sdiCode')}>
                {registry.personal_data.sdi_code || <DetailEmpty />}
              </DetailField>
            ) : (
              <>
                <DetailField label={t('personalData.form.birthDate')}>
                  {formatDate(registry.personal_data.birth_date) || <DetailEmpty />}
                </DetailField>
                <DetailField label={t('personalData.form.birthCity')}>
                  {registry.personal_data.birth_city?.name || <DetailEmpty />}
                </DetailField>
                <DetailField label={t('personalData.form.residenceCity')}>
                  {registry.personal_data.residence_city?.name || <DetailEmpty />}
                </DetailField>
                <DetailField label={t('personalData.form.gender')}>
                  {registry.personal_data.gender ? (
                    enumLabelOf('gender', registry.personal_data.gender)
                  ) : (
                    <DetailEmpty />
                  )}
                </DetailField>
              </>
            )}
          </DetailGrid>
        </DetailSection>
      )}

      <DetailSection title={t('registries.detail.details')} icon={<Info />}>
        <DetailGrid>
          <DetailField label={t('registries.form.source')}>
            {registry.source?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.vatGroup')}>
            {registry.vat_group || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.sizeClass')}>
            {registry.size_class ? enumLabelOf('size_class', registry.size_class) : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.employeeCount')}>
            {registry.employee_count ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.supervisor')}>
            {personField(registry.supervisor)}
          </DetailField>
          <DetailField label={t('registries.form.commercial')}>
            {personField(registry.commercial)}
          </DetailField>
          <DetailField label={t('registries.form.reporter')}>
            {personField(registry.reporter)}
          </DetailField>
          <DetailField label={t('registries.form.sectors')} full>
            {refList(registry.sectors)}
          </DetailField>
          <DetailField label={t('registries.form.agreementNotes')} full>
            {registry.agreement_notes || <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('registries.detail.people')} icon={<Users />}>
        <div className="flex flex-col gap-4">
          <div className="flex flex-col gap-2">
            <span className="text-xs font-medium text-muted-foreground">
              {t('registries.form.referents')}
            </span>
            {registry.referents.length > 0 ? (
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {registry.referents.map((referent) => (
                  <PersonCard
                    key={referent.id}
                    name={referent.name}
                    contacts={referent.primary_contacts}
                  />
                ))}
              </div>
            ) : (
              <DetailEmpty />
            )}
          </div>

          <div className="flex flex-col gap-2">
            <span className="text-xs font-medium text-muted-foreground">
              {t('registries.form.managers')}
            </span>
            {managerSlots.length > 0 ? (
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {managerSlots.map((manager, index) => {
                  const badge = (
                    <span
                      className="flex items-center gap-1"
                      title={t('registries.form.managerSlotLabel', { n: index + 1 })}
                    >
                      <UserRound aria-hidden="true" className="size-3" />
                      {index + 1}
                    </span>
                  )
                  return manager ? (
                    <PersonCard
                      key={index}
                      badge={badge}
                      name={manager.name}
                      contacts={manager.primary_contacts}
                    />
                  ) : (
                    <PersonCard
                      key={index}
                      badge={badge}
                      name={t('registries.form.managerSlotEmpty')}
                      muted
                    />
                  )
                })}
              </div>
            ) : (
              <DetailEmpty />
            )}
          </div>
        </div>
      </DetailSection>

      <DetailSection title={t('registries.form.sections.contacts.title')} icon={<Phone />}>
        {draft ? (
          <ContactsManager
            value={draft.contacts}
            onChange={noopChange}
            fieldPermission={READ_ONLY_FIELD_PERMISSION}
            showHeader={false}
          />
        ) : (
          <DetailEmpty />
        )}
      </DetailSection>

      <DetailSection title={t('registries.form.sections.addresses.title')} icon={<MapPin />}>
        {draft ? (
          <AddressesManager
            value={draft.addresses}
            onChange={noopChange}
            fieldPermission={READ_ONLY_FIELD_PERMISSION}
            showHeader={false}
            showSiteType
          />
        ) : (
          <DetailEmpty />
        )}
      </DetailSection>

      {registry.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="registries" id={registry.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('registries.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
