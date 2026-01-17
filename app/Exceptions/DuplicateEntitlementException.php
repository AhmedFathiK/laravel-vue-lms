<?php

namespace App\Exceptions;

use Exception;

class DuplicateEntitlementException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
