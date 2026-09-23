/**
 * Sortie du site vers une adresse externe — la passerelle de paiement, et elle
 * seule. Isolée dans sa propre fonction pour que le tunnel reste vérifiable :
 * un test observe l'appel au lieu de subir une navigation.
 */
export function quitterVers(url: string): void {
  window.location.assign(url)
}
