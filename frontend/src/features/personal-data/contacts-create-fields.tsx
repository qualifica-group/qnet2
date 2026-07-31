import { useTranslation } from 'react-i18next'
import { Input } from '@/components/ui/input'
import { formatContactValue } from '@/lib/formatting/input-format'
import { buildContactSchema } from '@/features/personal-data/contact-schema'
import { nextDraftKey } from '@/features/personal-data/drafts'
import {
  QUICK_CONTACT_TYPES,
  firstOfType,
  type QuickContactType,
} from '@/features/personal-data/quick-contacts'
import type { ContactDraft } from '@/features/personal-data/types'

const QUICK_LABEL_KEYS: Record<QuickContactType, string> = {
  email: 'personalData.contacts.quickEmail',
  phone: 'personalData.contacts.quickPhone',
  pec: 'personalData.contacts.quickPec',
  fax: 'personalData.contacts.quickFax',
}

/** Hoisted so the default prop keeps a stable identity across renders. */
const NO_REQUIRED_TYPES: QuickContactType[] = []

interface ContactsCreateFieldsProps {
  /** The full buffered contacts (quick-owned + any added through the dialog). */
  value: ContactDraft[]
  /** Emits the next buffer after a quick field is typed into or cleared. */
  onChange: (next: ContactDraft[]) => void
  /**
   * Quick fields the owner's create rule makes mandatory: marked with an
   * asterisk here, while the blocking check stays in the owner's own submit
   * gate. Defaults to none, so every other owner is unaffected.
   */
  requiredTypes?: QuickContactType[]
}

/**
 * Four always-visible, fully-controlled inline fields for the quick-create
 * flow: email, phone, pec, fax. Each is bound to the first buffered draft of
 * its type — typing appends/replaces/removes that draft directly in the
 * parent buffer, no RHF and no dialog. Validates per-type on every change,
 * reusing the same schema as the dialog `ContactForm`. Extracted from
 * `ContactsManager` to keep it within the file size limits.
 */
export function ContactsCreateFields({
  value,
  onChange,
  requiredTypes = NO_REQUIRED_TYPES,
}: ContactsCreateFieldsProps) {
  const { t } = useTranslation()
  const schema = buildContactSchema(t)

  const handleChange = (type: QuickContactType, text: string) => {
    const existing = firstOfType(value, type)

    if (!existing) {
      if (text === '') {
        return
      }
      onChange([
        ...value,
        { type, value: text, label: null, is_primary: true, _key: nextDraftKey() },
      ])
      return
    }

    if (text === '') {
      onChange(value.filter((contact) => contact._key !== existing._key))
      return
    }

    onChange(
      value.map((contact) =>
        contact._key === existing._key ? { ...contact, value: text } : contact,
      ),
    )
  }

  /** The per-type shape error for an existing draft, or `null` when valid/empty. */
  const errorOf = (draft: ContactDraft | undefined): string | null => {
    if (!draft) {
      return null
    }
    const result = schema.safeParse({
      type: draft.type,
      value: draft.value,
      label: draft.label ?? '',
      is_primary: draft.is_primary,
    })
    if (result.success) {
      return null
    }
    return result.error.issues.find((issue) => issue.path[0] === 'value')?.message ?? null
  }

  return (
    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
      {QUICK_CONTACT_TYPES.map((type) => {
        const draft = firstOfType(value, type)
        const error = errorOf(draft)
        const inputId = `contact-quick-${type}`
        const errorId = `${inputId}-error`
        const required = requiredTypes.includes(type)
        return (
          <div key={type} className="flex flex-col gap-1.5">
            <label htmlFor={inputId} className="text-sm font-medium">
              {t(QUICK_LABEL_KEYS[type])}
              {required && (
                <span className="ml-1 text-destructive" aria-hidden="true">
                  *
                </span>
              )}
            </label>
            <Input
              id={inputId}
              autoComplete="off"
              value={draft?.value ?? ''}
              onChange={(event) => handleChange(type, event.target.value)}
              onBlur={(event) => handleChange(type, formatContactValue(type, event.target.value))}
              aria-required={required}
              aria-invalid={error !== null}
              aria-describedby={error ? errorId : undefined}
            />
            {error && (
              <span id={errorId} role="alert" className="text-sm text-destructive">
                {error}
              </span>
            )}
          </div>
        )
      })}
    </div>
  )
}
