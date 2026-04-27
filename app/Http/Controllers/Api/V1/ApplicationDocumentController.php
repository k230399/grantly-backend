<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles file upload, listing, and deletion for application documents.
 * Laravel does not store the files itself — it validates them, then
 * sends them to Supabase Storage and stores the metadata here.
 * Endpoint prefix: /api/v1/applications/{application}/documents
 */
class ApplicationDocumentController extends Controller
{
    /**
     * GET /api/v1/applications/{application}/documents
     *
     * Returns all documents attached to a specific application.
     * Each document includes a signed URL for downloading the file from Supabase Storage.
     *
     * @param Application $application — the application whose documents to list
     * @return JsonResponse — array of document objects including signed download URLs
     */
    public function index(Application $application): JsonResponse
    {
        // TODO (Step 8): authorisation check, fetch documents, generate signed URLs
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * POST /api/v1/applications/{application}/documents
     *
     * Uploads a new document for an application.
     * Validates file type (PDF, DOCX, XLSX, JPG, PNG) and size (max 10 MB).
     * Uploads the file to Supabase Storage and stores the metadata here.
     *
     * @param Request $request — multipart form data with 'file' and 'document_type'
     * @param Application $application — the application to attach the document to
     * @return JsonResponse — the newly created document record
     */
    public function store(Request $request, Application $application): JsonResponse
    {
        // TODO (Step 8): validate file type + size, upload to Supabase Storage, create record
        return response()->json(['message' => 'Not yet implemented'], 501);
    }

    /**
     * DELETE /api/v1/documents/{document}
     *
     * Deletes a document — removes both the metadata row and the file from Supabase Storage.
     * Only allowed while the application is still in "draft" status.
     *
     * @param ApplicationDocument $document — the document to delete
     * @return JsonResponse — 204 No Content on success
     */
    public function destroy(ApplicationDocument $document): JsonResponse
    {
        // TODO (Step 8): check application is draft, delete from Supabase Storage, delete record
        return response()->json(['message' => 'Not yet implemented'], 501);
    }
}
