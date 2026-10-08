<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactEnquiryRequest;
use App\Models\ContactEnquiry;
use Illuminate\Http\JsonResponse;

class ContactEnquiryController extends Controller
{
    public function store(StoreContactEnquiryRequest $request): JsonResponse
    {
        $enquiry = ContactEnquiry::create([
            ...$request->safe()->except(['privacy_consent', 'website']),
            'status' => 'new',
            'privacy_consent_at' => now(),
        ]);

        return response()->json([
            'message' => 'Thanks — your enquiry has been received.',
            'data' => [
                'id' => $enquiry->id,
                'status' => $enquiry->status,
                'created_at' => $enquiry->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
