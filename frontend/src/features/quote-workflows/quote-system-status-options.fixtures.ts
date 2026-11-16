import type { WorkflowSystemStatusOption } from '@/features/workflow-statuses/types'

/** The seeded system statuses as the configurators' select receives them (spec 0404 A-D3). */
export const SYSTEM_STATUS_OPTIONS: WorkflowSystemStatusOption[] = [
  { id: 1, label: 'Aperto', color: 'green', behavior: 'open', systemKey: 'open' },
  { id: 2, label: 'In attesa', color: 'orange', behavior: 'pending', systemKey: null },
  { id: 3, label: 'Vinto provvisorio', color: 'violet', behavior: 'validated', systemKey: null },
  { id: 4, label: 'Vinto', color: 'emerald', behavior: 'closed_won', systemKey: 'closed_won' },
  { id: 5, label: 'Perso', color: 'red', behavior: 'closed_lost', systemKey: 'closed_lost' },
]
