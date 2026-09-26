# Aura — Design System

> Tokens visuais do produto **Aura** — *Assistente Unificado de Recursos e Análises*.
> Tagline: *Aura: Inteligência invisível, controle absoluto.*
> Referência de UI adaptada do moodboard Skillway.

## 0. Marca na interface

| Elemento | Spec |
| --- | --- |
| Wordmark | **Aura** — Poppins Bold (`700`), `color.text.primary` (`#FCFDFC`) |
| Sigla (uso raro / docs) | A.U.R.A. — não poluir a UI com a expansão; reservar para About / docs |
| Tagline | *Aura: Inteligência invisível, controle absoluto.* — `text.caption` ou `text.body`, `color.text.secondary`, weight `400` |
| Login / Figma (1º viewport) | Wordmark hero + tagline de apoio + formulário; sem competir com outros headlines |
| Ícone de marca | Accent `#DCCFFF` + wordmark claro sobre fundo `#151716` |

## 1. Paleta de Cores (Color Tokens)

### 1.1 Branding (explícito)

| Token | Hex | Uso |
| --- | --- | --- |
| `color.brand.primary` | `#DCCFFF` | Cor da marca; cards em destaque; badge “Mandatory”; item ativo do overview; play button; barras de progresso ativas |
| `color.bg.default` | `#151716` | Fundo global da aplicação / canvas do dashboard |
| `color.text.primary` | `#FCFDFC` | Títulos, números em destaque, links ativos, texto principal |

### 1.2 Superfícies

| Token | Hex | Uso |
| --- | --- | --- |
| `color.surface.default` | `#1C1E1D` | Fundo de cards elevados (métricas, progress, schedule) |
| `color.surface.raised` | `#2C2E2D` | Cards de curso / listagens com maior contraste |
| `color.surface.sunken` | `#101211` | Áreas rebaixadas (track de gráfico, player de vídeo) |
| `color.surface.brand` | `#DCCFFF` | Superfície filled da marca (featured course, lesson ativa) |
| `color.surface.inverse` | `#FCFDFC` | Fundo de controles ativos (tabs/filters selecionados) |

### 1.3 Bordas

| Token | Hex | Uso |
| --- | --- | --- |
| `color.border.subtle` | `#2A2C2B` | Contorno fino de cards escuros |
| `color.border.default` | `#3A3C3B` | Contorno de pills/botões secundários inativos |
| `color.border.muted` | `#484A49` | Divisores e chevrons circulares |

### 1.4 Texto

| Token | Hex | Uso |
| --- | --- | --- |
| `color.text.primary` | `#FCFDFC` | Hierarquia principal |
| `color.text.secondary` | `#9A9C9B` | Labels, metadados, nav inativa, breadcrumbs |
| `color.text.muted` | `#6E706F` | Captions de baixa prioridade (deltas vs período anterior, hints) |
| `color.text.on-brand` | `#151716` | Texto/ícones sobre fundo `#DCCFFF` |
| `color.text.on-inverse` | `#151716` | Texto sobre pills/tabs brancos |
| `color.text.disabled` | `#525554` | Estados desabilitados |

### 1.5 Interação & overlays

| Token | Hex / valor | Uso |
| --- | --- | --- |
| `color.interactive.primary` | `#DCCFFF` | CTAs e highlights de marca |
| `color.interactive.inverse` | `#FCFDFC` | Tab/filter ativo |
| `color.interactive.dark` | `#0A0B0A` | Botão “More details” sobre card brand |
| `color.overlay.scrim` | `rgba(21, 23, 22, 0.72)` | Overlay do player / modais |
| `color.brand.muted` | `rgba(220, 207, 255, 0.35)` | Barras inativas do chart vertical |

### 1.6 Feedback

| Token | Hex | Uso |
| --- | --- | --- |
| `color.feedback.neutral` | `#9A9C9B` | Deltas informativos (“+2 courses vs last month”) |
| `color.feedback.positive` | `#A8E6C3` | Indicadores de crescimento positivo (quando aplicados) |
| `color.feedback.warning` | — | Não observado nas referências |
| `color.feedback.danger` | — | Não observado nas referências |

---

## 2. Tipografia (Typography Tokens)

### 2.1 Família

| Token | Valor |
| --- | --- |
| `font.family.sans` | `"Poppins", system-ui, sans-serif` |

### 2.2 Escala tipográfica

| Token | Tamanho | Line-height | Letter-spacing | Uso |
| --- | --- | --- | --- | --- |
| `text.display` | `36px` / `2.25rem` | `1.1` (`40px`) | `-0.02em` | Números hero nos metric cards (`8`, `82%`, `14`) |
| `text.heading-1` | `28px` / `1.75rem` | `1.25` (`35px`) | `-0.01em` | Título da lição / página |
| `text.heading-2` | `22px` / `1.375rem` | `1.3` (`28px`) | `0` | Seções: “My Progress”, “My Schedule”, “Lesson Overview” |
| `text.heading-3` | `18px` / `1.125rem` | `1.35` (`24px`) | `0` | Títulos de cards de curso / lição |
| `text.body` | `14px` / `0.875rem` | `1.5` (`21px`) | `0` | Descrições, corpo de texto |
| `text.body-lg` | `16px` / `1rem` | `1.5` (`24px`) | `0` | Nav principal, labels de destaque |
| `text.caption` | `12px` / `0.75rem` | `1.4` (`17px`) | `0.01em` | Metadados (due date, duração, role do perfil) |
| `text.small` | `11px` / `0.6875rem` | `1.35` (`15px`) | `0.02em` | Badges compactos, tooltips do chart |

### 2.3 Pesos (font-weight)

| Token | Weight | Contextos |
| --- | --- | --- |
| `font.weight.regular` | `400` | Body, descrições, metadados, nav inativa |
| `font.weight.medium` | `500` | Labels de filtros, captions de cards, horário no schedule |
| `font.weight.semibold` | `600` | Headings de seção (H2), nav ativa, títulos de lição em lista |
| `font.weight.bold` | `700` | Display numbers, H1 de página, logo wordmark |

### 2.4 Mapeamento por contexto

| Contexto | Style | Weight |
| --- | --- | --- |
| Logo “Aura” | `text.body-lg` | `700` |
| Nav link (ativa) | `text.body` / `text.body-lg` | `600` + `color.text.primary` |
| Nav link (inativa) | `text.body` / `text.body-lg` | `400` + `color.text.secondary` |
| Metric number | `text.display` | `600`–`700` |
| Section title | `text.heading-2` | `600` |
| Course / lesson title | `text.heading-3` | `600` |
| Body description | `text.body` | `400` |
| Badge / pill label | `text.caption` | `500` |
| Profile name | `text.caption` | `600` |
| Profile role | `text.small` | `400` |

---

## 3. Geometria e Espaçamento (Spacing & Shape Tokens)

### 3.1 Border Radius

| Token | Valor | Uso |
| --- | --- | --- |
| `radius.none` | `0` | — |
| `radius.sm` | `8px` | Ícones em container, chevrons circulares internos, tooltips compactos |
| `radius.md` | `12px` | Cards de lição na sidebar (inativos) |
| `radius.lg` | `16px` | Cards de curso horizontais, containers médios |
| `radius.xl` | `24px` | Cards do dashboard, player de vídeo, painel “Lesson Overview” |
| `radius.2xl` | `32px` | Shell externo do dashboard / janela principal |
| `radius.full` | `9999px` | Botões, filters, badges, tabs, avatares, date picker ativo (pill) |

### 3.2 Escala de espaçamento

| Token | Valor | Uso típico |
| --- | --- | --- |
| `space.1` | `4px` | Micro-gap (ícone ↔ label em badges) |
| `space.2` | `8px` | Gap entre metadados; padding vertical de chips |
| `space.3` | `12px` | Gap entre lesson cards; padding horizontal de pills |
| `space.4` | `16px` | Padding interno de cards compactos; gap de nav items |
| `space.5` | `20px` | Gap da grid do dashboard (gutter entre cards) |
| `space.6` | `24px` | Padding padrão de cards |
| `space.8` | `32px` | Padding generoso / padding do shell |
| `space.10` | `40px` | Separação entre blocos maiores (header → content) |
| `space.12` | `48px` | Margem de seção |

### 3.3 Layout

| Token | Valor | Descrição |
| --- | --- | --- |
| `layout.grid.gap` | `20px` | Espaço entre cards da grid |
| `layout.page.padding` | `24px`–`32px` | Padding interno do canvas |
| `layout.card.padding` | `24px` | Padding padrão de card |
| `layout.card.padding-sm` | `16px` | Padding de cards de lista / lesson items |
| `layout.max-width` | fluido (dashboard full-bleed) | Conteúdo ocupa a viewport com gutters laterais |

---

## 4. Especificação de Componentes

### 4.1 Cards

#### Card padrão (métricas / progress / schedule)

| Propriedade | Valor |
| --- | --- |
| Background | `color.surface.default` (`#1C1E1D`) |
| Border | `1px solid color.border.subtle` (`#2A2C2B`) |
| Border radius | `radius.xl` (`24px`) |
| Padding | `space.6` (`24px`) |
| Título | `text.heading-2` / weight `600` / `color.text.primary` |
| Valor display | `text.display` / weight `600`–`700` / `color.text.primary` |
| Caption / delta | `text.caption` / `color.text.muted` |
| Ação auxiliar | Ícone outline (arrow-up-right) no canto superior direito |

#### Card de curso (lista)

| Propriedade | Valor |
| --- | --- |
| Background | `color.surface.raised` (`#2C2E2D`) |
| Border radius | `radius.lg` (`16px`) |
| Padding | `16px`–`20px` |
| Badge | Pill brand (`#DCCFFF`) + texto `on-brand` |
| Progress bar | Track escuro + fill brand (ver §4.4) |

#### Card featured / brand

| Propriedade | Valor |
| --- | --- |
| Background | `color.surface.brand` (`#DCCFFF`) |
| Border | nenhuma (ou transparente) |
| Border radius | `radius.xl` (`24px`) |
| Texto | `color.text.on-brand` (`#151716`) |
| CTA | Botão filled dark (`color.interactive.dark`) + texto claro |

#### Lesson item (sidebar)

| Estado | Background | Texto | Radius |
| --- | --- | --- | --- |
| Inativo | `color.surface.raised` | primary / secondary | `radius.md` (`12px`) |
| Ativo / expandido | `color.brand.primary` | `on-brand` | `radius.md`–`lg` |
| Padding | `16px` | — | — |
| Gap entre itens | `12px` | — | — |

---

### 4.2 Botões e Badges

#### Botão primário (brand)

| Propriedade | Valor |
| --- | --- |
| Background | `#DCCFFF` |
| Texto / ícone | `#151716` |
| Radius | `radius.full` |
| Padding | `10px 20px` |
| Type | `text.caption`–`text.body` / weight `500`–`600` |
| Exemplo | Play overlay no vídeo; accents de marca |

#### Botão secundário (dark)

| Propriedade | Valor |
| --- | --- |
| Background | `color.surface.default` ou transparente |
| Border | `1px solid color.border.default` |
| Texto | `color.text.primary` |
| Radius | `radius.full` |
| Padding | `8px 16px` |
| Exemplo | Filters inativos (“All”, “Completed”); tabs inativas |

#### Botão inverso (ativo / selected)

| Propriedade | Valor |
| --- | --- |
| Background | `#FCFDFC` |
| Texto | `#151716` |
| Radius | `radius.full` |
| Exemplo | Filter “Mandatory”; tab “Description” |

#### Botão dark on-brand

| Propriedade | Valor |
| --- | --- |
| Background | `#0A0B0A` / `#151716` |
| Texto | `#FCFDFC` |
| Radius | `radius.full` |
| Exemplo | “More details” no featured card |

#### Badge de status (“Mandatory”)

| Variante | Background | Texto | Radius | Padding |
| --- | --- | --- | --- | --- |
| Brand | `#DCCFFF` | `#151716` | `radius.full` | `4px 10px` |
| Inverse (sobre brand) | `#FCFDFC` | `#151716` | `radius.full` | `4px 10px` |
| Meta (info) | `color.surface.raised` + border subtle | `color.text.primary` | `radius.full` | `6px 12px` |

Badges meta observados: “5 lessons”, “30 min”, “Due date: Feb 15” — ícone outline + label `text.caption` / weight `500`.

---

### 4.3 Navegação

#### Top nav (links)

| Estado | Cor do texto | Weight |
| --- | --- | --- |
| Ativa | `#FCFDFC` | `600` |
| Inativa | `#9A9C9B` | `400` |
| Hover (deduzido) | `#FCFDFC` @ 80% | `500` |

- Sem underline; sem background nos links de texto.
- Logo: ícone brand (`#DCCFFF`) + wordmark Poppins Bold em `#FCFDFC`.
- Utilities à direita: ícones outline (search, bell) + avatar circular + nome (`caption` / `600`) + role (`small` / `400` / secondary).

#### Tabs / filters (segmento de conteúdo)

| Estado | Background | Texto | Border |
| --- | --- | --- | --- |
| Ativa | `#FCFDFC` (pill branco) | `#151716` | none |
| Inativa | transparente / surface | `#FCFDFC` | `1px solid #3A3C3B` |

Radius: `radius.full`. Gap entre pills: `8px`–`12px`.

#### Date picker (schedule)

| Estado | Forma | Background | Texto |
| --- | --- | --- | --- |
| Ativa | círculo (`radius.full`) | `#DCCFFF` | `#151716` |
| Inativa | texto livre | transparente | secondary / primary |

---

### 4.4 Data Display

#### Progress bar (horizontal)

| Parte | Token / valor |
| --- | --- |
| Track height | `4px`–`6px` |
| Track color | `#2C2E2D` ou `rgba(220, 207, 255, 0.2)` |
| Fill color | `#DCCFFF` (ou `#FCFDFC` em alto contraste) |
| Track radius | `radius.full` |
| Fill radius | `radius.full` |

Usado nos cards de curso para percentual concluído.

#### Bar chart (vertical — “My Progress”)

| Parte | Token / valor |
| --- | --- |
| Container | card `surface.default`, padding `24px` |
| Bar width | ~`12px`–`16px` |
| Bar radius | topo arredondado (`radius.sm` / `8px`) |
| Bar ativa | `#FCFDFC` (com tooltip) |
| Barras inativas | `color.brand.muted` (`rgba(220, 207, 255, 0.35)`) |
| Tooltip | fundo `#FCFDFC`, texto `#151716`, radius `radius.sm`, label `text.small` |
| Axis labels | `text.caption` / `color.text.secondary` |

#### Lista de schedule

- Item: ícone circular outline + título (`body` / `500`–`600`) + horário alinhado à direita (`caption` / secondary).
- Gap vertical entre itens: `12px`–`16px`.
- Separadores: ausentes (apenas spacing).

#### Player / media

- Container: `radius.xl`, fundo `surface.sunken`.
- Play control: círculo `radius.full`, fill `#DCCFFF`, ícone `#151716`.

---

### 4.5 Ícones

| Propriedade | Valor |
| --- | --- |
| Estilo | Outline / stroke fino |
| Stroke | ~`1.5px`–`2px` |
| Tamanho padrão | `16px` / `20px` |
| Cor default | `color.text.primary` ou `color.text.secondary` |
| Cor on-brand | `color.text.on-brand` |

Ícones recorrentes: search, bell, calendar, clock, book, share, chevron, arrow-up-right, play.

---

### 4.6 Resumo rápido de tokens CSS (referência)

```css
:root {
  /* Color */
  --color-brand-primary: #DCCFFF;
  --color-bg-default: #151716;
  --color-surface-default: #1C1E1D;
  --color-surface-raised: #2C2E2D;
  --color-surface-sunken: #101211;
  --color-border-subtle: #2A2C2B;
  --color-border-default: #3A3C3B;
  --color-text-primary: #FCFDFC;
  --color-text-secondary: #9A9C9B;
  --color-text-muted: #6E706F;
  --color-text-on-brand: #151716;
  --color-interactive-dark: #0A0B0A;
  --color-brand-muted: rgba(220, 207, 255, 0.35);
  --color-feedback-positive: #A8E6C3;

  /* Typography */
  --font-sans: "Poppins", system-ui, sans-serif;
  --text-display: 2.25rem;
  --text-h1: 1.75rem;
  --text-h2: 1.375rem;
  --text-h3: 1.125rem;
  --text-body: 0.875rem;
  --text-caption: 0.75rem;
  --text-small: 0.6875rem;

  /* Shape */
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --radius-xl: 24px;
  --radius-2xl: 32px;
  --radius-full: 9999px;

  /* Spacing */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --space-6: 24px;
  --space-8: 32px;
}
```
