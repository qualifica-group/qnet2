import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import { UserAvatar } from '@/components/user-avatar'
import { UserProfileHoverCard } from '@/components/user-profile-hover-card'
import type { TaskNamedRef } from '@/features/tasks/types'

/** Avatar + name inside the shared user hover card (hover shows the profile action, click opens the user Sheet), as in the Lead detail. */
export function TaskPerson({ person }: { person: TaskNamedRef }) {
  return (
    <UserProfileHoverCard user={person} triggerClassName="rounded-md">
      <UserAvatar name={person.name} src={null} className="shrink-0" />
      <span className="truncate text-sm text-foreground">{person.name}</span>
    </UserProfileHoverCard>
  )
}

interface TaskPeopleListProps {
  people: TaskNamedRef[]
}

/**
 * Comma-free vertical list of people (avatar + name with the user hover card), so long names never truncate into each
 * other. Keyed by id rather than name: assignees and watchers are two
 * independent sets (AC-083) and a homonym is a real possibility.
 */
export function TaskPeopleList({ people }: TaskPeopleListProps) {
  const { t } = useTranslation()

  if (people.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex flex-col gap-1.5" aria-label={t('tasks.detail.peopleList')}>
      {people.map((person) => (
        <li key={person.id} className="min-w-0">
          <TaskPerson person={person} />
        </li>
      ))}
    </ul>
  )
}
