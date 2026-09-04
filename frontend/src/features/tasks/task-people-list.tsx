import { useTranslation } from 'react-i18next'
import { DetailEmpty } from '@/components/detail/detail-panel'
import type { TaskNamedRef } from '@/features/tasks/types'

interface TaskPeopleListProps {
  people: TaskNamedRef[]
}

/**
 * Comma-free vertical list of people, so long names never truncate into each
 * other. Keyed by id rather than name: assignees and watchers are two
 * independent sets (AC-083) and a homonym is a real possibility.
 */
export function TaskPeopleList({ people }: TaskPeopleListProps) {
  const { t } = useTranslation()

  if (people.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex flex-col gap-0.5" aria-label={t('tasks.detail.peopleList')}>
      {people.map((person) => (
        <li key={person.id} className="truncate">
          {person.name}
        </li>
      ))}
    </ul>
  )
}
