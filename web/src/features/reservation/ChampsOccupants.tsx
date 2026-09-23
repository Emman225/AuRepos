import { DeleteOutlined, PlusOutlined } from '@ant-design/icons'
import { Alert, Button, Card, Checkbox, Col, Form, Input, Row, Select, Space, Typography } from 'antd'
import { Controller, useFieldArray, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { OCCUPANT_VIDE, TYPES_DE_PIECE, type SaisieFormulaire } from './formulaire'

interface Props {
  control: Control<SaisieFormulaire>
  /** adultes + enfants annoncés : le serveur refuse une liste entamée qui ne les couvre pas tous. */
  occupantsAnnonces: number
}

/**
 * Occupants nommés (CdC § 5.2). Facultatifs : on peut réserver sans en décrire
 * aucun. Mais dès qu'on en décrit un, le serveur les veut tous — c'est dit ici,
 * avant la soumission, et c'est lui qui tranche.
 */
export function ChampsOccupants({ control, occupantsAnnonces }: Props) {
  const { t } = useTranslation()
  const { fields, append, remove } = useFieldArray({ control, name: 'occupants' })

  return (
    <Space orientation="vertical" size="middle" style={{ width: '100%' }}>
      <Typography.Text type="secondary">{t('reservation.occupants.aide')}</Typography.Text>

      {fields.length > 0 && fields.length !== occupantsAnnonces && (
        <Alert
          type="warning"
          showIcon
          title={t('reservation.occupants.incoherents', { decrits: fields.length, annonces: occupantsAnnonces })}
        />
      )}

      {fields.map((champ, index) => (
        <Card key={champ.id} size="small" title={t('reservation.occupants.numero', { numero: index + 1 })}
          extra={
            <Button
              type="text"
              danger
              icon={<DeleteOutlined />}
              onClick={() => remove(index)}
              aria-label={t('reservation.occupants.retirer', { numero: index + 1 })}
            />
          }
        >
          <Row gutter={[12, 0]}>
            <Col xs={24} sm={12}>
              <Controller
                control={control}
                name={`occupants.${index}.nom`}
                render={({ field, fieldState }) => {
                  const message = fieldState.error?.message
                  return (
                    <Form.Item
                      label={t('reservation.occupants.nom')}
                      htmlFor={`occupant-nom-${index}`}
                      required
                      validateStatus={message ? 'error' : undefined}
                      help={message ? t(message) : undefined}
                    >
                      <Input {...field} id={`occupant-nom-${index}`} maxLength={100} />
                    </Form.Item>
                  )
                }}
              />
            </Col>
            <Col xs={24} sm={12}>
              <Controller
                control={control}
                name={`occupants.${index}.prenoms`}
                render={({ field }) => (
                  <Form.Item label={t('reservation.occupants.prenoms')} htmlFor={`occupant-prenoms-${index}`}>
                    <Input {...field} id={`occupant-prenoms-${index}`} maxLength={150} />
                  </Form.Item>
                )}
              />
            </Col>
            <Col xs={24} sm={8}>
              <Controller
                control={control}
                name={`occupants.${index}.type_piece`}
                render={({ field }) => (
                  <Form.Item label={t('reservation.occupants.typePiece')} htmlFor={`occupant-piece-${index}`}>
                    <Select
                      {...field}
                      id={`occupant-piece-${index}`}
                      allowClear
                      value={field.value ?? undefined}
                      onChange={(valeur) => field.onChange(valeur ?? null)}
                      placeholder={t('reservation.occupants.typePieceAucun')}
                      options={TYPES_DE_PIECE.map((type) => ({ value: type, label: t(`reservation.pieces.${type}`) }))}
                    />
                  </Form.Item>
                )}
              />
            </Col>
            <Col xs={24} sm={8}>
              <Controller
                control={control}
                name={`occupants.${index}.numero_piece`}
                render={({ field }) => (
                  <Form.Item label={t('reservation.occupants.numeroPiece')} htmlFor={`occupant-numero-${index}`}>
                    <Input {...field} id={`occupant-numero-${index}`} maxLength={60} />
                  </Form.Item>
                )}
              />
            </Col>
            <Col xs={24} sm={8}>
              <Controller
                control={control}
                name={`occupants.${index}.telephone`}
                render={({ field }) => (
                  <Form.Item label={t('reservation.occupants.telephone')} htmlFor={`occupant-telephone-${index}`}>
                    <Input {...field} id={`occupant-telephone-${index}`} inputMode="tel" maxLength={30} />
                  </Form.Item>
                )}
              />
            </Col>
            <Col xs={24}>
              <Controller
                control={control}
                name={`occupants.${index}.enfant`}
                render={({ field }) => (
                  <Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)}>
                    {t('reservation.occupants.enfant')}
                  </Checkbox>
                )}
              />
            </Col>
          </Row>
        </Card>
      ))}

      <Button icon={<PlusOutlined />} onClick={() => append({ ...OCCUPANT_VIDE })}>
        {t('reservation.occupants.ajouter')}
      </Button>
    </Space>
  )
}
