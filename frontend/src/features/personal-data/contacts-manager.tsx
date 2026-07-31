import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Pencil, Phone, Plus, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { useConfirm } from '@/components/confirm-dialog-context'
import { ContactForm } from '@/features/personal-data/contact-form'
import { ContactsCreateFields } from '@/features/personal-data/contacts-create-fields'
import { createContact, deleteContact, updateContact } from '@/features/personal-data/api'
import { contactToDraft, nextDraftKey } from '@/features/personal-data/drafts'
import { quickOwnedKeys, type QuickContactType } from '@/features/personal-data/quick-contacts'
import { useImmediatePersist } from '@/features/personal-data/use-immediate-persist'
import { useEnumOptions } from '@/features/config/use-config'
import type {
  ContactDraft,
  OwnerRef,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'

interface ContactsManagerProps {
  /** The buffered contacts owned by the parent form. */
  value: ContactDraft[]
  /** Emits the next buffer after any add/edit/remove. */
  onChange: (next: ContactDraft[]) => void
  /**
   * Resolves gating for the whole section (`personal_data.contacts`, spec
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
   * Renders the quick-create fields (email/phone/pec/fax) above the list and
   * hides them from it (`quickOwnedKeys`), so typing there and the "Add
   * contact" dialog never double-show the same row. Default `false` keeps
   * today's exact CRUD-only behaviour; only meaningful in create mode.
   */
  createMode?: boolean
  /**
   * Quick fields the owner's create rule makes mandatory (asterisk only — the
   * blocking check belongs to the owner's submit gate). Forwarded verbatim to
   * `ContactsCreateFields`; ignored outside `createMode`.
   */
  requiredCreateTypes?: QuickContactType[]
}

/** `new` = the add form is open; a string = that contact `_key` is being edited. */
type EditingState = 'new' | string | null

/**
 * Reusable CRUD list for an owner's contacts. The create/edit form opens in a
 * dialog. Two write modes, chosen by `persistence`: buffered (mutate through
 * `onChange`, persisted with the parent user payload — ADR 0012) or immediate
 * (persist each change straight away and sync the buffer with the server row).
 * Enforces one primary contact per type, mirroring the backend. In
 * `createMode`, four quick fields (email/phone/pec/fax) sit above the list,
 * each bound to the first draft of its type; the list then shows only the
 * remaining, non-quick-owned contacts.
 */
export function ContactsManager({
  value,
  onChange,
  fieldPermission,
  showHeader = true,
  persistence,
  createMode = false,
  requiredCreateTypes,
}: ContactsManagerProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { pending, run } = useImmediatePersist()
  const [editing, setEditing] = useState<EditingState>(null)
  const typeOptions = useEnumOptions('contact_type')
  const permission = fieldPermission?.('personal_data.contacts')

  if (permission && !permission.visible) {
    return null
  }
  const readOnly = permission ? permission.disabled || !permission.editable : false
  // Create mode: the quick fields own the first draft of each quick type, so
  // the CRUD list excludes those rows (they render above, never in the list).
  const visibleContacts = createMode
    ? value.filter((contact) => !quickOwnedKeys(value).has(contact._key))
    : value

  /** Resolves a contact `type` enum value to its localized label. */
  const typeLabelOf = (type: string) =>
    typeOptions.find((option) => option.value === type)?.label ?? type

  /** Demotes other contacts of the same type when one is marked primary. */
  const enforcePrimary = (
    contacts: ContactDraft[],
    primaryKey: string,
    type: string,
  ): ContactDraft[] =>
    contacts.map((contact) =>
      contact._key !== primaryKey && contact.type === type
        ? { ...contact, is_primary: false }
        : contact,
    )

  /** Merges a just-added draft into the buffer, enforcing the primary rule. */
  const appendDraft = (draft: ContactDraft) => {
    const next = [...value, draft]
    onChange(draft.is_primary ? enforcePrimary(next, draft._key, draft.type) : next)
  }

  /** Replaces the draft at `key` in the buffer, enforcing the primary rule. */
  const replaceDraft = (key: string, draft: ContactDraft) => {
    const next = value.map((contact) => (contact._key === key ? draft : contact))
    onChange(draft.is_primary ? enforcePrimary(next, draft._key, draft.type) : next)
  }

  const handleAdd = async (fields: Omit<ContactDraft, '_key'>) => {
    if (persistence) {
      const ok = await run(
        async () => {
          const created = await createContact({
            ...fields,
            contactable_type: persistence.type,
            contactable_id: persistence.id,
          })
          appendDraft(contactToDraft(created))
        },
        'personalData.contacts.created',
        'personalData.contacts.genericError',
      )
      if (ok) {
        setEditing(null)
      }
      return
    }
    appendDraft({ ...fields, _key: nextDraftKey() })
    setEditing(null)
  }

  const handleEdit = async (key: string, fields: Omit<ContactDraft, '_key'>) => {
    const current = value.find((contact) => contact._key === key)
    if (persistence && current?.id !== undefined) {
      const ok = await run(
        async () => {
          const updated = await updateContact(current.id!, {
            type: fields.type,
            value: fields.value,
            label: fields.label,
            is_primary: fields.is_primary,
          })
          replaceDraft(key, { ...contactToDraft(updated), _key: key })
        },
        'personalData.contacts.updated',
        'personalData.contacts.genericError',
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
      title: t('personalData.contacts.deleteAction'),
      description: t('personalData.contacts.deleteConfirm'),
      confirmLabel: t('personalData.contacts.deleteAction'),
    })
    if (!confirmed) {
      return
    }
    const current = value.find((contact) => contact._key === key)
    if (persistence && current?.id !== undefined) {
      await run(
        () => deleteContact(current.id!).then(() => onChange(value.filter((c) => c._key !== key))),
        'personalData.contacts.deleted',
        'personalData.contacts.genericError',
      )
      return
    }
    onChange(value.filter((contact) => contact._key !== key))
  }

  const editingContact = typeof editing === 'string' && editing !== 'new'
    ? value.find((contact) => contact._key === editing)
    : undefined

  return (
    <section className="flex flex-col gap-2">
      {showHeader && (
        <div className="flex items-center justify-between">
          <h4 className="text-sm font-medium">{t('personalData.contacts.title')}</h4>
          {!readOnly && (
            <Button type="button" variant="outline" size="sm" onClick={() => setEditing('new')}>
              <Plus aria-hidden="true" />
              {t('personalData.contacts.add')}
            </Button>
          )}
        </div>
      )}

      {createMode && (
        <ContactsCreateFields
          value={value}
          onChange={onChange}
          requiredTypes={requiredCreateTypes}
        />
      )}

      {visibleContacts.length === 0 && (
        <p className="text-sm text-muted-foreground">
          {t('personalData.contacts.empty')}
        </p>
      )}

      <ul className="flex flex-col gap-2">
        {visibleContacts.map((contact) => (
          <li
            key={contact._key}
            className="flex flex-wrap items-center gap-2 rounded-lg border p-3"
          >
            <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
              <Phone className="size-4" aria-hidden="true" />
            </span>
            <div className="flex min-w-0 flex-1 flex-col">
              <span className="truncate text-sm font-medium">{contact.value}</span>
              <span className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                <Badge variant="outline" className="font-normal">
                  {typeLabelOf(contact.type)}
                </Badge>
                {contact.label && <span className="truncate">{contact.label}</span>}
              </span>
            </div>
            {contact.is_primary && (
              <Badge variant="secondary">
                {t('personalData.contacts.primaryBadge')}
              </Badge>
            )}
            {!readOnly && (
              <div className="flex shrink-0 gap-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('personalData.contacts.editAction')}
                  onClick={() => setEditing(contact._key)}
                >
                  <Pencil aria-hidden="true" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('personalData.contacts.deleteAction')}
                  onClick={() => handleDelete(contact._key)}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              </div>
            )}
          </li>
        ))}
      </ul>

      {!showHeader && !readOnly && (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => setEditing('new')}
          className="self-start"
        >
          <Plus aria-hidden="true" />
          {t('personalData.contacts.add')}
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
                ? t('personalData.contacts.add')
                : t('personalData.contacts.editAction')}
            </DialogTitle>
          </DialogHeader>
          {editing !== null && (
            <ContactForm
              contact={editingContact}
              onSubmit={
                editing === 'new'
                  ? handleAdd
                  : (fields) => handleEdit(editing, fields)
              }
              onCancel={() => setEditing(null)}
              submitting={pending}
            />
          )}
        </DialogContent>
      </Dialog>
    </section>
  )
}
