<?php

namespace BSPDX\Keystone\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Base class for exceptions thrown by Keystone.
 *
 * These are expected outcomes, not faults: they are not reported, and over
 * HTTP they render as a JSON client error with the exception's message.
 */
class KeystoneException extends RuntimeException implements ShouldntReport
{
    /**
     * The HTTP status this exception renders with.
     */
    public function status(): int
    {
        return 400;
    }

    /**
     * Render the exception as a JSON response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status());
    }
}
