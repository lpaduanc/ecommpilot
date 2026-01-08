/**
 * Testes unitários para utilitários de sanitização
 */

import { describe, it, expect } from 'vitest';
import {
  sanitizeHtml,
  stripHtml,
  sanitizeBasicFormatting,
  sanitizeUrl,
} from '../sanitize';

describe('sanitize utils', () => {
  describe('sanitizeHtml', () => {
    it('deve permitir tags HTML seguras', () => {
      const input = '<p>Texto <b>negrito</b> e <i>itálico</i></p>';
      const result = sanitizeHtml(input);

      expect(result).toContain('<p>');
      expect(result).toContain('<b>');
      expect(result).toContain('<i>');
    });

    it('deve remover scripts maliciosos', () => {
      const input = '<p>Texto seguro</p><script>alert("XSS")</script>';
      const result = sanitizeHtml(input);

      expect(result).toContain('<p>Texto seguro</p>');
      expect(result).not.toContain('<script>');
      expect(result).not.toContain('alert');
    });

    it('deve remover event handlers', () => {
      const input = '<a href="#" onclick="alert(1)">Link</a>';
      const result = sanitizeHtml(input);

      expect(result).toContain('<a href="#">Link</a>');
      expect(result).not.toContain('onclick');
    });

    it('deve remover iframes', () => {
      const input = '<p>Texto</p><iframe src="evil.com"></iframe>';
      const result = sanitizeHtml(input);

      expect(result).toContain('Texto');
      expect(result).not.toContain('<iframe');
    });

    it('deve permitir links com atributos seguros', () => {
      const input = '<a href="https://exemplo.com" target="_blank" rel="noopener">Link</a>';
      const result = sanitizeHtml(input);

      expect(result).toContain('href');
      expect(result).toContain('target');
      expect(result).toContain('rel');
    });

    it('deve remover javascript: URLs', () => {
      const input = '<a href="javascript:alert(1)">Link malicioso</a>';
      const result = sanitizeHtml(input);

      expect(result).not.toContain('javascript:');
    });

    it('deve preservar texto sem tags', () => {
      const input = 'Apenas texto simples';
      const result = sanitizeHtml(input);

      expect(result).toBe(input);
    });

    it('deve lidar com HTML vazio', () => {
      expect(sanitizeHtml('')).toBe('');
      expect(sanitizeHtml('   ')).toBe('   ');
    });

    it('deve permitir listas', () => {
      const input = '<ul><li>Item 1</li><li>Item 2</li></ul>';
      const result = sanitizeHtml(input);

      expect(result).toContain('<ul>');
      expect(result).toContain('<li>');
    });

    it('deve permitir headings', () => {
      const input = '<h1>Título</h1><h2>Subtítulo</h2>';
      const result = sanitizeHtml(input);

      expect(result).toContain('<h1>');
      expect(result).toContain('<h2>');
    });

    it('deve permitir code e pre', () => {
      const input = '<pre><code>const x = 1;</code></pre>';
      const result = sanitizeHtml(input);

      expect(result).toContain('<pre>');
      expect(result).toContain('<code>');
    });
  });

  describe('stripHtml', () => {
    it('deve remover todas as tags HTML', () => {
      const input = '<p>Texto <b>negrito</b></p>';
      const result = stripHtml(input);

      expect(result).toBe('Texto negrito');
      expect(result).not.toContain('<');
      expect(result).not.toContain('>');
    });

    it('deve remover scripts e retornar apenas texto', () => {
      const input = '<p>Conteúdo</p><script>alert(1)</script>';
      const result = stripHtml(input);

      expect(result).toBe('Conteúdo');
      expect(result).not.toContain('alert');
    });

    it('deve preservar texto sem tags', () => {
      const input = 'Apenas texto';
      const result = stripHtml(input);

      expect(result).toBe(input);
    });

    it('deve lidar com múltiplas tags aninhadas', () => {
      const input = '<div><p><strong><em>Texto</em></strong></p></div>';
      const result = stripHtml(input);

      expect(result).toBe('Texto');
    });

    it('deve lidar com HTML vazio', () => {
      expect(stripHtml('')).toBe('');
      expect(stripHtml('<p></p>')).toBe('');
    });

    it('deve remover tags mas preservar espaços', () => {
      const input = '<p>Palavra 1</p> <p>Palavra 2</p>';
      const result = stripHtml(input);

      expect(result).toContain('Palavra 1');
      expect(result).toContain('Palavra 2');
    });
  });

  describe('sanitizeBasicFormatting', () => {
    it('deve permitir apenas formatação básica', () => {
      const input = '<p>Texto <b>negrito</b> <i>itálico</i></p>';
      const result = sanitizeBasicFormatting(input);

      expect(result).toContain('<b>');
      expect(result).toContain('<i>');
    });

    it('deve remover tags não permitidas', () => {
      const input = '<div><p>Texto</p><script>alert(1)</script></div>';
      const result = sanitizeBasicFormatting(input);

      expect(result).not.toContain('<div>');
      expect(result).not.toContain('<p>');
      expect(result).not.toContain('<script>');
      expect(result).toContain('Texto');
    });

    it('deve permitir links com href apenas', () => {
      const input = '<a href="https://exemplo.com" target="_blank">Link</a>';
      const result = sanitizeBasicFormatting(input);

      expect(result).toContain('href');
      expect(result).not.toContain('target');
    });

    it('deve permitir quebras de linha', () => {
      const input = 'Linha 1<br>Linha 2<br/>Linha 3';
      const result = sanitizeBasicFormatting(input);

      expect(result).toContain('<br>');
    });

    it('deve remover iframes e scripts', () => {
      const input = '<b>Texto</b><iframe src="evil.com"></iframe>';
      const result = sanitizeBasicFormatting(input);

      expect(result).toContain('<b>Texto</b>');
      expect(result).not.toContain('<iframe');
    });
  });

  describe('sanitizeUrl', () => {
    it('deve permitir URLs HTTP', () => {
      expect(sanitizeUrl('http://exemplo.com')).toBe('http://exemplo.com');
    });

    it('deve permitir URLs HTTPS', () => {
      expect(sanitizeUrl('https://exemplo.com')).toBe('https://exemplo.com');
    });

    it('deve permitir URLs mailto', () => {
      expect(sanitizeUrl('mailto:teste@exemplo.com')).toBe('mailto:teste@exemplo.com');
    });

    it('deve bloquear javascript: URLs', () => {
      expect(sanitizeUrl('javascript:alert(1)')).toBe('');
    });

    it('deve bloquear data: URLs', () => {
      expect(sanitizeUrl('data:text/html,<script>alert(1)</script>')).toBe('');
    });

    it('deve bloquear vbscript: URLs', () => {
      expect(sanitizeUrl('vbscript:msgbox(1)')).toBe('');
    });

    it('deve bloquear file: URLs', () => {
      expect(sanitizeUrl('file:///etc/passwd')).toBe('');
    });

    it('deve lidar com URLs inválidas', () => {
      expect(sanitizeUrl('not a url')).toBe('');
      expect(sanitizeUrl('')).toBe('');
      expect(sanitizeUrl('   ')).toBe('');
    });

    it('deve permitir URLs com query strings', () => {
      const url = 'https://exemplo.com?param=value&other=123';
      expect(sanitizeUrl(url)).toBe(url);
    });

    it('deve permitir URLs com fragmentos', () => {
      const url = 'https://exemplo.com#section';
      expect(sanitizeUrl(url)).toBe(url);
    });

    it('deve permitir URLs com portas', () => {
      const url = 'https://exemplo.com:8080/path';
      expect(sanitizeUrl(url)).toBe(url);
    });
  });
});
