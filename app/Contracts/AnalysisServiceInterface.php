<?php

namespace App\Contracts;

use App\DTOs\StoreDataDTO;
use App\Models\Analysis;
use App\Models\Store;
use App\Models\User;

interface AnalysisServiceInterface
{
    /**
     * Cria uma nova solicitação de análise
     */
    public function createAnalysis(User $user, Store $store): Analysis;

    /**
     * Processa uma análise pendente
     */
    public function processAnalysis(Analysis $analysis): void;

    /**
     * Prepara os dados da loja para análise
     */
    public function prepareStoreData(Store $store, \DateTimeInterface $startDate, \DateTimeInterface $endDate): StoreDataDTO;

    /**
     * Verifica se o usuário pode solicitar uma nova análise
     */
    public function canRequestAnalysis(User $user): bool;

    /**
     * Obtém a próxima data disponível para análise
     */
    public function getNextAvailableAnalysisTime(User $user): ?\DateTimeInterface;

    /**
     * Obtém a análise mais recente de uma loja
     */
    public function getLatestAnalysis(Store $store): ?Analysis;
}
