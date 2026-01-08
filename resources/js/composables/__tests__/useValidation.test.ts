/**
 * Testes unitários para useValidation composable
 */

import { describe, it, expect } from 'vitest';
import { useValidation } from '../useValidation';

describe('useValidation', () => {
  const { isValidEmail, isValidCPF } = useValidation();

  describe('isValidEmail', () => {
    it('deve validar emails válidos', () => {
      expect(isValidEmail('teste@exemplo.com')).toBe(true);
      expect(isValidEmail('user@domain.com')).toBe(true);
      expect(isValidEmail('nome.sobrenome@empresa.com.br')).toBe(true);
      expect(isValidEmail('usuario+tag@email.com')).toBe(true);
      expect(isValidEmail('user123@test-domain.com')).toBe(true);
    });

    it('deve rejeitar emails sem @', () => {
      expect(isValidEmail('testexemplo.com')).toBe(false);
      expect(isValidEmail('usuario.dominio.com')).toBe(false);
    });

    it('deve rejeitar emails sem domínio', () => {
      expect(isValidEmail('teste@')).toBe(false);
      expect(isValidEmail('teste@exemplo')).toBe(false);
    });

    it('deve rejeitar emails sem nome de usuário', () => {
      expect(isValidEmail('@exemplo.com')).toBe(false);
      expect(isValidEmail('@')).toBe(false);
    });

    it('deve rejeitar emails com espaços', () => {
      expect(isValidEmail('teste @exemplo.com')).toBe(false);
      expect(isValidEmail('teste@ exemplo.com')).toBe(false);
      expect(isValidEmail(' teste@exemplo.com')).toBe(false);
      expect(isValidEmail('teste@exemplo.com ')).toBe(false);
    });

    it('deve rejeitar emails vazios ou inválidos', () => {
      expect(isValidEmail('')).toBe(false);
      expect(isValidEmail('   ')).toBe(false);
      expect(isValidEmail('invalid')).toBe(false);
      expect(isValidEmail('..@exemplo.com')).toBe(false);
    });

    it('deve aceitar emails com caracteres especiais permitidos', () => {
      expect(isValidEmail('user+filter@domain.com')).toBe(true);
      expect(isValidEmail('user.name@domain.com')).toBe(true);
      expect(isValidEmail('user_name@domain.com')).toBe(true);
    });
  });

  describe('isValidCPF', () => {
    it('deve validar CPFs válidos com formatação', () => {
      expect(isValidCPF('123.456.789-09')).toBe(true);
      expect(isValidCPF('111.444.777-35')).toBe(true);
    });

    it('deve validar CPFs válidos sem formatação', () => {
      expect(isValidCPF('12345678909')).toBe(true);
      expect(isValidCPF('11144477735')).toBe(true);
    });

    it('deve rejeitar CPFs com todos os dígitos iguais', () => {
      expect(isValidCPF('111.111.111-11')).toBe(false);
      expect(isValidCPF('222.222.222-22')).toBe(false);
      expect(isValidCPF('00000000000')).toBe(false);
      expect(isValidCPF('99999999999')).toBe(false);
    });

    it('deve rejeitar CPFs com número incorreto de dígitos', () => {
      expect(isValidCPF('123.456.789-0')).toBe(false);
      expect(isValidCPF('123.456.789-099')).toBe(false);
      expect(isValidCPF('1234567890')).toBe(false);
      expect(isValidCPF('123456789012')).toBe(false);
    });

    it('deve rejeitar CPFs com dígitos verificadores inválidos', () => {
      expect(isValidCPF('123.456.789-00')).toBe(false);
      expect(isValidCPF('123.456.789-99')).toBe(false);
      expect(isValidCPF('12345678900')).toBe(false);
    });

    it('deve rejeitar CPFs vazios ou inválidos', () => {
      expect(isValidCPF('')).toBe(false);
      expect(isValidCPF('   ')).toBe(false);
      expect(isValidCPF('abc.def.ghi-jk')).toBe(false);
    });

    it('deve aceitar CPFs com formatação parcial', () => {
      const cpfSemPontos = '12345678909';
      const cpfComPontos = '123.456.789-09';

      expect(isValidCPF(cpfSemPontos)).toBe(isValidCPF(cpfComPontos));
    });

    it('deve validar CPFs conhecidos como válidos', () => {
      // CPFs gerados que são válidos
      const cpfsValidos = [
        '529.982.247-25',
        '52998224725',
        '796.681.527-70',
        '79668152770',
      ];

      cpfsValidos.forEach(cpf => {
        expect(isValidCPF(cpf)).toBe(true);
      });
    });

    it('deve rejeitar CPFs conhecidos como inválidos', () => {
      const cpfsInvalidos = [
        '123.456.789-10',
        '000.000.000-00',
        '999.999.999-99',
        '12345678901',
      ];

      cpfsInvalidos.forEach(cpf => {
        expect(isValidCPF(cpf)).toBe(false);
      });
    });
  });
});
