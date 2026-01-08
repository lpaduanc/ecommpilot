/**
 * Utilitários para sanitização de HTML
 *
 * Fornece funções para sanitizar e remover HTML de strings,
 * protegendo contra ataques XSS (Cross-Site Scripting)
 *
 * @module sanitize
 */

import DOMPurify from 'dompurify';

/**
 * Configuração padrão para sanitização HTML
 * Define quais tags e atributos são permitidos
 */
const DEFAULT_CONFIG: DOMPurify.Config = {
  ALLOWED_TAGS: [
    'b',
    'i',
    'em',
    'strong',
    'a',
    'p',
    'br',
    'ul',
    'ol',
    'li',
    'code',
    'pre',
    'blockquote',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
  ],
  ALLOWED_ATTR: ['href', 'target', 'rel', 'class'],
  ALLOW_DATA_ATTR: false,
};

/**
 * Sanitiza uma string HTML, removendo scripts e elementos maliciosos
 *
 * Remove elementos e atributos potencialmente perigosos enquanto
 * mantém formatação básica permitida (negrito, itálico, links, etc.)
 *
 * @param dirty - String HTML potencialmente insegura
 * @param config - Configuração customizada do DOMPurify (opcional)
 * @returns String HTML sanitizada e segura para renderização
 *
 * @example
 * ```typescript
 * // Remove scripts e mantém formatação
 * sanitizeHtml('<p>Texto <b>negrito</b></p><script>alert("XSS")</script>')
 * // Retorna: '<p>Texto <b>negrito</b></p>'
 *
 * // Remove event handlers
 * sanitizeHtml('<a href="#" onclick="alert(1)">Link</a>')
 * // Retorna: '<a href="#">Link</a>'
 *
 * // Permite apenas tags específicas
 * sanitizeHtml('<p>Texto <b>negrito</b></p>', {
 *   ALLOWED_TAGS: ['p']
 * })
 * // Retorna: '<p>Texto negrito</p>'
 * ```
 */
export function sanitizeHtml(
  dirty: string,
  config: DOMPurify.Config = DEFAULT_CONFIG
): string {
  return DOMPurify.sanitize(dirty, config);
}

/**
 * Remove todas as tags HTML de uma string, retornando apenas texto puro
 *
 * Útil para exibir conteúdo em contextos onde HTML não é permitido
 * (ex: meta tags, notifications, plain text emails)
 *
 * @param dirty - String HTML a ser convertida para texto puro
 * @returns String sem nenhuma tag HTML
 *
 * @example
 * ```typescript
 * stripHtml('<p>Texto <b>negrito</b></p>')
 * // Retorna: 'Texto negrito'
 *
 * stripHtml('<div>Parágrafo 1</div><div>Parágrafo 2</div>')
 * // Retorna: 'Parágrafo 1Parágrafo 2'
 *
 * stripHtml('<script>alert("XSS")</script>Conteúdo')
 * // Retorna: 'Conteúdo'
 * ```
 */
export function stripHtml(dirty: string): string {
  return DOMPurify.sanitize(dirty, { ALLOWED_TAGS: [] });
}

/**
 * Sanitiza HTML permitindo apenas formatação de texto básica
 *
 * Configuração mais restritiva, ideal para comentários e campos de texto
 * que suportam formatação simples
 *
 * @param dirty - String HTML a ser sanitizada
 * @returns String HTML com apenas formatação básica
 *
 * @example
 * ```typescript
 * sanitizeBasicFormatting('<p>Texto <b>negrito</b> <a href="#">link</a></p>')
 * // Retorna: '<p>Texto <b>negrito</b> <a href="#">link</a></p>'
 *
 * sanitizeBasicFormatting('<p>Texto <iframe src="evil.com"></iframe></p>')
 * // Retorna: '<p>Texto </p>'
 * ```
 */
export function sanitizeBasicFormatting(dirty: string): string {
  return DOMPurify.sanitize(dirty, {
    ALLOWED_TAGS: ['b', 'i', 'em', 'strong', 'a', 'br'],
    ALLOWED_ATTR: ['href'],
  });
}

/**
 * Sanitiza URLs removendo protocolos perigosos
 *
 * Permite apenas http, https e mailto. Remove javascript:, data:, etc.
 *
 * @param url - URL a ser sanitizada
 * @returns URL segura ou string vazia se inválida
 *
 * @example
 * ```typescript
 * sanitizeUrl('https://exemplo.com') // 'https://exemplo.com'
 * sanitizeUrl('javascript:alert(1)') // ''
 * sanitizeUrl('data:text/html,<script>alert(1)</script>') // ''
 * ```
 */
export function sanitizeUrl(url: string): string {
  const allowedProtocols = ['http:', 'https:', 'mailto:'];

  try {
    const urlObject = new URL(url);

    if (allowedProtocols.includes(urlObject.protocol)) {
      return url;
    }
  } catch {
    // URL inválida
    return '';
  }

  return '';
}
