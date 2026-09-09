import { AlertTriangle } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { IdentityDuplicateMatch } from '@/features/identity-duplicates/duplicate-check-api'

/** i18n key of each `matched_on` criterion (data_contract of the check). */
const MATCH_CRITERION_LABEL_KEYS: Record<string, string> = {
  email: 'identityDuplicates.criteria.email',
  phone: 'identityDuplicates.criteria.phone',
  mobile: 'identityDuplicates.criteria.mobile',
  tax_code: 'identityDuplicates.criteria.taxCode',
  vat_number: 'identityDuplicates.criteria.vatNumber',
}

/** i18n key of the holder's own kind, so the operator knows WHERE the duplicate sits. */
const OWNER_LABEL_KEYS: Record<string, string> = {
  user: 'identityDuplicates.owners.user',
  registry: 'identityDuplicates.owners.registry',
  referent: 'identityDuplicates.owners.referent',
}

interface IdentityDuplicateWarningProps {
  matches: IdentityDuplicateMatch[]
}

/**
 * Non-blocking amber notice (spec 0037): lists the existing users/anagrafiche/
 * referenti whose contacts, codice fiscale or partita IVA already match what
 * the operator is typing. Purely presentational — `role="status"` (aria-live
 * polite, not `alert`) so assistive tech announces it without interrupting.
 * The save is gated server-side, never by this component (AC-008: renders
 * nothing without matches).
 */
export function IdentityDuplicateWarning({ matches }: IdentityDuplicateWarningProps) {
  const { t } = useTranslation()

  if (matches.length === 0) {
    return null
  }

  return (
    <div
      role="status"
      className="flex flex-col gap-1.5 rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-xs text-amber-700 dark:text-amber-400"
    >
      <span className="flex items-center gap-1.5 font-medium">
        <AlertTriangle className="size-3.5 shrink-0" aria-hidden="true" />
        {t('identityDuplicates.title')}
      </span>
      <ul className="flex flex-col gap-1 pl-5">
        {matches.map((match) => (
          <li key={`${match.owner_type}-${match.owner_id}`} className="list-disc">
            {t('identityDuplicates.entry', {
              owner: t(OWNER_LABEL_KEYS[match.owner_type] ?? match.owner_type),
              name: match.name,
              criteria: match.matched_on
                .map((criterion) => t(MATCH_CRITERION_LABEL_KEYS[criterion] ?? criterion))
                .join(', '),
            })}
          </li>
        ))}
      </ul>
    </div>
  )
}
