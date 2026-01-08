<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class StorePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any stores.
     */
    public function viewAny(User $user): bool
    {
        // Admins podem ver todas as lojas
        if ($user->isAdmin()) {
            return true;
        }

        // Usuários só podem ver suas próprias lojas
        return true;
    }

    /**
     * Determine whether the user can view the store.
     */
    public function view(User $user, Store $store): bool
    {
        // Admins podem ver qualquer loja
        if ($user->isAdmin()) {
            return true;
        }

        // Usuário só pode ver suas próprias lojas
        return $user->id === $store->user_id;
    }

    /**
     * Determine whether the user can create stores.
     */
    public function create(User $user): bool
    {
        // Qualquer usuário autenticado pode criar uma loja
        return true;
    }

    /**
     * Determine whether the user can update the store.
     */
    public function update(User $user, Store $store): bool
    {
        // Admins podem atualizar qualquer loja
        if ($user->isAdmin()) {
            return true;
        }

        // Usuário só pode atualizar suas próprias lojas
        return $user->id === $store->user_id;
    }

    /**
     * Determine whether the user can delete the store.
     */
    public function delete(User $user, Store $store): bool
    {
        // Admins podem deletar qualquer loja
        if ($user->isAdmin()) {
            return true;
        }

        // Usuário só pode deletar suas próprias lojas
        return $user->id === $store->user_id;
    }

    /**
     * Determine whether the user can sync the store.
     */
    public function sync(User $user, Store $store): bool
    {
        // Mesmo comportamento de update
        return $this->update($user, $store);
    }

    /**
     * Determine whether the user can view store analytics.
     */
    public function viewAnalytics(User $user, Store $store): bool
    {
        // Mesmo comportamento de view
        return $this->view($user, $store);
    }

    /**
     * Determine whether the user can request AI analysis for the store.
     */
    public function requestAnalysis(User $user, Store $store): bool
    {
        // Precisa ser dono da loja e ter créditos
        if (! $this->view($user, $store)) {
            return false;
        }

        return $user->ai_credits > 0;
    }

    /**
     * Determine whether the user can restore the store.
     */
    public function restore(User $user, Store $store): bool
    {
        // Apenas admins podem restaurar lojas deletadas
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the store.
     */
    public function forceDelete(User $user, Store $store): bool
    {
        // Apenas admins podem deletar permanentemente
        return $user->isAdmin();
    }
}
