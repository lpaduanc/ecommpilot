# Component Patterns — Detailed Reference

## InfoTooltip Component

**File:** `resources/js/components/common/InfoTooltip.vue`

### Architecture
- `InformationCircleIcon` from `@heroicons/vue/20/solid` (20/solid, NOT 24/outline) at `w-4 h-4`
- Desktop: show/hide on mouseenter/mouseleave
- Mobile: toggle on click.stop, dismiss via click-outside listener (onMounted/onUnmounted)
- Keyboard: show on focus, hide on blur
- Unique ID per instance: `tooltip-${Math.random().toString(36).slice(2, 9)}`

### Props
```js
text: String (required)
position: 'top' | 'bottom' | 'left' | 'right' (default: 'top')
maxWidth: String (default: 'max-w-xs')
iconClass: String (override default gray icon colors)
```

### Default icon style (no iconClass provided)
`text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-400`

### Override for colored/dark backgrounds
`icon-class="text-white/60 hover:text-white"` — used on gradient headers

### Tooltip panel styles
`bg-gray-900 dark:bg-gray-700 text-white text-xs rounded-lg px-3 py-2 shadow-lg`

### Transition
Fade 200ms using Vue `<Transition>` with opacity classes

### Arrow implementation
`<span class="absolute w-0 h-0 border-4" :class="arrowClasses[position]">` — CSS border trick
Arrow position classes are defined as a static object (not computed), keyed by position name.

### Accessibility
- `role="tooltip"` on panel div
- `aria-describedby` on button pointing to panel ID
- `tabindex="0"` (via button element which is naturally focusable)
- `class="sr-only"` span: "Mais informações"

### Usage in Analysis page (13 tooltips placed)
| Component | Section | position | iconClass |
|-----------|---------|----------|-----------|
| HealthScore.vue | "Saúde da Loja" h3 | bottom | (default) |
| AnalysisAlerts.vue | "Alertas" header row | bottom | (default) |
| StrategicSummaryPanel.vue | "Resumo Estratégico" header | bottom | white/60 |
| StrategicSummaryPanel.vue | Growth Score bars | right | (default) |
| StrategicSummaryPanel.vue | 3-card grid (gargalo) | right | (default) |
| StrategicSummaryPanel.vue | "Plano de Ação 90 Dias" | right | (default) |
| StrategicSummaryPanel.vue | "Impacto Financeiro" | right | (default) |
| StrategicSummaryPanel.vue | "Cenários de Crescimento" | right | (default) |
| OpportunitiesPanel.vue | "Oportunidades" header | bottom | white/60 |
| AnalysisView.vue | "Sugestões Estratégicas" header | bottom | white/60 |
| AnalysisView.vue | "Alta Prioridade" | right | (default) |
| AnalysisView.vue | "Média Prioridade" | right | (default) |
| AnalysisView.vue | "Baixa Prioridade" | right | (default) |

### Pattern: adding tooltip to flex headers
When h3/h4 is already inside a flex container, just add `<InfoTooltip ... />` after the text element.
When h3/h4 is NOT in a flex container, wrap in `<div class="flex items-center gap-2">`.

### Pattern: AnalysisAlerts header row
AnalysisAlerts had no visible title. Added a small header row above the transition-group:
```html
<div class="flex items-center gap-2">
    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Alertas</span>
    <InfoTooltip text="..." position="bottom" />
</div>
```

### Pattern: standalone tooltip (no adjacent text)
For the 3-card grid in StrategicSummaryPanel, tooltip added as standalone in its own flex div:
```html
<div class="flex items-center gap-2 mt-6 mb-0">
    <InfoTooltip text="..." position="right" />
</div>
<div class="grid ...">
```
