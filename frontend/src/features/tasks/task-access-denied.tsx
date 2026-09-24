import { useTranslation } from 'react-i18next'
import { Mail, ShieldAlert } from 'lucide-react'
import { safeUrl } from '@/lib/safe-url'
import type { TaskAccessContact } from '@/features/tasks/task-access-denied-info'

interface TaskAccessDeniedProps {
  message: string
  contacts: TaskAccessContact[]
}

/**
 * "Accesso non consentito" (spec 0155 D-7): the server's own message plus the
 * requester/creator contacts, name and a `mailto:` link — no Riprova, a 403
 * here is never transient. `safeUrl` restricted to `mailto:` (react-security.md):
 * an `email` value is server data, but the allow-list still applies rather
 * than trusting the shape blindly.
 */
export function TaskAccessDenied({ message, contacts }: TaskAccessDeniedProps) {
  const { t } = useTranslation()

  return (
    <div className="flex flex-col items-start gap-3 p-6">
      <div className="flex items-center gap-2 text-sm font-medium text-foreground">
        <ShieldAlert className="size-4 text-destructive" aria-hidden="true" />
        {t('tasks.detail.accessDenied.title')}
      </div>
      <p className="text-sm text-muted-foreground" role="alert">
        {message}
      </p>

      {contacts.length > 0 ? (
        <div className="flex flex-col gap-1.5">
          <p className="text-xs text-muted-foreground">{t('tasks.detail.accessDenied.contactsIntro')}</p>
          <ul className="flex flex-col gap-1">
            {contacts.map((contact) => {
              const href = safeUrl(`mailto:${contact.email}`, ['mailto:'])
              return (
                <li key={contact.id} className="flex items-center gap-1.5 text-sm">
                  <Mail className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                  {href ? (
                    <a href={href} className="text-primary underline-offset-2 hover:underline">
                      {contact.name}
                    </a>
                  ) : (
                    <span>{contact.name}</span>
                  )}
                </li>
              )
            })}
          </ul>
        </div>
      ) : null}
    </div>
  )
}
