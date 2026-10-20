<?php

namespace App\Policies;

use App\Models\FrameGeneration;
use App\Models\User;

class FrameGenerationPolicy
{
    /** Share pages are public; managing a generation requires ownership. */
    public function update(User $user, FrameGeneration $generation): bool
    {
        return $user->isAdmin() || ($generation->user_id !== null && $generation->user_id === $user->id);
    }

    public function delete(User $user, FrameGeneration $generation): bool
    {
        return $this->update($user, $generation);
    }

    public function feature(User $user, FrameGeneration $generation): bool
    {
        return $user->isAdmin();
    }
}
