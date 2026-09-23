import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { ErreurApi } from '../api/client'

/**
 * Reporte sur les champs les erreurs de validation du serveur (HTTP 422) et
 * rend le message général à afficher, ou null si tout a trouvé sa place.
 * Les champs du formulaire portent le même nom que ceux de l'API.
 */
export function reporterErreurs<T extends FieldValues>(e: unknown, setError: UseFormSetError<T>, champsConnus: readonly Path<T>[], repli: string): string | null {
  if (!(e instanceof ErreurApi)) return repli
  if (!e.champs) return e.message

  let place = false
  for (const [champ, messages] of Object.entries(e.champs)) {
    if ((champsConnus as readonly string[]).includes(champ) && messages[0]) {
      setError(champ as Path<T>, { message: messages[0] })
      place = true
    }
  }
  return place ? null : e.message
}
