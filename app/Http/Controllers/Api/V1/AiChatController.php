<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\GrantRound;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    // POST /api/v1/ai/chat
    // Grounded chatbot endpoint. The frontend sends the conversation + a context_type;
    // the controller assembles the system prompt server-side (so the client can't tamper
    // with what the model sees) and streams an SSE response from OpenRouter. Each context
    // (apply / browse / dashboard / admin_review / admin_overview / admin_round_compose) has
    // its own prompt builder that hydrates from the real DB rows the caller is allowed to see.
    public function chat(Request $request): StreamedResponse|JsonResponse
    {
        // 4000-char cap per message keeps abuse cheap and the prompt small.
        $validated = $request->validate([
            'context_type'         => 'required|in:apply,browse,dashboard,admin_review,admin_overview,admin_round_compose',
            'application_id'       => 'required_if:context_type,apply,admin_review|uuid',
            'grant_round_id'       => 'sometimes|nullable|uuid',
            'messages'             => 'required|array|min:1|max:30',
            'messages.*.role'      => 'required|in:user,assistant',
            'messages.*.content'   => 'required|string|max:4000',
        ]);

        $user = $request->user();

        // Each helper handles its own authorization and may short-circuit with a 403.
        $systemPrompt = match ($validated['context_type']) {
            'apply'           => $this->buildApplyPrompt($user, $validated['application_id']),
            'browse'          => $this->buildBrowsePrompt($validated['grant_round_id'] ?? null),
            'dashboard'       => $this->buildDashboardPrompt($user),
            'admin_review'         => $this->buildAdminReviewPrompt($user, $validated['application_id']),
            'admin_overview'       => $this->buildAdminOverviewPrompt($user),
            'admin_round_compose'  => $this->buildAdminRoundComposePrompt($user, $validated['grant_round_id'] ?? null),
        };

        if ($systemPrompt instanceof JsonResponse) {
            return $systemPrompt;
        }

        // The server-assembled system prompt always comes first. Any 'system' role from the client is ignored.
        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $validated['messages']
        );

        return $this->streamCompletion($messages);
    }

    // Apply surface: bot sees the round details plus the draft's current values.
    private function buildApplyPrompt(User $user, string $applicationId): string|JsonResponse
    {
        $application = Application::with([
            'grantRound',
            'documents:id,application_id,form_field_id',
            'documentRequests' => function ($q) {
                $q->whereIn('status', ['pending', 'fulfilled'])->orderByDesc('requested_at');
            },
        ])->find($applicationId);

        if (! $application || $application->applicant_id !== $user->id) {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'You do not have access to this application.',
                ],
            ], 403);
        }

        $round = $application->grantRound;

        $roundBlock = sprintf(
            "GRANT ROUND\nTitle: %s\nDescription: %s\nEligibility criteria: %s\nMin funding: %s\nMax funding: %s\nCloses: %s",
            $round->title ?? '(untitled)',
            $round->description ?? '(none)',
            $round->eligibility_criteria ?? '(none)',
            $round->min_funding_amount !== null ? '$' . $round->min_funding_amount : '(no minimum)',
            $round->max_funding_amount !== null ? '$' . $round->max_funding_amount : '(no maximum)',
            $round->closes_at?->toDayDateTimeString() ?? '(no close date)',
        );

        $draftBlock = sprintf(
            "APPLICANT'S CURRENT DRAFT\nProject name: %s\nProject description: %s\nFunding requested: %s\nTotal project budget: %s\nDeclaration accepted: %s",
            $application->project_name ?: '(blank)',
            $application->project_description ?: '(blank)',
            $application->funding_requested ? '$' . $application->funding_requested : '(blank)',
            $application->total_project_budget ? '$' . $application->total_project_budget : '(blank)',
            $application->declaration_accepted ? 'yes' : 'no',
        );

        // Custom questions defined by this round, plus the applicant's current answers, so the
        // assistant can explain and help phrase them. Empty when the round has no custom schema.
        $customBlock = $this->formatCustomQuestions($round, $application);

        // Surface admin-driven document requests so the assistant can nudge the applicant
        // to upload the right files. Hidden when there are none.
        $requestsBlock = '';
        if ($application->documentRequests->isNotEmpty()) {
            $requestsList = $application->documentRequests->map(fn ($r) => sprintf(
                '- %s (%s)%s',
                $r->label,
                $r->status,
                $r->description ? ': ' . str_replace("\n", ' ', $r->description) : '',
            ))->implode("\n");

            $requestsBlock = "\n\nDOCUMENTS REQUESTED BY ADMIN:\n$requestsList";
        }

        return <<<PROMPT
You are Grantly's friendly application assistant. The user is filling out a grant application and needs help. Be concise (2-4 sentences unless they ask for a draft), encouraging, and specific to their grant round.

You can help with: explaining what a field is asking for, suggesting how to phrase a project description, checking whether their funding request fits the round's limits, and pointing out missing information. You cannot submit the application for them, change their answers directly, or guarantee they will be approved.

If a question is outside the application (general chit-chat, unrelated topics), politely redirect them back to their application.

Here is everything you know about their context:

$roundBlock

$draftBlock$customBlock$requestsBlock
PROMPT;
    }

    // Builds a "CUSTOM QUESTIONS" block from the round's application_form_schema, pairing each
    // field with the applicant's current answer (from form_data, or upload status for document
    // fields). Choice answers are mapped from stored option ids back to their labels. Returns an
    // empty string when the round defines no custom schema.
    private function formatCustomQuestions(?GrantRound $round, Application $application): string
    {
        $schema = $round?->application_form_schema;
        if (! is_array($schema) || empty($schema['fields']) || ! is_array($schema['fields'])) {
            return '';
        }

        $formData = is_array($application->form_data) ? $application->form_data : [];
        $uploadedFieldIds = $application->documents
            ->whereNotNull('form_field_id')
            ->pluck('form_field_id')
            ->all();

        $lines = [];
        foreach ($schema['fields'] as $field) {
            $label   = $field['label'] ?? '(untitled question)';
            $type    = $field['type'] ?? 'text';
            $fieldId = $field['id'] ?? null;
            $req     = empty($field['required']) ? 'optional' : 'required';

            // Map option ids -> labels so choice answers read as words, not ids.
            $optionLabels = [];
            foreach (($field['options'] ?? []) as $opt) {
                if (is_array($opt) && isset($opt['id'])) {
                    $optionLabels[$opt['id']] = $opt['label'] ?? $opt['id'];
                }
            }
            $optionsHint = $optionLabels ? ' [options: ' . implode(', ', array_values($optionLabels)) . ']' : '';
            $help        = ! empty($field['help_text']) ? ' — help: ' . str_replace("\n", ' ', $field['help_text']) : '';

            if ($type === 'document') {
                $answer = in_array($fieldId, $uploadedFieldIds, true) ? '(document uploaded)' : '(no document yet)';
            } else {
                $raw = $fieldId !== null ? ($formData[$fieldId] ?? null) : null;
                if (is_array($raw)) {
                    $mapped = array_map(fn ($v) => $optionLabels[$v] ?? $v, $raw);
                    $answer = $mapped ? implode(', ', $mapped) : '(blank)';
                } elseif ($raw === null || $raw === '') {
                    $answer = '(blank)';
                } else {
                    $answer = $optionLabels[$raw] ?? (string) $raw;
                }

                // Keep the prompt small even if an applicant pasted a long answer.
                if (mb_strlen($answer) > 500) {
                    $answer = mb_substr($answer, 0, 500) . '…';
                }
            }

            $lines[] = sprintf("- %s (%s, %s)%s%s\n  Current answer: %s", $label, $type, $req, $optionsHint, $help, $answer);
        }

        return "\n\nCUSTOM QUESTIONS FOR THIS ROUND (specific to this grant):\n" . implode("\n", $lines);
    }

    // Browse surface: a specific round's full public details, or a list of every currently open round.
    private function buildBrowsePrompt(?string $grantRoundId): string
    {
        if ($grantRoundId) {
            $round = GrantRound::find($grantRoundId);

            if ($round && $round->is_published) {
                $block = sprintf(
                    "GRANT ROUND\nTitle: %s\nStatus: %s\nDescription: %s\nEligible organisation types: %s\nGeographic restrictions: %s\nEligibility criteria: %s\nKey focus areas: %s\nMin funding: %s\nMax funding: %s\nOpens: %s\nCloses: %s\nContact: %s",
                    $round->title ?? '(untitled)',
                    $round->status ?? '(unknown)',
                    $round->description ?? '(none)',
                    $round->eligible_organisation_types ?? '(none)',
                    $round->geographic_restrictions ?? '(none)',
                    $round->eligibility_criteria ?? '(none)',
                    is_array($round->key_focus_areas) ? implode(', ', $round->key_focus_areas) : '(none)',
                    $round->min_funding_amount !== null ? '$' . $round->min_funding_amount : '(no minimum)',
                    $round->max_funding_amount !== null ? '$' . $round->max_funding_amount : '(no maximum)',
                    $round->opens_at?->toDayDateTimeString() ?? '(unknown)',
                    $round->closes_at?->toDayDateTimeString() ?? '(no close date)',
                    $round->contact_email ?? '(not provided)',
                );

                return <<<PROMPT
You are Grantly's friendly grants guide. The user is reading about a specific grant round and wants help understanding it. Be concise (2-4 sentences) and grounded in the facts below.

You can help with: explaining eligibility, summarising the funding range, clarifying dates, and suggesting whether an organisation might be a fit. You cannot submit an application for them or guarantee they will be approved. If they want to apply, tell them to click the Apply button on the page.

If a question is outside this round (other topics, general chit-chat), politely redirect.

$block
PROMPT;
            }
        }

        $openRounds = GrantRound::where('is_published', true)
            ->where('status', 'open')
            ->orderBy('closes_at', 'asc')
            ->limit(20)
            ->get();

        if ($openRounds->isEmpty()) {
            $list = '(No grant rounds are currently open.)';
        } else {
            $list = $openRounds->map(function ($r) {
                return sprintf(
                    "- %s | Closes: %s | Funding: %s-%s | Focus: %s",
                    $r->title,
                    $r->closes_at?->toFormattedDateString() ?? 'no close date',
                    $r->min_funding_amount !== null ? '$' . $r->min_funding_amount : 'no min',
                    $r->max_funding_amount !== null ? '$' . $r->max_funding_amount : 'no max',
                    is_array($r->key_focus_areas) && count($r->key_focus_areas) ? implode(', ', $r->key_focus_areas) : 'general',
                );
            })->implode("\n");
        }

        return <<<PROMPT
You are Grantly's friendly grants guide. The user is browsing the list of open grant rounds and wants help. Be concise (2-4 sentences).

You can help with: recommending rounds that match an applicant's situation, comparing funding ranges, and explaining what "open" or "closed" means. If they describe their organisation or project, suggest the most relevant round(s) by title.

CURRENTLY OPEN ROUNDS:
$list
PROMPT;
    }

    // Dashboard surface: every application the caller owns plus a summary of each round.
    private function buildDashboardPrompt(User $user): string
    {
        $applications = $user->applications()
            ->with('grantRound:id,title,status,closes_at,max_funding_amount')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($applications->isEmpty()) {
            $list = "(You haven't started any applications yet.)";
        } else {
            $list = $applications->map(function (Application $a) {
                return sprintf(
                    "- [%s] %s | Status: %s | Round: %s | Submitted: %s | Funding requested: %s",
                    $a->reference_number ?? $a->id,
                    $a->project_name ?: '(untitled)',
                    $a->status,
                    $a->grantRound?->title ?? '(unknown)',
                    $a->submitted_at?->toFormattedDateString() ?? 'not submitted',
                    $a->funding_requested ? '$' . $a->funding_requested : '(blank)',
                );
            })->implode("\n");
        }

        $name = $user->full_name ?: 'there';

        return <<<PROMPT
You are Grantly's friendly applicant assistant. The user ($name) is logged into their dashboard and wants help understanding their applications. Be concise (2-4 sentences) and only reference applications listed below.

You can help with: explaining what each application status means (draft, submitted, under_review, approved, rejected, withdrawn), pointing out which applications still need work, summarising their portfolio, and reminding them of close dates. You cannot edit or submit applications for them.

If asked about applications you can't see in the list, say you don't have that information and suggest they refresh.

THIS USER'S APPLICATIONS:
$list
PROMPT;
    }

    // Admin review surface: full picture of one application for a reviewer.
    // Includes applicant profile, status history, and existing review notes.
    private function buildAdminReviewPrompt(User $user, string $applicationId): string|JsonResponse
    {
        if ($user->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can use the review assistant.',
                ],
            ], 403);
        }

        $application = Application::with([
            'grantRound',
            'applicant',
            'documents',
            'statusHistory',
            'reviewNotes.reviewer',
            'documentRequests.document',
        ])->find($applicationId);

        // Drafts are invisible to admins (private until submitted), including the review assistant.
        if (! $application || $application->status === 'draft') {
            return response()->json([
                'error' => [
                    'code'    => 'not_found',
                    'message' => 'Application not found.',
                ],
            ], 404);
        }

        $round    = $application->grantRound;
        $a        = $application->applicant;
        $docs     = $application->documents;
        $history  = $application->statusHistory;
        $notes    = $application->reviewNotes;

        $roundBlock = sprintf(
            "GRANT ROUND\nTitle: %s\nMax funding: %s\nEligibility criteria: %s\nAssessment criteria: %s",
            $round?->title ?? '(unknown)',
            $round?->max_funding_amount !== null ? '$' . $round->max_funding_amount : '(no max)',
            $round?->eligibility_criteria ?? '(none)',
            $round?->assessment_criteria ?? '(none)',
        );

        $appBlock = sprintf(
            "APPLICATION\nReference: %s\nStatus: %s\nProject name: %s\nProject description: %s\nFunding requested: %s\nTotal project budget: %s\nDeclaration accepted: %s\nSubmitted: %s\nForm answers: %s",
            $application->reference_number ?? $application->id,
            $application->status,
            $application->project_name ?: '(blank)',
            $application->project_description ?: '(blank)',
            $application->funding_requested ? '$' . $application->funding_requested : '(blank)',
            $application->total_project_budget ? '$' . $application->total_project_budget : '(blank)',
            $application->declaration_accepted ? 'yes' : 'no',
            $application->submitted_at?->toDayDateTimeString() ?? 'not submitted',
            $application->form_data ? json_encode($application->form_data) : '(none)',
        );

        $applicantBlock = sprintf(
            "APPLICANT\nName: %s\nEmail: %s\nOrganisation: %s\nABN: %s\nPhone: %s\nLocation: %s %s %s",
            $a?->full_name ?? '(unknown)',
            $a?->email ?? '(unknown)',
            $a?->organisation_name ?? '(not provided)',
            $a?->abn ?? '(not provided)',
            $a?->phone ?? '(not provided)',
            $a?->address ?? '',
            $a?->state ?? '',
            $a?->postcode ?? '',
        );

        $docsList = $docs->isEmpty()
            ? '(no documents uploaded)'
            : $docs->map(fn ($d) => sprintf('- %s (%s)', $d->file_name, $d->document_type ?? 'document'))->implode("\n");

        // Admin-driven document requests, with their fulfillment state and the linked file when present.
        $requests = $application->documentRequests;
        $requestsList = $requests->isEmpty()
            ? '(no document requests)'
            : $requests->map(fn ($r) => sprintf(
                '- %s [%s]%s%s',
                $r->label,
                $r->status,
                $r->description ? ': ' . str_replace("\n", ' ', $r->description) : '',
                $r->document ? ' — uploaded: ' . $r->document->file_name : ' — no file yet',
            ))->implode("\n");

        $historyList = $history->isEmpty()
            ? '(no status changes yet)'
            : $history->map(fn ($h) => sprintf(
                '- %s: %s → %s (notes: %s)',
                $h->changed_at?->toFormattedDateString() ?? '(no date)',
                $h->previous_status ?? '(none)',
                $h->new_status,
                $h->notes ? str_replace("\n", ' ', $h->notes) : 'none',
            ))->implode("\n");

        $notesList = $notes->isEmpty()
            ? '(no review notes yet)'
            : $notes->map(fn ($n) => sprintf(
                '- %s by %s: %s',
                $n->created_at?->toFormattedDateString() ?? '(no date)',
                $n->reviewer?->full_name ?? 'unknown reviewer',
                str_replace("\n", ' ', $n->note_content),
            ))->implode("\n");

        return <<<PROMPT
You are Grantly Assistant, helping an administrator review a single grant application. Be concise but thorough: 2-5 sentences for analysis questions, longer drafts only if they explicitly ask. Stay grounded in the facts below — do not invent details.

You can help with: summarising the application, scoring it against the assessment criteria, flagging missing information, drafting reviewer notes or status-change messages, and explaining the audit trail. You cannot change the application's status or submit notes on the admin's behalf. If asked for a recommendation, weigh both strengths and weaknesses and end with a clear lean ("looks strong", "needs more info", "below criteria") rather than a guarantee.

$roundBlock

$appBlock

$applicantBlock

DOCUMENTS:
$docsList

REQUESTED DOCUMENTS:
$requestsList

STATUS HISTORY:
$historyList

EXISTING REVIEW NOTES:
$notesList
PROMPT;
    }

    // Admin overview surface: aggregates across every application in the system.
    // Snapshot only — refreshed each turn but frozen for the duration of one response.
    private function buildAdminOverviewPrompt(User $user): string|JsonResponse
    {
        if ($user->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can use the overview assistant.',
                ],
            ], 403);
        }

        // Status counts: how many applications sit at each lifecycle stage. Drafts are excluded
        // entirely — admins don't see applicants' unsubmitted work, not even as a tally.
        $statusCounts = Application::where('status', '!=', 'draft')
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $statusBlock = collect(['submitted', 'under_review', 'approved', 'rejected', 'withdrawn'])
            ->map(fn ($s) => sprintf('%s: %d', $s, $statusCounts[$s] ?? 0))
            ->implode(', ');

        // Recent submission volume — the "what came in lately" metric.
        $submittedLast7 = Application::where('status', '!=', 'draft')
            ->where('submitted_at', '>=', now()->subDays(7))
            ->count();

        // Per-round breakdown so the bot can answer "which round is busiest?". Drafts excluded.
        $perRound = Application::where('status', '!=', 'draft')
            ->selectRaw('grant_round_id, COUNT(*) as c')
            ->groupBy('grant_round_id')
            ->orderByDesc('c')
            ->with('grantRound:id,title,status')
            ->limit(10)
            ->get();

        $roundsBlock = $perRound->isEmpty()
            ? '(no applications yet)'
            : $perRound->map(fn ($r) => sprintf(
                '- %s [%s]: %d applications',
                $r->grantRound?->title ?? '(unknown round)',
                $r->grantRound?->status ?? '?',
                $r->c,
            ))->implode("\n");

        // Most recent submitted applications so the bot can name specific ones.
        $recent = Application::with('grantRound:id,title', 'applicant:id,full_name')
            ->where('status', '!=', 'draft')
            ->orderByDesc('submitted_at')
            ->limit(10)
            ->get();

        $recentBlock = $recent->isEmpty()
            ? '(none yet)'
            : $recent->map(fn (Application $a) => sprintf(
                '- [%s] %s by %s | Round: %s | Status: %s | Submitted: %s',
                $a->reference_number ?? $a->id,
                $a->project_name ?: '(untitled)',
                $a->applicant?->full_name ?? 'unknown',
                $a->grantRound?->title ?? '(unknown)',
                $a->status,
                $a->submitted_at?->toFormattedDateString() ?? 'unknown',
            ))->implode("\n");

        return <<<PROMPT
You are Grantly Assistant, helping an administrator understand the current state of the application queue. Be concise (2-4 sentences). Only reference numbers and applications that appear in the snapshot below — never invent figures.

You can help with: summarising the queue, pointing out backlogs, highlighting which rounds are getting the most interest, and surfacing recently submitted applications for review. You cannot change application statuses or send reviewer comms.

The data below is a snapshot taken just now. If they ask for newer numbers later in this conversation, tell them the snapshot is for the start of this chat and suggest they refresh.

STATUS COUNTS (across all submitted applications):
$statusBlock

SUBMITTED IN THE LAST 7 DAYS: $submittedLast7

APPLICATIONS PER ROUND (top 10):
$roundsBlock

10 MOST RECENT SUBMITTED APPLICATIONS:
$recentBlock
PROMPT;
    }

    // Admin round-compose surface: helps an admin write or refine a grant round.
    // On /new the round_id is null and the bot is a generic copywriting helper.
    // On /edit the bot sees the round's saved values so it can react to them.
    private function buildAdminRoundComposePrompt(User $user, ?string $grantRoundId): string|JsonResponse
    {
        if ($user->role !== 'admin') {
            return response()->json([
                'error' => [
                    'code'    => 'forbidden',
                    'message' => 'Only administrators can use the round composer.',
                ],
            ], 403);
        }

        $context = '(This is a new grant round. Nothing has been saved yet, so respond to whatever the admin describes in the chat.)';

        if ($grantRoundId) {
            $round = GrantRound::find($grantRoundId);
            if ($round) {
                $context = sprintf(
                    "CURRENT SAVED VALUES\nTitle: %s\nShort description: %s\nDescription: %s\nEligible organisation types: %s\nGeographic restrictions: %s\nEligibility criteria: %s\nAssessment criteria: %s\nKey focus areas: %s\nRequired documents: %s\nMin funding: %s\nMax funding: %s\nTotal funding pool: %s\nStatus: %s\nPublished: %s\nOpens: %s\nCloses: %s",
                    $round->title ?: '(blank)',
                    $round->short_description ?: '(blank)',
                    $round->description ?: '(blank)',
                    $round->eligible_organisation_types ?: '(blank)',
                    $round->geographic_restrictions ?: '(blank)',
                    $round->eligibility_criteria ?: '(blank)',
                    $round->assessment_criteria ?: '(blank)',
                    is_array($round->key_focus_areas) && count($round->key_focus_areas) ? implode(', ', $round->key_focus_areas) : '(blank)',
                    is_array($round->required_documents) && count($round->required_documents) ? implode(', ', $round->required_documents) : '(blank)',
                    $round->min_funding_amount !== null ? '$' . $round->min_funding_amount : '(blank)',
                    $round->max_funding_amount !== null ? '$' . $round->max_funding_amount : '(blank)',
                    $round->total_funding_pool !== null ? '$' . $round->total_funding_pool : '(blank)',
                    $round->status,
                    $round->is_published ? 'yes' : 'no',
                    $round->opens_at?->toDayDateTimeString() ?? '(not set)',
                    $round->closes_at?->toDayDateTimeString() ?? '(not set)',
                );
            }
        }

        return <<<PROMPT
You are Grantly Assistant, helping an administrator compose a grant round. Be concise (2-4 sentences for advice, longer only when drafting actual copy). Write in clear, Australian English suitable for community organisations applying for funding. Avoid jargon and corporate fluff.

You can help with: drafting titles and descriptions, writing eligibility and assessment criteria, suggesting key focus areas, recommending required documents, and tightening tone. You cannot save changes to the round directly. When you produce a draft, give just the draft, no preface like "Here is a draft:".

Important: you can only see values that have been saved. If the admin says they typed something into the form just now and you don't see it, ask them to paste it into the chat.

$context
PROMPT;
    }

    // Pipes raw OpenRouter SSE chunks straight through. The frontend parses the deltas.
    private function streamCompletion(array $messages): StreamedResponse
    {
        $apiKey = env('OPENROUTER_API_KEY');
        $model  = env('OPENROUTER_MODEL', 'openai/gpt-oss-120b:free');

        return new StreamedResponse(function () use ($messages, $apiKey, $model) {
            $client = new Client(['timeout' => 120]);

            try {
                $response = $client->post('https://openrouter.ai/api/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type'  => 'application/json',
                        'HTTP-Referer'  => config('app.url'),
                        'X-Title'       => 'Grantly',
                    ],
                    'json' => [
                        'model'      => $model,
                        'messages'   => $messages,
                        'stream'     => true,
                        'max_tokens' => 800,
                    ],
                    'stream' => true,
                ]);

                $body = $response->getBody();
                while (! $body->eof()) {
                    echo $body->read(1024);
                    @ob_flush();
                    flush();
                }
            } catch (\Throwable $e) {
                // Emit a final SSE event so the frontend can show an error instead of a stalled stream.
                echo "event: error\ndata: " . json_encode(['message' => $e->getMessage()]) . "\n\n";
                @ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ]);
    }
}
