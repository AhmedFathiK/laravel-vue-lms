<?php

namespace App\Policies;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReceiptPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can void the model.
     */
    public function void(User $user, Receipt $receipt): bool
    {
        return $user->can('void.receipts');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Receipt $receipt): bool
    {
        // Prevent deleting a voided receipt
        if ($receipt->voided_at) {
            return false;
        }

        return $user->can('delete.receipts');
    }
}