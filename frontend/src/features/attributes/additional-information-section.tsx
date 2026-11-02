import { useState, type ReactNode } from 'react'
import { RecordSection } from '@/components/detail/record-panel'

const CLOSED_VALUE = 'closed'

function readStoredOpen(storageKey: string): boolean {
  try {
    return window.localStorage.getItem(storageKey) !== CLOSED_VALUE
  } catch {
    return true
  }
}

/** The section's open state, kept in the browser under `storageKey`: open until the operator closes it. */
function usePersistedOpen(storageKey: string) {
  const [open, setOpenState] = useState(() => readStoredOpen(storageKey))

  const setOpen = (next: boolean) => {
    setOpenState(next)
    try {
      if (next) {
        window.localStorage.removeItem(storageKey)
      } else {
        window.localStorage.setItem(storageKey, CLOSED_VALUE)
      }
    } catch {
      // Storage can be unavailable (private mode, quota): the section still toggles for this session.
    }
  }

  return { open, setOpen }
}

interface AdditionalInformationSectionProps {
  /** One key per module (e.g. `quotes.additional-information`): every record of that module shares the state. */
  storageKey: string
  title: string
  icon: ReactNode
  className?: string
  children: ReactNode
}

/**
 * The collapsible "Informazioni aggiuntive" of a record (Offerte, Commesse):
 * the operator's open/closed choice is remembered in the browser per module,
 * on the detail and on the create form alike.
 */
export function AdditionalInformationSection({
  storageKey,
  title,
  icon,
  className,
  children,
}: AdditionalInformationSectionProps) {
  const { open, setOpen } = usePersistedOpen(storageKey)

  return (
    <RecordSection title={title} icon={icon} className={className} collapsible open={open} onOpenChange={setOpen}>
      {children}
    </RecordSection>
  )
}
