import { useState } from 'react'
import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAbsenceCalendar } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import type { AbsenceCalendar, AbsenceCalendarParams } from '@/features/absences/types'
import { monthOf, monthRange, shiftMonth } from '@/features/absences/calendar/calendar-month'

/**
 * State and data of the absence calendar (spec 0279): the shown month, the
 * optional function filter and the server calendar for that period. The
 * previous month stays on screen while the next one loads.
 */
export function useAbsenceCalendar() {
  const [month, setMonth] = useState(() => monthOf(new Date()))
  const [businessFunctionId, setBusinessFunctionId] = useState<number | null>(null)

  const params: AbsenceCalendarParams = {
    ...monthRange(month),
    ...(businessFunctionId === null ? {} : { business_function_id: businessFunctionId }),
  }

  const query = useQuery<AbsenceCalendar, AxiosError>({
    queryKey: absenceKeys.calendar(params),
    queryFn: () => fetchAbsenceCalendar(params),
    placeholderData: keepPreviousData,
  })

  return {
    month,
    showPrevious: () => setMonth((current) => shiftMonth(current, -1)),
    showNext: () => setMonth((current) => shiftMonth(current, 1)),
    showCurrent: () => setMonth(monthOf(new Date())),
    businessFunctionId,
    setBusinessFunctionId,
    query,
  }
}
