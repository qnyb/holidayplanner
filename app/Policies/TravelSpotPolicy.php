<?php

namespace App\Policies;

use App\Models\TravelSpot;
use App\Models\User;

class TravelSpotPolicy
{
    public function update(User $user, TravelSpot $travelSpot): bool
    {
        return $user->id === $travelSpot->user_id;
    }

    public function delete(User $user, TravelSpot $travelSpot): bool
    {
        return $user->id === $travelSpot->user_id;
    }
}
