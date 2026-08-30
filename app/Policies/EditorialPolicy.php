<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class EditorialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasEditorialAccess();
    }

    public function view(User $user, Model $model): bool
    {
        return $user->hasEditorialAccess();
    }

    public function create(User $user): bool
    {
        return $user->canCreateEditorialContent();
    }

    public function update(User $user, Model $model): bool
    {
        return $user->canUpdateEditorialContent();
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->canDeleteEditorialContent();
    }

    public function deleteAny(User $user): bool
    {
        return $user->canDeleteEditorialContent();
    }

    public function restore(User $user, Model $model): bool
    {
        return $user->canDeleteEditorialContent();
    }

    public function restoreAny(User $user): bool
    {
        return $user->canDeleteEditorialContent();
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $user->canDeleteEditorialContent();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->canDeleteEditorialContent();
    }
}
