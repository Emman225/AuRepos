import dayjs from 'dayjs'

/** « 10/11/2026 » à partir d'une date ISO renvoyée par l'API (`Y-m-d`). */
export function formaterDate(iso: string): string {
  return dayjs(iso).format('DD/MM/YYYY')
}
