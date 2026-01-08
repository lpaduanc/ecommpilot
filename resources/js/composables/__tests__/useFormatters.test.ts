/**
 * Testes unitários para useFormatters composable
 */

import { describe, it, expect } from 'vitest';
import { useFormatters } from '../useFormatters';

describe('useFormatters', () => {
  const { formatCurrency, formatDate, formatPercentage } = useFormatters();

  describe('formatCurrency', () => {
    it('deve formatar valores positivos corretamente', () => {
      expect(formatCurrency(1000)).toBe('R$ 1.000,00');
      expect(formatCurrency(1234.56)).toBe('R$ 1.234,56');
      expect(formatCurrency(999999.99)).toBe('R$ 999.999,99');
    });

    it('deve formatar zero corretamente', () => {
      expect(formatCurrency(0)).toBe('R$ 0,00');
    });

    it('deve formatar valores negativos corretamente', () => {
      expect(formatCurrency(-1000)).toBe('-R$ 1.000,00');
      expect(formatCurrency(-1234.56)).toBe('-R$ 1.234,56');
    });

    it('deve arredondar valores com mais de 2 casas decimais', () => {
      expect(formatCurrency(10.999)).toBe('R$ 11,00');
      expect(formatCurrency(10.994)).toBe('R$ 10,99');
    });

    it('deve formatar valores muito grandes', () => {
      expect(formatCurrency(1000000)).toBe('R$ 1.000.000,00');
      expect(formatCurrency(1234567.89)).toBe('R$ 1.234.567,89');
    });

    it('deve formatar valores decimais pequenos', () => {
      expect(formatCurrency(0.01)).toBe('R$ 0,01');
      expect(formatCurrency(0.99)).toBe('R$ 0,99');
    });
  });

  describe('formatDate', () => {
    it('deve formatar datas ISO corretamente', () => {
      expect(formatDate('2024-01-15')).toBe('15/01/2024');
      expect(formatDate('2024-12-31')).toBe('31/12/2024');
    });

    it('deve formatar objetos Date corretamente', () => {
      const date = new Date('2024-01-15T10:30:00');
      expect(formatDate(date)).toBe('15/01/2024');
    });

    it('deve formatar datas com timestamp', () => {
      const date = new Date('2024-06-15T15:45:30Z');
      expect(formatDate(date)).toMatch(/\d{2}\/\d{2}\/2024/);
    });

    it('deve lidar com diferentes formatos de string de data', () => {
      expect(formatDate('2024-01-01')).toBe('01/01/2024');
      expect(formatDate('2024/01/01')).toBe('01/01/2024');
    });
  });

  describe('formatPercentage', () => {
    it('deve formatar porcentagens inteiras', () => {
      expect(formatPercentage(100)).toBe('100.00%');
      expect(formatPercentage(50)).toBe('50.00%');
      expect(formatPercentage(0)).toBe('0.00%');
    });

    it('deve formatar porcentagens com decimais', () => {
      expect(formatPercentage(25.5)).toBe('25.50%');
      expect(formatPercentage(33.33)).toBe('33.33%');
      expect(formatPercentage(66.666)).toBe('66.67%');
    });

    it('deve formatar porcentagens negativas', () => {
      expect(formatPercentage(-10)).toBe('-10.00%');
      expect(formatPercentage(-25.5)).toBe('-25.50%');
    });

    it('deve sempre mostrar 2 casas decimais', () => {
      expect(formatPercentage(10)).toBe('10.00%');
      expect(formatPercentage(10.1)).toBe('10.10%');
      expect(formatPercentage(10.12)).toBe('10.12%');
      expect(formatPercentage(10.123)).toBe('10.12%');
    });

    it('deve formatar valores muito pequenos', () => {
      expect(formatPercentage(0.01)).toBe('0.01%');
      expect(formatPercentage(0.001)).toBe('0.00%');
    });

    it('deve formatar valores muito grandes', () => {
      expect(formatPercentage(1000)).toBe('1000.00%');
      expect(formatPercentage(9999.99)).toBe('9999.99%');
    });
  });
});
