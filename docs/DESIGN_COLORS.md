# Design System - Cores do Ecommpilot

Documentação das cores utilizadas no aplicativo, definidas em `tailwind.config.js` e `resources/css/app.css`.

## Paleta de Cores

### Primary (Azul)

Cor principal do sistema, usada em botões, links e elementos de destaque.

| Token | Hex | Uso |
|-------|-----|-----|
| 50 | `#f0f7ff` | Fundos muito claros |
| 100 | `#e0efff` | Fundos claros |
| 200 | `#baddff` | Bordas claras |
| 300 | `#7cc2ff` | Destaques leves |
| 400 | `#36a3ff` | Elementos hover |
| **500** | `#0c87f7` | **Cor principal** |
| 600 | `#006ad4` | Botões, links ativos |
| 700 | `#0054ab` | Hover em botões |
| 800 | `#00478d` | Estados pressed |
| 900 | `#063d74` | Texto escuro |
| 950 | `#04264d` | Muito escuro |

### Secondary (Roxo)

Cor secundária para elementos de destaque alternativo.

| Token | Hex |
|-------|-----|
| 50 | `#f5f3ff` |
| 100 | `#ede8ff` |
| 200 | `#ddd5ff` |
| 300 | `#c4b3ff` |
| 400 | `#a688ff` |
| **500** | `#8b57ff` |
| 600 | `#7c34f7` |
| 700 | `#6d22e3` |
| 800 | `#5b1cbf` |
| 900 | `#4c199c` |
| 950 | `#2f0d6a` |

### Accent / Warning (Amarelo/Laranja)

Usada para alertas, avisos e elementos de atenção.

| Token | Hex |
|-------|-----|
| 50 | `#fffbeb` |
| 100 | `#fef3c7` |
| 200 | `#fde68a` |
| 300 | `#fcd34d` |
| 400 | `#fbbf24` |
| **500** | `#f59e0b` |
| 600 | `#d97706` |
| 700 | `#b45309` |
| 800 | `#92400e` |
| 900 | `#78350f` |
| 950 | `#451a03` |

### Success (Verde)

Usada para estados de sucesso, confirmações e indicadores positivos.

| Token | Hex |
|-------|-----|
| 50 | `#ecfdf5` |
| 100 | `#d1fae5` |
| 200 | `#a7f3d0` |
| 300 | `#6ee7b7` |
| 400 | `#34d399` |
| **500** | `#10b981` |
| 600 | `#059669` |
| 700 | `#047857` |
| 800 | `#065f46` |
| 900 | `#064e3b` |
| 950 | `#022c22` |

### Danger (Vermelho)

Usada para erros, ações destrutivas e alertas críticos.

| Token | Hex |
|-------|-----|
| 50 | `#fef2f2` |
| 100 | `#fee2e2` |
| 200 | `#fecaca` |
| 300 | `#fca5a5` |
| 400 | `#f87171` |
| **500** | `#ef4444` |
| 600 | `#dc2626` |
| 700 | `#b91c1c` |
| 800 | `#991b1b` |
| 900 | `#7f1d1d` |
| 950 | `#450a0a` |

## Tipografia

| Família | Fonte | Uso |
|---------|-------|-----|
| Sans | DM Sans | Texto geral |
| Display | Sora | Títulos e headings |
| Mono | JetBrains Mono | Código |

## Uso no Código

### Classes Tailwind

```html
<!-- Backgrounds -->
<div class="bg-primary-500">...</div>
<div class="bg-success-100">...</div>

<!-- Texto -->
<p class="text-primary-600">...</p>
<p class="text-danger-500">...</p>

<!-- Bordas -->
<div class="border border-primary-200">...</div>

<!-- Dark Mode -->
<div class="bg-gray-50 dark:bg-gray-900">...</div>
<p class="text-gray-900 dark:text-gray-100">...</p>
```

### CSS Variables

```css
/* Acesso direto às variáveis */
.custom-element {
    background-color: var(--color-primary-500);
    color: var(--color-danger-600);
    border-color: var(--color-success-400);
}
```

### Componentes Pré-definidos

```html
<!-- Botões -->
<button class="btn btn-primary">Primary</button>
<button class="btn btn-secondary">Secondary</button>
<button class="btn btn-danger">Danger</button>
<button class="btn btn-success">Success</button>
<button class="btn btn-ghost">Ghost</button>

<!-- Badges -->
<span class="badge badge-primary">Primary</span>
<span class="badge badge-success">Success</span>
<span class="badge badge-warning">Warning</span>
<span class="badge badge-danger">Danger</span>
<span class="badge badge-secondary">Secondary</span>

<!-- Cards -->
<div class="card">...</div>
<div class="card card-hover">...</div>
<div class="stat-card">...</div>

<!-- Inputs -->
<input class="input" />
<input class="input input-error" />
```

## Dark Mode

O sistema usa `darkMode: 'class'` no Tailwind. Para ativar o dark mode, adicione a classe `dark` no elemento `<html>`.

### Cores de Fundo (Dark Mode)

| Elemento | Light | Dark |
|----------|-------|------|
| Body | `#ffffff` | `#111827` |
| Card | `#ffffff` | `#1f2937` |
| Input | `#ffffff` | `#374151` |
| Dropdown | `#ffffff` | `#1f2937` |

### Cores de Texto (Dark Mode)

| Elemento | Light | Dark |
|----------|-------|------|
| Primary | `#111827` | `#f3f4f6` |
| Secondary | `#374151` | `#d1d5db` |
| Muted | `#6b7280` | `#9ca3af` |
