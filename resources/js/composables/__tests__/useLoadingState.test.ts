import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useLoadingState } from '../useLoadingState';

describe('useLoadingState', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('deve inicializar com valores padrão', () => {
    const { isLoading, error } = useLoadingState();

    expect(isLoading.value).toBe(false);
    expect(error.value).toBeNull();
  });

  it('deve definir isLoading como true durante execução', async () => {
    const { isLoading, execute } = useLoadingState();

    let isLoadingDuringExecution = false;

    const promise = execute(async () => {
      isLoadingDuringExecution = isLoading.value;
      return 'resultado';
    });

    expect(isLoading.value).toBe(true);
    await promise;
    expect(isLoadingDuringExecution).toBe(true);
  });

  it('deve definir isLoading como false após execução bem-sucedida', async () => {
    const { isLoading, execute } = useLoadingState();

    await execute(async () => {
      return 'resultado';
    });

    expect(isLoading.value).toBe(false);
  });

  it('deve retornar resultado da função executada', async () => {
    const { execute } = useLoadingState();
    const expectedResult = { data: 'test' };

    const result = await execute(async () => {
      return expectedResult;
    });

    expect(result).toEqual(expectedResult);
  });

  it('deve capturar e definir erro quando função falha', async () => {
    const { error, execute } = useLoadingState();
    const errorMessage = 'Erro de teste';

    await execute(async () => {
      throw new Error(errorMessage);
    });

    expect(error.value).toBe(errorMessage);
  });

  it('deve definir isLoading como false após erro', async () => {
    const { isLoading, execute } = useLoadingState();

    await execute(async () => {
      throw new Error('Erro');
    });

    expect(isLoading.value).toBe(false);
  });

  it('deve retornar undefined quando ocorre erro', async () => {
    const { execute } = useLoadingState();

    const result = await execute(async () => {
      throw new Error('Erro');
    });

    expect(result).toBeUndefined();
  });

  it('deve extrair mensagem de erro do response da API', async () => {
    const { error, execute } = useLoadingState();
    const apiErrorMessage = 'Erro da API';

    await execute(async () => {
      const apiError: any = new Error();
      apiError.response = {
        data: {
          message: apiErrorMessage,
        },
      };
      throw apiError;
    });

    expect(error.value).toBe(apiErrorMessage);
  });

  it('deve usar mensagem genérica quando erro não tem mensagem', async () => {
    const { error, execute } = useLoadingState();

    await execute(async () => {
      throw {}; // Erro sem mensagem
    });

    expect(error.value).toBe('Ocorreu um erro inesperado');
  });

  it('deve limpar erro com clearError', async () => {
    const { error, execute, clearError } = useLoadingState();

    await execute(async () => {
      throw new Error('Erro');
    });

    expect(error.value).not.toBeNull();

    clearError();

    expect(error.value).toBeNull();
  });

  it('deve limpar erro anterior ao executar nova função', async () => {
    const { error, execute } = useLoadingState();

    // Primeira execução com erro
    await execute(async () => {
      throw new Error('Primeiro erro');
    });

    expect(error.value).toBe('Primeiro erro');

    // Segunda execução bem-sucedida
    await execute(async () => {
      return 'sucesso';
    });

    expect(error.value).toBeNull();
  });

  it('deve funcionar com funções assíncronas que demoram', async () => {
    const { isLoading, execute } = useLoadingState();

    const promise = execute(async () => {
      await new Promise((resolve) => setTimeout(resolve, 50));
      return 'resultado';
    });

    expect(isLoading.value).toBe(true);

    const result = await promise;

    expect(isLoading.value).toBe(false);
    expect(result).toBe('resultado');
  });

  it('deve permitir múltiplas execuções sequenciais', async () => {
    const { execute, error } = useLoadingState();

    const result1 = await execute(async () => 'primeiro');
    const result2 = await execute(async () => 'segundo');

    expect(result1).toBe('primeiro');
    expect(result2).toBe('segundo');
    expect(error.value).toBeNull();
  });
});
