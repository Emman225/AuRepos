/** « 45 000 F » : séparateur de milliers français, jamais de décimales (F CFA, CdC § 13.2). */
export function formaterPrix(montant: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(montant)} F`
}
