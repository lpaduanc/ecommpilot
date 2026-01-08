/**
 * Composable para validação de dados
 *
 * Fornece funções reutilizáveis para validação de email, CPF e outros dados comuns
 *
 * @example
 * ```vue
 * <script setup>
 * import { useValidation } from '@/composables/useValidation';
 *
 * const { isValidEmail, isValidCPF } = useValidation();
 *
 * console.log(isValidEmail('teste@exemplo.com')); // true
 * console.log(isValidCPF('123.456.789-10')); // true (se válido)
 * </script>
 * ```
 */

export function useValidation() {
  /**
   * Valida se uma string é um email válido
   *
   * @param email - String de email a ser validada
   * @returns true se o email é válido, false caso contrário
   *
   * @example
   * isValidEmail('teste@exemplo.com') // true
   * isValidEmail('teste@exemplo') // false
   * isValidEmail('teste.exemplo.com') // false
   */
  const isValidEmail = (email: string): boolean => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  };

  /**
   * Valida se uma string é um CPF válido (formato e dígitos verificadores)
   *
   * Aceita CPF com ou sem formatação (pontos e hífen)
   * Valida tanto o formato quanto os dígitos verificadores
   *
   * @param cpf - String de CPF a ser validada
   * @returns true se o CPF é válido, false caso contrário
   *
   * @example
   * isValidCPF('123.456.789-10') // Valida formato e dígitos
   * isValidCPF('12345678910') // Aceita sem formatação
   * isValidCPF('111.111.111-11') // false (CPF inválido)
   */
  const isValidCPF = (cpf: string): boolean => {
    // Remove caracteres não numéricos
    const cleanCPF = cpf.replace(/[^\d]/g, '');

    // Verifica se tem 11 dígitos
    if (cleanCPF.length !== 11) {
      return false;
    }

    // Verifica se todos os dígitos são iguais (ex: 111.111.111-11)
    if (/^(\d)\1+$/.test(cleanCPF)) {
      return false;
    }

    // Validação dos dígitos verificadores
    let sum = 0;
    let remainder;

    // Valida primeiro dígito verificador
    for (let i = 1; i <= 9; i++) {
      sum += parseInt(cleanCPF.substring(i - 1, i)) * (11 - i);
    }

    remainder = (sum * 10) % 11;
    if (remainder === 10 || remainder === 11) {
      remainder = 0;
    }

    if (remainder !== parseInt(cleanCPF.substring(9, 10))) {
      return false;
    }

    // Valida segundo dígito verificador
    sum = 0;
    for (let i = 1; i <= 10; i++) {
      sum += parseInt(cleanCPF.substring(i - 1, i)) * (12 - i);
    }

    remainder = (sum * 10) % 11;
    if (remainder === 10 || remainder === 11) {
      remainder = 0;
    }

    if (remainder !== parseInt(cleanCPF.substring(10, 11))) {
      return false;
    }

    return true;
  };

  return {
    isValidEmail,
    isValidCPF,
  };
}
