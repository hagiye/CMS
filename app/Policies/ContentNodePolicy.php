<?php

namespace App\Policies;

use App\Models\ContentNode;
use App\Models\User;

class ContentNodePolicy extends EditorialPolicy
{
    public function publish(User $user, ContentNode $contentNode): bool
    {
        return $user->canPublishEditorialContent();
    }
}
