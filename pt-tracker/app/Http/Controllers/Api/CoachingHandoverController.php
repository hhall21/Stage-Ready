<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoachingHandover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class CoachingHandoverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role === 'client', 403);

        return response()->json(CoachingHandover::where('client_id', $request->user()->id)
            ->select(['id', 'started_on', 'ended_at', 'snapshot', 'delete_after_view'])->orderByDesc('ended_at')->orderByDesc('id')->paginate(12)
            ->through(fn (CoachingHandover $handover) => [
                'id' => $handover->id, 'started_on' => $handover->started_on->toDateString(), 'ended_at' => $handover->ended_at->toISOString(),
                'coach_name' => $handover->snapshot['coach_name'], 'discipline' => $handover->snapshot['discipline'],
                'pdf_url' => '/api/coaching-handovers/'.$handover->id.'/pdf',
                'delete_after_view' => $handover->delete_after_view,
            ]));
    }

    public function pdf(Request $request, CoachingHandover $handover): Response
    {
        abort_unless($request->user()?->role === 'client' && $handover->client_id === $request->user()->id, 403);
        abort_if($handover->delete_after_view, 403, 'Open this temporary handover from your Coaching handovers dashboard.');
        $pdf = base64_decode($handover->pdf_base64, true);
        abort_if($pdf === false, 500, 'This handover could not be opened.');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->boolean('download') ? 'attachment' : 'inline', 'stage-ready-handover-'.$handover->id.'.pdf'),
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ]);
    }

    public function open(Request $request, CoachingHandover $handover): JsonResponse
    {
        abort_unless($request->user()?->role === 'client' && $handover->client_id === $request->user()->id && $handover->delete_after_view, 403);
        $token = Str::random(64);
        $handover->forceFill(['opened_at' => now(), 'view_token_hash' => hash('sha256', $token)])->save();

        return response()->json(['pdf_base64' => $handover->pdf_base64, 'view_token' => $token]);
    }

    public function viewed(Request $request, CoachingHandover $handover): Response
    {
        abort_unless($request->user()?->role === 'client' && $handover->client_id === $request->user()->id, 403);
        $data = $request->validate(['view_token' => ['required', 'string', 'size:64']]);
        DB::transaction(function () use ($handover, $data): void {
            $document = CoachingHandover::whereKey($handover->id)->lockForUpdate()->firstOrFail();
            abort_unless($document->delete_after_view && $document->opened_at !== null && $document->view_token_hash !== null
                && hash_equals($document->view_token_hash, hash('sha256', $data['view_token'])), 403);
            $document->delete();
        });

        return response()->noContent();
    }
}
