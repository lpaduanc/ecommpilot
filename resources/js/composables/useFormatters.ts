/**
 * Composable para formatação de valores
 *
 * Fornece funções reutilizáveis para formatação de moeda, datas e porcentagens
 * usando o padrão brasileiro (pt-BR)
 *
 * @example
 * ```vue
 * <script setup>
 * import { useFormatters } from '@/composables/useFormatters';
 *
 * const { formatCurrency, formatDate, formatPercentage } = useFormatters();
 *
 * const price = 1234.56;
 * console.log(formatCurrency(price)); // "R$ 1.234,56"
 * </script>
 * ```
 */

export function useFormatters() {
  /**
   * Formata um número como moeda brasileira (BRL)
   *
   * @param value - Valor numérico a ser formatado
   * @returns String formatada no padrão "R$ 1.234,56"
   *
   * @example
   * formatCurrency(1000) // "R$ 1.000,00"
   * formatCurrency(0) // "R$ 0,00"
   * formatCurrency(1234.56) // "R$ 1.234,56"
   */
  const formatCurrency = (value: number): string => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(value);
  };

  /**
   * Formata uma data no padrão brasileiro (dd/mm/yyyy)
   *
   * @param date - String de data ou objeto Date
   * @returns String formatada no padrão "01/01/2024"
   *
   * @example
   * formatDate('2024-01-15') // "15/01/2024"
   * formatDate(new Date()) // "06/01/2026"
   */
  const formatDate = (date: string | Date): string => {
    return new Intl.DateTimeFormat('pt-BR').format(new Date(date));
  };

  /**
   * Formata um número como porcentagem com 2 casas decimais
   *
   * @param value - Valor numérico a ser formatado
   * @returns String formatada no padrão "25.50%"
   *
   * @example
   * formatPercentage(25.5) // "25.50%"
   * formatPercentage(100) // "100.00%"
   * formatPercentage(0.5) // "0.50%"
   */
  const formatPercentage = (value: number): string => {
    return `${value.toFixed(2)}%`;
  };

  return {
    formatCurrency,
    formatDate,
    formatPercentage,
  };
}
