<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoachingHandover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;

class CoachingHandoverController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role === 'client', 403);

        return response()->json(CoachingHandover::where('client_id', $request->user()->id)
            ->select(['id', 'started_on', 'ended_at', 'snapshot'])->orderByDesc('ended_at')->orderByDesc('id')->paginate(12)
            ->through(fn (CoachingHandover $handover) => [
                'id' => $handover->id, 'started_on' => $handover->started_on->toDateString(), 'ended_at' => $handover->ended_at->toISOString(),
                'coach_name' => $handover->snapshot['coach_name'], 'discipline' => $handover->snapshot['discipline'],
                'pdf_url' => '/api/coaching-handovers/'.$handover->id.'/pdf',
            ]));
    }

    public function pdf(Request $request, CoachingHandover $handover): Response
    {
        abort_unless($request->user()?->role === 'client' && $handover->client_id === $request->user()->id, 403);
        $pdf = base64_decode($handover->pdf_base64, true);
        abort_if($pdf === false, 500, 'This handover could not be opened.');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($request->boolean('download') ? 'attachment' : 'inline', 'stage-ready-handover-'.$handover->id.'.pdf'),
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
        ]);
    }
}
