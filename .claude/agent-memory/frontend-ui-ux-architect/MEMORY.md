# Frontend UI/UX Architect Memory

## Component Patterns

### Timeline Component for Action Steps
- Created `ActionTimeline.vue` component for visual representation of strategic action steps
- Location: `resources/js/components/analysis/ActionTimeline.vue`
- Parse structured format from AI recommendations with regex pattern matching
- Timeline design: vertical line with numbered circles, gradient styling, dark mode support
- Four sub-sections per step: O QUE (what), COMO (how), RESULTADO (result), RECURSOS (resources)
- Each section has distinct icon and color coding:
  - O QUE: Blue cube icon
  - COMO: Purple wrench icon
  - RESULTADO: Green chart icon
  - RECURSOS: Amber dollar icon
- Responsive grid layout with proper spacing
- Fallback display for unparsed formats

### Action Steps Format (from AI)
```
**PASSO 1: [Title] (Dias 1-7)**
• O QUE: [description]
• COMO: [implementation details]
• RESULTADO: [expected metrics]
• RECURSOS: [tools + costs]

**PASSO 2: [Title] (Dias 8-14)**
[repeat format]
```

## Integration Points
- `SuggestionDetailModal.vue` uses ActionTimeline for displaying `recommended_action` field
- Replaced old `actionSteps` computed with direct pass-through to ActionTimeline component
- Data comes from `Suggestion.recommended_action` (can be string or array, cast in Laravel model)

## TypeScript Types Updated
- Enhanced `Suggestion` interface in `types/analysis.ts` with:
  - `recommended_action?: string | string[]`
  - `expected_impact?: string`
  - `category?: string`
  - Additional boolean flags for page context

## Color Tokens & Dark Mode
- All timeline elements have `dark:` variants
- Gradient from primary-500 to secondary-500 for step numbers
- Background transitions: white/gray-800 for cards
- Border colors: gray-200/gray-700
- Text colors: gray-900/gray-100 for headings, gray-700/gray-300 for content

## Responsive Behavior
- Timeline line is 0.5px width, absolute positioned at left: 1.5rem
- Step circles are 12x12 (3rem) with responsive content padding
- Content uses ml-20 (5rem) to clear timeline column
- Grid layout for sub-items with flex-start alignment for icon columns

## "Em breve" / Disabled Pattern (IntegrationsView + AnalysisTypeSelector)

When a feature is not yet available, apply this consistent disabled visual pattern:

**Card/Button disabled classes:**
- `opacity-60` — reduces overall opacity
- `cursor-not-allowed` — communicates non-interactivity
- Remove hover effects (`hover:shadow-xl`, `hover:-translate-y-1`, `hover:bg-*`)
- Keep border and background colors but use muted text color (`text-gray-400 dark:text-gray-500`)

**"Em breve" badge:**
```html
<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 ml-1">
    Em breve
</span>
```
- Larger variant (for full cards): `px-3 py-1.5` instead of `px-1.5 py-0.5`

**Click guard in script:**
```js
function selectType(type) {
    if (!type.available) return;
    emit('update:modelValue', type.key);
}
```

**HTML button attribute:** `:disabled="!type.available"` blocks native interaction

**Applied in:** `AnalysisTypeSelector.vue` (analysis types) and `IntegrationsView.vue` (platforms)

## Hero Header Pattern (ProductsView / OrdersView / CustomersView)

Standard gradient hero used across all data-listing views:
```html
<div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-primary-950 to-secondary-950 dark:from-gray-950 dark:via-gray-900 dark:to-gray-950 px-4 sm:px-6 lg:px-8 py-6 sm:py-8 lg:py-12 -mx-4 sm:-mx-6 lg:-mx-8 -mt-4 sm:-mt-6 lg:-mt-8">
```
- Three blur orbs: top-right (primary), bottom-left (secondary), center (accent)
- Radial dot grid pattern via inline style
- Icon badge: `rounded-xl sm:rounded-2xl bg-gradient-to-br from-primary-400 to-secondary-500 shadow-lg shadow-primary-500/30`
- Search input: `bg-white/10 backdrop-blur-sm border border-white/20 text-white placeholder-white/60`

## Table/Card Dual Layout Pattern

All data-listing views use `xl:hidden` for mobile cards and `hidden xl:block` for desktop table.

**Desktop table header:**
```html
<thead class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
```
**Row hover:**
```html
class="hover:bg-gradient-to-r hover:from-primary-50/50 dark:hover:from-primary-900/30 hover:to-transparent transition-all duration-200"
```
**Table body divider:** `divide-y divide-gray-100 dark:divide-gray-700`

## Accordion Pattern (CustomersView RFM Section)

Accordion with smooth height transition using `v-show` + CSS class transitions:
- Header: `button` with `@click="toggle"`, `aria-expanded`, `aria-controls`
- Chevron rotates 180 on open: `:class="{ 'rotate-180': isOpen }"`
- Content uses `v-show` with `Transition` for enter/leave animations
- Default state: **closed** (`ref(false)`)
- Use `max-h-0` → `max-h-[800px]` approach for height animation

## Pagination Pattern

Standard pagination across all list views:
```html
<div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 sm:px-6 py-4 border-t border-gray-100 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50">
```
- Shows: `(currentPage - 1) * perPage + 1` to `Math.min(currentPage * perPage, totalItems)` of `totalItems`
- Per-page selector with `[10, 20, 50, 100]` options
- BaseButton prev/next with ChevronLeftIcon / ChevronRightIcon

## Filter Select Styling (consistent across all views)

Custom select with chevron arrow via inline SVG background:
```
style="background-image: url('data:image/svg+xml,...'); background-position: right 0.5rem center; background-size: 1.5em 1.5em; background-repeat: no-repeat;"
class="appearance-none px-4 py-2.5 pr-10 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500"
```

## Filters Card Pattern (OrdersView / CustomersView)

```html
<BaseCard class="mb-6">
  <div class="flex flex-col sm:flex-row items-start gap-3 sm:gap-4">
    <!-- Icon badge w-10/w-12 gradient -->
    <div class="flex-1 w-full min-w-0">
      <!-- Header row with title + clear filters button -->
      <div class="flex sm:flex-row sm:items-center justify-between gap-2 mb-4">
        <!-- grid of filter inputs -->
      </div>
    </div>
  </div>
</BaseCard>
```

## RFM Segment Colors (CustomersView) — UPDATED

**Single source of truth pattern:** `RFM_COLOR_MAP` object in CustomersView.vue maps each
segment name → hex color. Both badge tags AND chart series consume this map via:
- `getSegmentBadgeStyle(segment)` → returns inline `{ backgroundColor, color, borderColor }` using `hexToRgba()` + `lightenHex()`/`darkenHex()` for dark/light mode
- `resolveChartColors(labels)` → maps API-returned labels to hex colors (order-safe)

Badge tags use `class="... border" :style="getSegmentBadgeStyle(...)"` (NO Tailwind color classes).
The `<script setup>` tag has NO `lang="ts"` — do NOT use TypeScript annotations in this file.

**Gradient cold→hot by segment quality:**
- Campeões: `#059669` (emerald-600)
- Clientes Fiéis: `#16a34a` (green-600)
- Potenciais Fiéis: `#2563eb` (blue-600)
- Novos Clientes: `#0891b2` (cyan-600)
- Promissores: `#7c3aed` (violet-600)
- Precisam de Atenção: `#d97706` (amber-600)
- Quase Dormindo: `#ea580c` (orange-600)
- Em Risco: `#dc2626` (red-600)
- Não Pode Perder: `#991b1b` (red-800)
- Hibernando: `#9f1239` (rose-800)
- Perdidos: `#6b21a8` (purple-800)

## WhatsApp Web Integration Pattern (OrdersView / CustomersView)

Composable `useWhatsApp` at `resources/js/composables/useWhatsApp.ts`:
- `isBrazilianMobile(phone)` — 13 digits + digit[4] === '9'
- `getWhatsAppLink(phone)` — `https://wa.me/55XXXXXXXXXXX` or null
- Exported in `composables/index.ts`
- Mobile card uses `w-3.5 h-3.5` WhatsApp SVG icon; desktop table uses `w-4 h-4`

## InfoTooltip Component

**File:** `resources/js/components/common/InfoTooltip.vue`
- `InformationCircleIcon` from `@heroicons/vue/20/solid` at w-4 h-4
- Props: `text` (required), `position` (top/bottom/left/right, default top), `maxWidth`, `iconClass`
- Default icon: `text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400`
- On colored backgrounds: `icon-class="text-white/60 hover:text-white"`
- Tooltip panel: `bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-lg px-3 py-2 shadow-lg`
- Toggle on click.stop (mobile), show/hide on hover (desktop), show/hide on focus/blur (keyboard)
- Click-outside dismiss via document listener registered in onMounted/onUnmounted
- Accessibility: `role="tooltip"`, `aria-describedby`, unique ID, sr-only "Mais informações" text
- 13 tooltips placed across Analysis page — see `components.md` for full placement table
- @click.stop prevents row click events from firing when clicking the WhatsApp link
- fill="#25D366" is the official WhatsApp green — use it directly, not via Tailwind

## LazyChart Usage

```html
<LazyChart
    type="bar"           <!-- or "pie", "line", etc. -->
    :height="320"
    :options="chartOptions"   <!-- computed ref with ApexCharts options -->
    :series="chartSeries"     <!-- computed ref -->
    :loading="isLoadingData"
    loadingMessage="..."
    emptyMessage="..."
/>
```
- For horizontal bar: set `plotOptions.bar.horizontal: true` in options
- Always pass `chart.background: 'transparent'` and `chart.fontFamily: 'inherit'`
- Use `isDarkMode()` helper for theme-aware colors: `document.documentElement.classList.contains('dark')`
