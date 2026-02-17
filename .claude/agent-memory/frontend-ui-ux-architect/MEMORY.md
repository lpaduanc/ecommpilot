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
