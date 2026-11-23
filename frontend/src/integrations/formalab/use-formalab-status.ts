import { useQuery } from '@tanstack/react-query'
import { fetchFormalabStatus } from '@/integrations/formalab/api'
import { formalabKeys } from '@/integrations/formalab/query-keys'

export function useFormalabStatus() {
  return useQuery({ queryKey: formalabKeys.status, queryFn: fetchFormalabStatus, retry: false })
}
