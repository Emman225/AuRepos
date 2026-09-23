import { Form, Input } from 'antd'
import type { ReactNode } from 'react'
import { Controller, type Control, type FieldValues, type Path } from 'react-hook-form'
import { useTranslation } from 'react-i18next'

interface Props<T extends FieldValues> {
  control: Control<T>
  nom: Path<T>
  libelle: string
  aide?: string
  obligatoire?: boolean
  type?: 'texte' | 'motDePasse'
  prefixe?: ReactNode
  autoComplete?: string
  inputMode?: 'text' | 'numeric' | 'tel' | 'email'
  maxLength?: number
  autoFocus?: boolean
}

/**
 * Champ de formulaire : libellé RELIÉ au champ (lecteurs d'écran, clic sur
 * le libellé), astérisque sur les champs obligatoires, erreur sous le champ.
 * Le message d'erreur est soit une clé de traduction (contrôle du navigateur),
 * soit une phrase déjà en français venue du serveur.
 */
export function Champ<T extends FieldValues>({ control, nom, libelle, aide, obligatoire, type = 'texte', prefixe, ...reste }: Props<T>) {
  const { t, i18n } = useTranslation()
  const id = `champ-${nom}`

  return (
    <Controller
      name={nom}
      control={control}
      render={({ field, fieldState }) => {
        const message = fieldState.error?.message
        const erreur = message ? (i18n.exists(message) ? t(message) : message) : undefined
        const Saisie = type === 'motDePasse' ? Input.Password : Input

        return (
          <Form.Item label={libelle} htmlFor={id} required={obligatoire} validateStatus={erreur ? 'error' : undefined} help={erreur ?? aide}>
            <Saisie {...field} {...reste} id={id} size="large" prefix={prefixe} aria-invalid={erreur ? true : undefined} />
          </Form.Item>
        )
      }}
    />
  )
}

