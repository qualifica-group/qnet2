import { useConfig } from '@/features/config/use-config'
import { DEFAULT_WRITE_OFF_THRESHOLD } from '@/features/invoices/write-off'

/** Residual (in euros) up to which "write off" is the proposed choice; from `GET /api/config`. */
export function useWriteOffThreshold(): number {
  const { data } = useConfig()
  return Number(data?.treasury?.write_off_suggest_threshold ?? DEFAULT_WRITE_OFF_THRESHOLD)
}
