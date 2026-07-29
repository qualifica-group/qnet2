import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { IdCard, MapPin, Phone, UserRound } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { AddressCreateField } from '@/features/personal-data/address-create-field'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import type { AddressDraft, ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { ClientGroup } from '@/features/request-management/request-client-section'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'

interface RequestCreateClientSectionProps {
  control: Control<RequestCreateFormValues>
  identity: PersonalDataDraft
  onIdentityChange: (next: PersonalDataDraft) => void
  contacts: ContactDraft[]
  onContactsChange: (next: ContactDraft[]) => void
  address: AddressDraft[]
  onAddressChange: (next: AddressDraft[]) => void
  usingExistingRegistry: boolean
  /** Server 422s for the `client_*` blocks (AC-016), collected as one banner — see `useRequestCreateForm`. */
  errorMessage: string | null
}

/**
 * The create form's anagrafica section (spec 0057 D-2, AC-012/013): a
 * registry picker on top, mutually exclusive with the three identity/
 * contacts/address cards below it. The cards reuse the SAME components the
 * work panel's own section does (`PersonalDataCardForm`/`ContactsManager`/
 * `AddressCreateField`, via the shared `ClientGroup`), just bound to a plain
 * buffer instead of RHF fields — this create-only form has no PATCH diff to
 * compute, so there is nothing the RHF wiring would buy it (mirrors
 * `useRegistryForm`'s `profileDraft`). Picking a registry hides the three
 * cards outright: D-2 forbids sending both branches together, so there is
 * nothing left to disable.
 */
export function RequestCreateClientSection({
  control,
  identity,
  onIdentityChange,
  contacts,
  onContactsChange,
  address,
  onAddressChange,
  usingExistingRegistry,
  errorMessage,
}: RequestCreateClientSectionProps) {
  const { t } = useTranslation()
  const { renderAction, selectedItemFor } = useQuickCreateAction(REGISTRIES_FOR_SELECT_RESOURCE)

  return (
    <FormSection
      icon={IdCard}
      title={t('requestManagement.form.create.client.title')}
      description={t('requestManagement.form.create.client.description')}
    >
      <FormField
        control={control}
        name="registry_id"
        render={({ field }) => (
          <FormItem>
            <FormControl>
              <AsyncPaginatedSelect
                resource={REGISTRIES_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                // Keeps a just-created registry labelled in the trigger until
                // the invalidated options page catches up (spec 0028 AC-006).
                selectedItem={selectedItemFor(field.value)}
                action={renderAction((ref) => field.onChange(ref.id))}
                labels={{
                  placeholder: t('requestManagement.form.create.client.registryPlaceholder'),
                  searchPlaceholder: t('requestManagement.form.create.client.registrySearch'),
                  empty: t('requestManagement.form.create.client.registryEmpty'),
                  error: t('requestManagement.form.create.client.registryError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('requestManagement.form.create.client.registryLabel'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
            <p className="text-xs text-muted-foreground">
              {t('requestManagement.form.create.client.registryHint')}
            </p>
            <FormMessage />
          </FormItem>
        )}
      />

      {!usingExistingRegistry && (
        <>
          <div className="border-t" />

          <ClientGroup icon={UserRound} title={t('requestManagement.form.create.client.identityGroup')}>
            <PersonalDataCardForm value={identity} onChange={onIdentityChange} />
          </ClientGroup>

          <div className="border-t" />

          <ClientGroup icon={Phone} title={t('requestManagement.form.create.client.contactsGroup')}>
            <ContactsManager value={contacts} onChange={onContactsChange} showHeader={false} createMode />
          </ClientGroup>

          <div className="border-t" />

          <ClientGroup icon={MapPin} title={t('requestManagement.form.create.client.addressGroup')}>
            <AddressCreateField value={address} onChange={onAddressChange} cityRequired={false} />
          </ClientGroup>
        </>
      )}

      {errorMessage && (
        <div
          role="alert"
          className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive"
        >
          {errorMessage}
        </div>
      )}
    </FormSection>
  )
}
