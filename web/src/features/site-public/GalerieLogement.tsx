import { HomeOutlined } from '@ant-design/icons'
import { Image, Typography } from 'antd'
import { useState } from 'react'
import { couleurs } from '../../shared/theme/jetons'

interface Photo {
  url: string
  url_vignette: string
  legende: string | null
  couverture: boolean
}

interface Props {
  photos: Photo[]
  nom: string
}

/** Galerie légendée : grande photo + vignettes cliquables (CdC § 5.1). */
export function GalerieLogement({ photos, nom }: Props) {
  const [selection, setSelection] = useState(0)

  if (photos.length === 0) {
    return (
      <div
        style={{
          width: '100%',
          height: 320,
          borderRadius: 8,
          background: couleurs.sableClair,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
        }}
      >
        <HomeOutlined style={{ fontSize: 56, color: couleurs.sable }} />
      </div>
    )
  }

  const photo = photos[selection] ?? photos[0]

  return (
    <div>
      <Image
        src={photo.url}
        srcSet={`${photo.url_vignette} 480w, ${photo.url} 1600w`}
        sizes="(max-width: 768px) 100vw, 1100px"
        alt={photo.legende ?? nom}
        loading="eager"
        fetchPriority="high"
        style={{ width: '100%', maxHeight: 480, objectFit: 'cover', borderRadius: 8 }}
      />
      {photo.legende && (
        <Typography.Text style={{ display: 'block', marginTop: 4 }} type="secondary">
          {photo.legende}
        </Typography.Text>
      )}
      {photos.length > 1 && (
        <div style={{ display: 'flex', gap: 8, marginTop: 8, overflowX: 'auto' }}>
          {photos.map((p, i) => (
            <img
              key={p.url_vignette}
              src={p.url_vignette}
              alt={p.legende ?? `${nom} — photo ${i + 1}`}
              loading="lazy"
              onClick={() => setSelection(i)}
              style={{
                width: 88,
                height: 66,
                objectFit: 'cover',
                borderRadius: 6,
                cursor: 'pointer',
                flexShrink: 0,
                outline: i === selection ? '2px solid #1E3A5F' : 'none',
              }}
            />
          ))}
        </div>
      )}
    </div>
  )
}
