<?php

namespace App\Policies;

use App\Models\AssetDocument;
use App\Models\User;

class AssetDocumentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_super_admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('documents.view');
    }

    public function view(User $user, AssetDocument $assetDocument): bool
    {
        return $user->tenant_id === $assetDocument->tenant_id
            && $user->hasPermission('documents.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('documents.create');
    }

    public function update(User $user, AssetDocument $assetDocument): bool
    {
        return $user->tenant_id === $assetDocument->tenant_id
            && $user->hasPermission('documents.update');
    }

    public function delete(User $user, AssetDocument $assetDocument): bool
    {
        return $user->tenant_id === $assetDocument->tenant_id
            && $user->hasPermission('documents.delete');
    }
}
