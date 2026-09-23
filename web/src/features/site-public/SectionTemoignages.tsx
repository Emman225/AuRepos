import { UserOutlined } from '@ant-design/icons'
import { Avatar, Card, Col, Rate, Row, Space, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import type { Temoignage } from './types'

interface Props {
  temoignages: Temoignage[]
}

/** Témoignages mis en avant (CdC § 5.1), distincts des avis vérifiés de fin de séjour (P2-AVI-01). */
export function SectionTemoignages({ temoignages }: Props) {
  const { t } = useTranslation()
  if (temoignages.length === 0) return null

  return (
    <section>
      <Typography.Title level={2}>{t('accueil.temoignages.titre')}</Typography.Title>
      <Row gutter={[16, 16]}>
        {temoignages.map((temoignage) => (
          <Col key={temoignage.id} xs={24} sm={12} lg={8}>
            <Card>
              {temoignage.note !== null && <Rate disabled value={temoignage.note} style={{ fontSize: 16 }} />}
              <Typography.Paragraph italic>« {temoignage.message} »</Typography.Paragraph>
              <Space>
                <Avatar src={temoignage.photo ?? undefined} icon={!temoignage.photo && <UserOutlined />} />
                <Typography.Text strong>{temoignage.nom_client}</Typography.Text>
              </Space>
            </Card>
          </Col>
        ))}
      </Row>
    </section>
  )
}
