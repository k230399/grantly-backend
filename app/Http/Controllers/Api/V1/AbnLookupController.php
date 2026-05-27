<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AbrLookupService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AbnLookupController extends Controller
{
    // GET /api/v1/abn-lookup/{abn}
    // Looks up an 11-digit ABN against the Australian Business Register and returns the
    // normalised record. Errors come back as typed JSON: not_found, cancelled, invalid_format,
    // not_configured (env var missing), or lookup_failed. The router constrains {abn} to 11 digits.
    public function show(string $abn, AbrLookupService $service): JsonResponse
    {
        try {
            $data = $service->lookup($abn);
        } catch (RuntimeException $e) {
            return $this->errorFor($e->getMessage());
        }

        return response()->json(['data' => $data]);
    }

    // Maps the service's exception codes to the project's standard error envelope.
    private function errorFor(string $code): JsonResponse
    {
        return match ($code) {
            'invalid_format' => response()->json([
                'error' => [
                    'code'    => 'invalid_format',
                    'message' => 'ABN must be exactly 11 digits.',
                ],
            ], 422),

            'not_configured' => response()->json([
                'error' => [
                    'code'    => 'not_configured',
                    'message' => 'ABN lookup is not yet enabled. Contact support.',
                ],
            ], 503),

            'not_found' => response()->json([
                'error' => [
                    'code'    => 'not_found',
                    'message' => 'This ABN was not found on the Australian Business Register.',
                ],
            ], 404),

            'cancelled' => response()->json([
                'error' => [
                    'code'    => 'cancelled',
                    'message' => 'This ABN is on the register but is no longer active.',
                ],
            ], 422),

            default => response()->json([
                'error' => [
                    'code'    => 'lookup_failed',
                    'message' => 'Could not reach the Australian Business Register. Please try again.',
                ],
            ], 502),
        };
    }
}
