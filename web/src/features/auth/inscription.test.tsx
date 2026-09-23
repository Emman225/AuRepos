import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import '../../shared/i18n'
import { ErreurApi } from '../../shared/api/client'
import * as apiAuth from './api'
import { PageConnexion } from './PageConnexion'
import { PageInscription } from './PageInscription'
import { PageMotDePasseOublie } from './PageMotDePasseOublie'
import { PageVerification } from './PageVerification'
import { useSession } from './session'
import type { SessionOuverte } from './types'

Object.defineProperty(window, 'matchMedia', {
  value: () => ({ matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} }),
})

const session: SessionOuverte = {
  jeton: 'jeton-1',
  type: 'Bearer',
  expire_dans: 86400,
  utilisateur: {
    id: 1, nom: 'Koné', prenoms: 'Awa', nom_complet: 'Awa Koné', email: 'awa@exemple.ci', telephone: null,
    profil: 'client', profil_libelle: 'Client', espace: 'client', agence: null, peut_encaisser: false,
  },
}

function monter(adresse: string, etat?: unknown) {
  return render(
    <MemoryRouter initialEntries={[{ pathname: adresse, state: etat }]}>
      <Routes>
        <Route path="/connexion" element={<PageConnexion />} />
        <Route path="/inscription" element={<PageInscription />} />
        <Route path="/verification" element={<PageVerification />} />
        <Route path="/mot-de-passe-oublie" element={<PageMotDePasseOublie />} />
        <Route path="/mon-espace" element={<p>espace client</p>} />
      </Routes>
    </MemoryRouter>,
  )
}

async function remplirInscription(surcharge: Partial<Record<string, string>> = {}) {
  const v = { nom: 'Koné', courriel: 'awa@exemple.ci', motDePasse: 'Abidjan2026', confirmation: 'Abidjan2026', ...surcharge }
  await userEvent.type(screen.getByLabelText(/^Nom/), v.nom)
  await userEvent.type(screen.getByLabelText(/^Courriel/), v.courriel)
  await userEvent.type(screen.getByLabelText(/^Mot de passe/), v.motDePasse)
  await userEvent.type(screen.getByLabelText(/^Confirmez/), v.confirmation)
}

beforeEach(() => {
  vi.restoreAllMocks()
  useSession.setState({ jeton: null, utilisateur: null, statut: 'anonyme' })
})

describe('inscription', () => {
  it('crée le compte puis emmène à la saisie du code', async () => {
    const appel = vi.spyOn(apiAuth, 'inscription').mockResolvedValue({ email: 'awa@exemple.ci', code_valable_minutes: 15 })
    monter('/inscription')

    await remplirInscription()
    await userEvent.click(screen.getByRole('checkbox'))
    await userEvent.click(screen.getByRole('button', { name: 'Créer mon compte' }))

    expect(await screen.findByRole('heading', { name: 'Vérifiez votre adresse' })).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('awa@exemple.ci')
    expect(appel).toHaveBeenCalledWith(expect.objectContaining({ email: 'awa@exemple.ci', conditions_acceptees: true }))
  })

  it('n’appelle pas le serveur tant que la saisie est incorrecte', async () => {
    const appel = vi.spyOn(apiAuth, 'inscription')
    monter('/inscription')

    await remplirInscription({ motDePasse: 'abcdefghij', confirmation: 'autrechose1' })
    await userEvent.click(screen.getByRole('button', { name: 'Créer mon compte' }))

    expect(await screen.findByText('Le mot de passe doit contenir des lettres et des chiffres.')).toBeInTheDocument()
    expect(screen.getByText('Les deux mots de passe sont différents.')).toBeInTheDocument()
    expect(screen.getByText('Vous devez accepter les conditions générales.')).toBeInTheDocument()
    expect(appel).not.toHaveBeenCalled()
  })

  it('affiche sous le bon champ l’erreur renvoyée par le serveur', async () => {
    vi.spyOn(apiAuth, 'inscription').mockRejectedValue(
      new ErreurApi('Certaines informations sont incorrectes.', 422, { email: ['Un compte existe déjà avec ce courriel.'] }),
    )
    monter('/inscription')

    await remplirInscription()
    await userEvent.click(screen.getByRole('checkbox'))
    await userEvent.click(screen.getByRole('button', { name: 'Créer mon compte' }))

    expect(await screen.findByText('Un compte existe déjà avec ce courriel.')).toBeInTheDocument()
    expect(screen.getByLabelText(/^Courriel/)).toHaveAttribute('aria-invalid', 'true')
  })
})

describe('vérification du code', () => {
  it('ouvre la session et l’espace client avec le bon code', async () => {
    vi.spyOn(apiAuth, 'verifierLeCode').mockResolvedValue(session)
    monter('/verification', { email: 'awa@exemple.ci' })

    await userEvent.type(screen.getByLabelText(/^Code de vérification/), '123456')
    await userEvent.click(screen.getByRole('button', { name: 'Valider le code' }))

    expect(await screen.findByText('espace client')).toBeInTheDocument()
    expect(apiAuth.verifierLeCode).toHaveBeenCalledWith('awa@exemple.ci', '123456')
    expect(useSession.getState().jeton).toBe('jeton-1')
  })

  it('refuse un code qui n’a pas six chiffres sans appeler le serveur', async () => {
    const appel = vi.spyOn(apiAuth, 'verifierLeCode')
    monter('/verification', { email: 'awa@exemple.ci' })

    await userEvent.type(screen.getByLabelText(/^Code de vérification/), '12a')
    await userEvent.click(screen.getByRole('button', { name: 'Valider le code' }))

    expect(await screen.findByText('Le code comporte six chiffres.')).toBeInTheDocument()
    expect(appel).not.toHaveBeenCalled()
  })

  it('fait patienter avant de proposer un nouveau code', () => {
    monter('/verification', { email: 'awa@exemple.ci' })
    expect(screen.getByRole('button', { name: /Nouveau code possible dans/ })).toBeDisabled()
  })

  it('emmène à la vérification un client qui se connecte avant d’avoir saisi son code', async () => {
    vi.spyOn(apiAuth, 'connexion').mockRejectedValue(
      new ErreurApi('Votre adresse n’est pas encore vérifiée.', 403, { code: ['courriel_non_verifie'] }),
    )
    monter('/connexion')

    await userEvent.type(screen.getByLabelText(/^Identifiant/), 'awa@exemple.ci')
    await userEvent.type(screen.getByLabelText(/^Mot de passe/), 'Abidjan2026')
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByRole('heading', { name: 'Vérifiez votre adresse' })).toBeInTheDocument()
    expect(screen.getByLabelText(/^Courriel/)).toHaveValue('awa@exemple.ci')
  })
})

describe('mot de passe oublié', () => {
  it('demande le code, change le mot de passe, puis renvoie à la connexion', async () => {
    vi.spyOn(apiAuth, 'motDePasseOublie').mockResolvedValue({ code_valable_minutes: 15 })
    const changement = vi.spyOn(apiAuth, 'reinitialiserLeMotDePasse').mockResolvedValue(null)
    monter('/mot-de-passe-oublie')

    await userEvent.type(screen.getByLabelText(/^Courriel/), 'awa@exemple.ci')
    await userEvent.click(screen.getByRole('button', { name: 'Recevoir le code' }))

    await userEvent.type(await screen.findByLabelText(/^Code de vérification/), '654321')
    await userEvent.type(screen.getByLabelText(/^Nouveau mot de passe/), 'Nouveau2026')
    await userEvent.type(screen.getByLabelText(/^Confirmez/), 'Nouveau2026')
    await userEvent.click(screen.getByRole('button', { name: 'Changer le mot de passe' }))

    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeInTheDocument()
    expect(screen.getByRole('status')).toHaveTextContent('Mot de passe changé')
    expect(changement).toHaveBeenCalledWith({
      email: 'awa@exemple.ci', code: '654321', mot_de_passe: 'Nouveau2026', mot_de_passe_confirmation: 'Nouveau2026',
    })
  })

  it('affiche sous le champ le refus du code par le serveur', async () => {
    vi.spyOn(apiAuth, 'motDePasseOublie').mockResolvedValue({ code_valable_minutes: 15 })
    vi.spyOn(apiAuth, 'reinitialiserLeMotDePasse').mockRejectedValue(
      new ErreurApi('Certaines informations sont incorrectes.', 422, { code: ['Ce code est incorrect ou a expiré. Demandez-en un nouveau.'] }),
    )
    monter('/mot-de-passe-oublie')

    await userEvent.type(screen.getByLabelText(/^Courriel/), 'awa@exemple.ci')
    await userEvent.click(screen.getByRole('button', { name: 'Recevoir le code' }))
    await userEvent.type(await screen.findByLabelText(/^Code de vérification/), '000000')
    await userEvent.type(screen.getByLabelText(/^Nouveau mot de passe/), 'Nouveau2026')
    await userEvent.type(screen.getByLabelText(/^Confirmez/), 'Nouveau2026')
    await userEvent.click(screen.getByRole('button', { name: 'Changer le mot de passe' }))

    expect(await screen.findByText(/Ce code est incorrect ou a expiré/)).toBeInTheDocument()
  })
})
