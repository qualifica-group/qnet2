import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useConfirm } from '@/components/confirm-dialog-context'
import { AddressCreateField } from '@/features/personal-data/address-create-field'
import { AddressForm } from '@/features/personal-data/address-form'
import { SITE_TYPE_LABEL_KEYS } from '@/features/personal-data/address-site-type'
import { createAddress, deleteAddress, updateAddress } from '@/features/personal-data/api'
import { addressToDraft, nextDraftKey } from '@/features/personal-data/drafts'
import { useImmediatePersist } from '@/features/personal-data/use-immediate-persist'
import type {
  AddressDraft,
  OwnerRef,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'

interface AddressesManagerProps {
  /** The buffered addresses owned by the parent form. */
  value: AddressDraft[]
  /** Emits the next buffer after any add/edit/remove. */
  onChange: (next: AddressDraft[]) => void
  /**
   * Resolves gating for the whole section (`personal_data.addresses`, spec
   * 0008): `!visible` hides the section entirely; `!editable` makes it
   * read-only (no add/edit/remove). Optional: omitting it keeps today's
   * ungated behaviour (self-service profile, AC-013).
   */
  fieldPermission?: PersonalDataFieldPermissionResolver
  /**
   * Renders the built-in title + top "Add" affordance. Defaults to `true`
   * (today's standalone look). Pass `false` when an ancestor (e.g. a
   * `FormSection`) already renders the heading, so only the rows plus a
   * trailing "Add" action are rendered.
   */
  showHeader?: boolean
  /**
   * When present (edit of an owner whose personal-data card already exists),
   * each add/edit/remove is persisted immediately to the backend against this
   * owner and the buffer is synced with the returned row. Omitting it keeps the
   * buffered flow: changes live in the buffer until the parent form is saved
   * (create mode, or edit of an owner with no card yet).
   */
  persistence?: OwnerRef
  /**
   * Renders the "site type" select in the create/edit dialog (spec 0020).
   * Opt-in, default `false`: forwarded verbatim to `AddressForm`.
   */
  showSiteType?: boolean
  /**
   * Caps the list length: once `value.length` reaches it the "Add" affordance
   * is hidden (Company Sites allow at most one address). Omitting it keeps the
   * unbounded behaviour (registries/self-service profile).
   */
  maxItems?: number
  /**
   * Renders a single, fully-controlled inline address form instead of the
   * list/dialog/"Add" affordance: no list, no dialog, just `line1`/`line2`/
   * postal code/`GeoSelect`/(optionally) site type, bound to `value[0]`.
   * Default `false` keeps today's exact CRUD-only behaviour; only meaningful
   * in create mode.
   */
  createMode?: boolean
  /**
   * With `createMode`, lists further addresses under the inline form, added
   * through the usual "Add" dialog (the anagrafiche: an owner with several
   * sites). The inline form keeps owning the address it started.
   */
  multipleOnCreate?: boolean
}

/** `new` = the add form is open; a string = that address `_key` is being edited. */
type EditingState = 'new' | string | null

/**
 * The human-readable location line of an address: postal code plus the hydrated
 * city/province/state/country names, in that order. Empty string when none is
 * present (a draft that only carries geo ids, or a caller that did not
 * eager-load the names), so the row can drop the line entirely.
 */
function locationSummary(address: AddressDraft): string {
  return [
    address.postal_code,
    address.city?.name,
    address.province?.name,
    address.state?.name,
    address.country?.name,
  ]
    .filter(Boolean)
    .join(' · ')
}

/**
 * Reusable CRUD list for an owner's addresses; the create/edit form opens in a
 * dialog. Two write modes via `persistence`: buffered (ADR 0012) or immediate
 * (persist each change, sync the buffer with the server row whose `is_primary`
 * is authoritative — the backend auto-primaries the first). Single primary per
 * owner, mirroring the backend (ADR 0010). In `createMode`, the list/dialog are
 * replaced by a single inline form (`AddressCreateField`) that starts optional;
 * with `multipleOnCreate` the list and its "Add" dialog follow it, for the
 * further addresses.
 */
export function AddressesManager({
  value,
  onChange,
  fieldPermission,
  showHeader = true,
  persistence,
  showSiteType = false,
  maxItems,
  createMode = false,
  multipleOnCreate = false,
}: AddressesManagerProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { pending, run } = useImmediatePersist()
  const [editing, setEditing] = useState<EditingState>(null)
  // `multipleOnCreate`: the `_key` of the address the inline form owns, so an
  // address added through the dialog never moves into it.
  const [inlineKey, setInlineKey] = useState<string | null>(null)
  const permission = fieldPermission?.('personal_data.addresses')

  if (permission && !permission.visible) {
    return null
  }
  const readOnly = permission ? permission.disabled || !permission.editable : false
  // Hide the "Add" affordance once read-only or the cap (if any) is reached.
  const canAdd = !readOnly && (maxItems === undefined || value.length < maxItems)

  if (createMode && !multipleOnCreate) {
    return (
      <section className="flex flex-col gap-2">
        {showHeader && (
          <h4 className="text-sm font-medium">{t('personalData.addresses.title')}</h4>
        )}
        <AddressCreateField value={value} onChange={onChange} showSiteType={showSiteType} />
      </section>
    )
  }

  /**
   * Single-primary invariant: a forced key wins (rest demoted); otherwise, when
   * none is primary, the first becomes the default.
   */
  const normalizePrimary = (
    addresses: AddressDraft[],
    primaryKey?: string,
  ): AddressDraft[] => {
    if (primaryKey) {
      return addresses.map((address) => ({
        ...address,
        is_primary: address._key === primaryKey,
      }))
    }
    if (addresses.length > 0 && !addresses.some((address) => address.is_primary)) {
      return addresses.map((address, index) => ({
        ...address,
        is_primary: index === 0,
      }))
    }
    return addresses
  }

  const inlineAddress = value.filter((address) => address._key === inlineKey)
  const listed = createMode ? value.filter((address) => address._key !== inlineKey) : value

  /** The inline form's edit: replaces (or clears) the address it owns, keeping a single primary. */
  const handleInlineChange = (next: AddressDraft[]) => {
    const others = value.filter((address) => address._key !== inlineKey)
    const draft = next[0]
    if (!draft) {
      setInlineKey(null)
      onChange(normalizePrimary(others))
      return
    }
    const owned = inlineAddress[0]
    const isPrimary = owned ? owned.is_primary : !others.some((address) => address.is_primary)
    const kept = { ...draft, is_primary: isPrimary }
    setInlineKey(kept._key)
    const nextValue = owned
      ? value.map((address) => (address._key === owned._key ? kept : address))
      : [kept, ...others]
    onChange(normalizePrimary(nextValue, isPrimary ? kept._key : undefined))
  }

  /** Merges a just-added draft into the buffer, normalizing the primary flag. */
  const appendDraft = (draft: AddressDraft) => {
    onChange(normalizePrimary([...value, draft], draft.is_primary ? draft._key : undefined))
  }

  /** Replaces the draft at `key` in the buffer, normalizing the primary flag. */
  const replaceDraft = (key: string, draft: AddressDraft) => {
    const next = value.map((address) => (address._key === key ? draft : address))
    onChange(normalizePrimary(next, draft.is_primary ? key : undefined))
  }

  const handleAdd = async (fields: Omit<AddressDraft, '_key'>) => {
    if (persistence) {
      const ok = await run(
        async () => {
          const created = await createAddress({
            ...fields,
            addressable_type: persistence.type,
            addressable_id: persistence.id,
          })
          appendDraft(addressToDraft(created))
        },
        'personalData.addresses.created',
        'personalData.addresses.genericError',
      )
      if (ok) {
        setEditing(null)
      }
      return
    }
    appendDraft({ ...fields, _key: nextDraftKey() })
    setEditing(null)
  }

  const handleEdit = async (key: string, fields: Omit<AddressDraft, '_key'>) => {
    const current = value.find((address) => address._key === key)
    if (persistence && current?.id !== undefined) {
      const ok = await run(
        async () => {
          const updated = await updateAddress(current.id!, {
            line1: fields.line1,
            line2: fields.line2,
            postal_code: fields.postal_code,
            city_id: fields.city_id,
            province_id: fields.province_id,
            state_id: fields.state_id,
            country_id: fields.country_id,
            is_primary: fields.is_primary,
            site_type: fields.site_type,
          })
          replaceDraft(key, { ...addressToDraft(updated), _key: key })
        },
        'personalData.addresses.updated',
        'personalData.addresses.genericError',
      )
      if (ok) {
        setEditing(null)
      }
      return
    }
    replaceDraft(key, { ...fields, _key: key })
    setEditing(null)
  }

  const handleDelete = async (key: string) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('personalData.addresses.deleteAction'),
      description: t('personalData.addresses.deleteConfirm'),
      confirmLabel: t('personalData.addresses.deleteAction'),
    })
    if (!confirmed) {
      return
    }
    const current = value.find((address) => address._key === key)
    const removed = () => onChange(normalizePrimary(value.filter((a) => a._key !== key)))
    if (persistence && current?.id !== undefined) {
      await run(
        () => deleteAddress(current.id!).then(removed),
        'personalData.addresses.deleted',
        'personalData.addresses.genericError',
      )
      return
    }
    removed()
  }

  return (
    <section className="flex flex-col gap-2">
      {showHeader && (
        <div className="flex items-center justify-between">
          <h4 className="text-sm font-medium">{t('personalData.addresses.title')}</h4>
          {canAdd && (
            <Button type="button" variant="outline" size="sm" onClick={() => setEditing('new')}>
              <Plus aria-hidden="true" />
              {t('personalData.addresses.add')}
            </Button>
          )}
        </div>
      )}

      {createMode && (
        <AddressCreateField value={inlineAddress} onChange={handleInlineChange} showSiteType={showSiteType} />
      )}

      {!createMode && value.length === 0 && (
        <p className="text-sm text-muted-foreground">
          {t('personalData.addresses.empty')}
        </p>
      )}

      <ul className="flex flex-col gap-2">
        {listed.map((address) => {
          const location = locationSummary(address)
          return (
          <li
            key={address._key}
            className="flex flex-wrap items-center gap-2 rounded-lg border p-3"
          >
            <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
              <MapPin className="size-4" aria-hidden="true" />
            </span>
            <div className="flex min-w-0 flex-1 flex-col">
              <span className="truncate text-sm font-medium">{address.line1}</span>
              {address.line2 && (
                <span className="truncate text-xs text-muted-foreground">{address.line2}</span>
              )}
              {location && (
                <span className="truncate text-xs text-muted-foreground">{location}</span>
              )}
            </div>
            {showSiteType && (
              <Badge variant="outline">{t(SITE_TYPE_LABEL_KEYS[address.site_type ?? 'billing'])}</Badge>
            )}
            {address.is_primary && (
              <Badge variant="secondary">
                {t('personalData.addresses.primaryBadge')}
              </Badge>
            )}
            {!readOnly && (
              <div className="flex shrink-0 gap-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('personalData.addresses.editAction')}
                  onClick={() => setEditing(address._key)}
                >
                  <Pencil aria-hidden="true" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('personalData.addresses.deleteAction')}
                  onClick={() => handleDelete(address._key)}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              </div>
            )}
          </li>
          )
        })}
      </ul>

      {!showHeader && canAdd && (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => setEditing('new')}
          className="self-start"
        >
          <Plus aria-hidden="true" />
          {t('personalData.addresses.add')}
        </Button>
      )}

      <Dialog
        open={editing !== null}
        onOpenChange={(open) => {
          if (!open && !pending) {
            setEditing(null)
          }
        }}
      >
        <DialogContent aria-describedby={undefined}>
          <DialogHeader>
            <DialogTitle>
              {editing === 'new'
                ? t('personalData.addresses.add')
                : t('personalData.addresses.editAction')}
            </DialogTitle>
          </DialogHeader>
          {editing !== null && (
            <AddressForm
              address={editing === 'new' ? undefined : value.find((a) => a._key === editing)}
              onSubmit={
                editing === 'new'
                  ? handleAdd
                  : (fields) => handleEdit(editing, fields)
              }
              onCancel={() => setEditing(null)}
              submitting={pending}
              showSiteType={showSiteType}
            />
          )}
        </DialogContent>
      </Dialog>
    </section>
  )
}
