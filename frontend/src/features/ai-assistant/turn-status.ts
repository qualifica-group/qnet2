import type { AiTurnStatus } from '@/features/ai-assistant/types'

const FINAL_STATUSES: readonly AiTurnStatus[] = ['completed', 'failed', 'cancelled', 'limit_reached']

/** States in which the server is still working and the browser keeps reading (0433, D-15). */
const POLLING_STATUSES: readonly AiTurnStatus[] = ['queued', 'running']

export const isAiTurnFinal = (status: AiTurnStatus): boolean => FINAL_STATUSES.includes(status)

/** `awaiting_confirmation` is not polled here: the preview card and its expiry arrive with ticket 05. */
export const isAiTurnPolling = (status: AiTurnStatus): boolean => POLLING_STATUSES.includes(status)
