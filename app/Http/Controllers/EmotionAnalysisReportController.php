<?php

namespace App\Http\Controllers;

use App\Services\Report\EmotionAnalysisReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmotionAnalysisReportController extends Controller
{
    protected EmotionAnalysisReportService $emotionAnalysisReportService;

    public function __construct(EmotionAnalysisReportService $emotionAnalysisReportService)
    {
        $this->emotionAnalysisReportService = $emotionAnalysisReportService;
    }

    /**
     * @OA\Get(
     *      path="/v1.0/reports/emotion-analysis-report",
     *      operationId="getEmotionAnalysisReport",
     *      tags={"Reports"},
     *      summary="Get Emotion Analysis Report",
     *      description="Retrieves AI Acoustic & Linguistic Emotion Radar Profile, emotional distribution across CX categories, dominant emotion KPIs, emotional resonance score, and customer evidence feed.",
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      @OA\Parameter(
     *          name="business_id",
     *          in="query",
     *          required=false,
     *          description="Business ID (defaults to authenticated user business)",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="branch_id",
     *          in="query",
     *          required=false,
     *          description="Branch ID filter",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="survey_id",
     *          in="query",
     *          required=false,
     *          description="Survey ID filter",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="staff_id",
     *          in="query",
     *          required=false,
     *          description="Staff ID filter",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          required=false,
     *          description="Start date (Y-m-d)",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          required=false,
     *          description="End date (Y-m-d)",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *      @OA\Parameter(
     *          name="source",
     *          in="query",
     *          required=false,
     *          description="Source channel filter",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="rating",
     *          in="query",
     *          required=false,
     *          description="Rating score filter (1 to 5)",
     *          @OA\Schema(type="integer", minimum=1, maximum=5)
     *      ),
     *      @OA\Parameter(
     *          name="sentiment",
     *          in="query",
     *          required=false,
     *          description="Sentiment label filter (positive, neutral, negative)",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="emotion",
     *          in="query",
     *          required=false,
     *          description="Primary emotion filter (Happy, Satisfied, Excited, Concerned, Disappointed, Frustrated, Angry)",
     *          @OA\Schema(type="string")
     *      ),
     *      @OA\Parameter(
     *          name="category_id",
     *          in="query",
     *          required=false,
     *          description="Question category ID filter",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          required=false,
     *          description="Customer evidence pagination limit",
     *          @OA\Schema(type="integer", default=6)
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Emotion analysis report retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Emotion analysis report retrieved successfully"),
     *              @OA\Property(property="data", type="object")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation error or missing business ID"
     *      ),
     *      @OA\Response(
     *          response=500,
     *          description="Server Error"
     *      )
     * )
     */
    public function getEmotionAnalysisReport(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'nullable|integer',
            'branch_id'   => 'nullable|integer',
            'survey_id'   => 'nullable|integer',
            'staff_id'    => 'nullable|integer',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'source'      => 'nullable|string',
            'rating'      => 'nullable|integer|min:1|max:5',
            'sentiment'   => 'nullable|string',
            'emotion'     => 'nullable|string',
            'category_id' => 'nullable|integer',
            'per_page'    => 'nullable|integer|min:1|max:100',
        ]);

        $user = $request->user();
        $businessId = $validated['business_id'] ?? ($user ? $user->business_id : null);

        if (!$businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Business ID is required',
            ], 422);
        }

        try {
            $data = $this->emotionAnalysisReportService->getEmotionAnalysisReport((int)$businessId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Emotion analysis report retrieved successfully',
                'data'    => $data,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Emotion analysis report generation failed', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate emotion analysis report',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
