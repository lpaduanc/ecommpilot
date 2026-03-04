/**
 * Composable para integração com WhatsApp Web
 *
 * Detecta se um número de telefone é celular brasileiro e gera
 * o link wa.me correspondente para abertura no WhatsApp Web.
 *
 * @example
 * ```vue
 * <script setup>
 * import { useWhatsApp } from '@/composables/useWhatsApp';
 *
 * const { isBrazilianMobile, getWhatsAppLink, getWhatsAppNumber } = useWhatsApp();
 * </script>
 * ```
 */

export function useWhatsApp() {
    /**
     * Limpa o número removendo todos os caracteres não numéricos.
     */
    function cleanPhone(phone: string): string {
        return phone.replace(/\D/g, '');
    }

    /**
     * Normaliza o número para o formato internacional brasileiro (55DDNNNNNNNNN).
     * Se não começar com 55, adiciona o prefixo.
     */
    function normalizeToE164Brazil(phone: string): string {
        const digits = cleanPhone(phone);
        return digits.startsWith('55') ? digits : `55${digits}`;
    }

    /**
     * Detecta se o número é um celular brasileiro.
     *
     * Critérios:
     * - Após o código de país 55, DDD tem 2 dígitos
     * - O número local tem 9 dígitos e começa com 9 (celular)
     * - Total com código de país: 13 dígitos (55 + DDD 2d + 9 + 8d)
     *
     * Fixo: DDD 2d + 8 dígitos começando com 2-8 → total 12 dígitos com 55
     */
    function isBrazilianMobile(phone: string | null | undefined): boolean {
        if (!phone) return false;
        const normalized = normalizeToE164Brazil(phone);
        // Deve ter exatamente 13 dígitos: 55 (2) + DDD (2) + 9xxxxx (9)
        if (normalized.length !== 13) return false;
        // O 5º dígito (índice 4) é o primeiro dígito do número local após o DDD
        // Celulares brasileiros começam com 9
        return normalized[4] === '9';
    }

    /**
     * Retorna o link wa.me formatado para o número.
     * Retorna null se o número não for um celular brasileiro válido.
     */
    function getWhatsAppLink(phone: string | null | undefined): string | null {
        if (!isBrazilianMobile(phone)) return null;
        const number = normalizeToE164Brazil(phone!);
        return `https://wa.me/${number}`;
    }

    /**
     * Retorna o número normalizado para exibição no link wa.me.
     * Mesmo comportamento do getWhatsAppLink, mas retorna apenas o número.
     */
    function getWhatsAppNumber(phone: string | null | undefined): string | null {
        if (!isBrazilianMobile(phone)) return null;
        return normalizeToE164Brazil(phone!);
    }

    return {
        isBrazilianMobile,
        getWhatsAppLink,
        getWhatsAppNumber,
    };
}
