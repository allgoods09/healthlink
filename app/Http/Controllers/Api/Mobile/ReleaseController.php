<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Support\MobileReleaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseController extends Controller
{
    public function __construct(
        private readonly MobileReleaseManager $releaseManager
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $request->validate([
            'version_code' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->releaseManager->releaseCheckPayload(
                $request->filled('version_code') ? (int) $request->input('version_code') : null
            )
        );
    }
}
