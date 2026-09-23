import { useEffect, useState } from 'react'

/**
 * Rend la valeur seulement après `delai` sans changement. Sert au tunnel de
 * réservation : le serveur recalcule le total pendant la saisie, mais une
 * frappe ne doit pas déclencher un appel par caractère.
 *
 * À n'utiliser qu'avec des valeurs comparables par `===` (nombre, texte,
 * booléen) : un objet reconstruit à chaque rendu relancerait le minuteur sans
 * fin. Pour une saisie entière, on retarde sa forme JSON.
 */
export function useValeurRetardee<T extends string | number | boolean | null | undefined>(valeur: T, delai = 400): T {
  const [retardee, setRetardee] = useState(valeur)

  useEffect(() => {
    const minuteur = setTimeout(() => setRetardee(valeur), delai)
    return () => clearTimeout(minuteur)
  }, [valeur, delai])

  return retardee
}
