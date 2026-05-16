<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationDocumentController extends Controller
{
    public function index(Application $application): JsonResponse
    {
        // TODO (Step 8): authorisation check, fetch documents, generate signed URLs
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    public function store(Request $request, Application $application): JsonResponse
    {
        // TODO (Step 8): validate file type + size, upload to Supabase Storage, create record
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    public function destroy(ApplicationDocument $document): JsonResponse
    {
        // TODO (Step 8): check application is draft, delete from Supabase Storage, delete record
        return response()->json(['message' => 'Not yet implemented'], 501);
    }
}
