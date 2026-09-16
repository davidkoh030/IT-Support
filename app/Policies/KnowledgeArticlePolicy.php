<?php

namespace App\Policies;

use App\Models\KnowledgeArticle;
use App\Models\User;

class KnowledgeArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KnowledgeArticle $article): bool
    {
        return $article->status === 'published' || $article->owner_id === $user->id || $user->hasRole('administrator') || $user->activeAccessGrants()->whereIn('role', ['it_agent', 'it_manager'])->exists();
    }

    public function manage(User $user): bool
    {
        return $user->hasRole('administrator') || $user->activeAccessGrants()->whereIn('role', ['it_agent', 'it_manager'])->exists();
    }
}
