/** Short label of a `YYYY-MM` month ("Oct 26" / "ott 26") in the given UI language. */
export function monthLabelFormatter(locale: string): (month: string) => string {
  const formatter = new Intl.DateTimeFormat(locale, { month: 'short', year: '2-digit', timeZone: 'UTC' })

  return (month) => {
    const [year, monthNumber] = month.split('-').map(Number)
    return formatter.format(new Date(Date.UTC(year, monthNumber - 1, 1)))
  }
}
