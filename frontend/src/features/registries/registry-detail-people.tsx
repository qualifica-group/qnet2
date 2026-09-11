import { DetailEmpty } from '@/components/detail/detail-panel'
import { enumLabelOf } from '@/features/config/enum-label'
import type { PrimaryContact } from '@/features/table/types'
import type { ReferenceRef } from '@/features/registries/types'

/**
 * The REFERENTI attached to an anagrafica, as compact cards carrying each
 * person's PRIMARY contacts.
 *
 * Only the referenti: the Supervisore and the G.A. slots left this block for
 * `RegistryTeamSection` (user directive 2026-09-11), which renders them as the
 * Opportunità and Offerta records do. What stays here is the one group that is
 * genuinely a set of cards rather than "one role, one person" rows — a
 * referente holds no position, so there is no label column to line up.
 */

/** A person's primary contacts as muted `type: value` lines. */
export function PersonContactLines({ contacts }: { contacts?: PrimaryContact[] }) {
  if (!contacts || contacts.length === 0) {
    return null
  }
  return (
    <>
      {contacts.map((contact) => (
        <span
          key={`${contact.type}-${contact.value}`}
          className="truncate text-xs text-muted-foreground"
        >
          {enumLabelOf('contact_type', contact.type)}: {contact.value}
        </span>
      ))}
    </>
  )
}

/**
 * A responsible person on a spec-sheet row (supervisore/commerciale/
 * segnalatore): the name plus their primary contacts, or the empty marker.
 */
export function PersonField({ person }: { person: ReferenceRef | null }) {
  if (!person) {
    return <DetailEmpty />
  }
  return (
    <div className="flex flex-col gap-0.5">
      <span>{person.name}</span>
      <PersonContactLines contacts={person.primary_contacts} />
    </div>
  )
}

/** Compact referente card: the name plus their primary contacts. */
function PersonCard({ name, contacts }: { name: string; contacts?: PrimaryContact[] }) {
  return (
    <div className="flex min-w-0 flex-col rounded-lg border p-2.5">
      <span className="truncate text-sm font-medium">{name}</span>
      <PersonContactLines contacts={contacts} />
    </div>
  )
}

interface RegistryReferentsProps {
  referents: ReferenceRef[]
}

/** Responsive card grid: one column on a narrow record, two from `@md`, three from `@3xl`. */
const REFERENTS_GRID_CLASS = 'grid grid-cols-1 gap-2 @md:grid-cols-2 @3xl:grid-cols-3'

export function RegistryReferents({ referents }: RegistryReferentsProps) {
  if (referents.length === 0) {
    return <DetailEmpty />
  }

  return (
    <div className={REFERENTS_GRID_CLASS}>
      {referents.map((referent) => (
        <PersonCard key={referent.id} name={referent.name} contacts={referent.primary_contacts} />
      ))}
    </div>
  )
}
