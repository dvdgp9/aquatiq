# Iconos PWA para Aquatiq

Coloca aquí los iconos de la aplicación. Todos deben ser **PNG** con fondo transparente o con el color de fondo de la app.

## Iconos Requeridos (Obligatorios)

| Archivo | Tamaño | Uso |
|---------|--------|-----|
| `icon-72x72.png` | 72x72 px | Android legacy |
| `icon-96x96.png` | 96x96 px | Android legacy |
| `icon-128x128.png` | 128x128 px | Chrome Web Store |
| `icon-144x144.png` | 144x144 px | iOS, Windows tiles |
| `icon-152x152.png` | 152x152 px | iPad |
| `icon-192x192.png` | 192x192 px | **Android splash** (crítico) |
| `icon-384x384.png` | 384x384 px | Alta resolución |
| `icon-512x512.png` | 512x512 px | **Play Store / Splash** (crítico) |

## Iconos para Apple (iOS/iPadOS)

Coloca también estos en la raíz del proyecto (`/`):

| Archivo | Tamaño | Uso |
|---------|--------|-----|
| `apple-touch-icon.png` | 180x180 px | **iPhone/iPad home screen** |

## Screenshots (Opcionales pero recomendados)

Para mejorar la experiencia de instalación en Android:

| Archivo | Tamaño | Uso |
|---------|--------|-----|
| `screenshot-wide.png` | 1280x720 px | Vista escritorio |
| `screenshot-mobile.png` | 720x1280 px | Vista móvil |

## Recomendaciones de Diseño

1. **Safe Zone para Maskable Icons**: Los iconos deben tener contenido importante dentro del 80% central (zona segura) ya que Android puede recortarlos en círculo u otras formas.

2. **Colores sugeridos**:
   - Color principal: `#0077be` (azul agua)
   - Fondo: Blanco `#ffffff` o transparente

3. **Herramientas útiles**:
   - [Maskable.app](https://maskable.app/) - Para verificar zona segura
   - [PWA Asset Generator](https://github.com/nicholasbraun/pwa-asset-generator) - Generar todos los tamaños desde una imagen
   - [RealFaviconGenerator](https://realfavicongenerator.net/) - Generador completo

4. **Comando rápido** (si tienes ImageMagick instalado):
   ```bash
   # Desde una imagen original de 512x512 o mayor
   convert logo-original.png -resize 72x72 icon-72x72.png
   convert logo-original.png -resize 96x96 icon-96x96.png
   convert logo-original.png -resize 128x128 icon-128x128.png
   convert logo-original.png -resize 144x144 icon-144x144.png
   convert logo-original.png -resize 152x152 icon-152x152.png
   convert logo-original.png -resize 192x192 icon-192x192.png
   convert logo-original.png -resize 384x384 icon-384x384.png
   convert logo-original.png -resize 512x512 icon-512x512.png
   convert logo-original.png -resize 180x180 ../apple-touch-icon.png
   ```

## Checklist

- [ ] icon-72x72.png
- [ ] icon-96x96.png
- [ ] icon-128x128.png
- [ ] icon-144x144.png
- [ ] icon-152x152.png
- [ ] icon-192x192.png (crítico)
- [ ] icon-384x384.png
- [ ] icon-512x512.png (crítico)
- [ ] apple-touch-icon.png (en raíz)
- [ ] screenshot-wide.png (opcional)
- [ ] screenshot-mobile.png (opcional)
