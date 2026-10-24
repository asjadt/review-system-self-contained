<?php

namespace App\Http\Controllers;

use App\Services\Report\NegativeFeedbackReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NegativeFeedbackReportController extends Controller
{
    protected NegativeFeedbackReportService $negativeFeedbackReportService;

    public function __construct(NegativeFeedbackReportService $negativeFeedbackReportService)
    {
        $this->negativeFeedbackReportService = $negativeFeedbackReportService;
    }

    /**
     * @OA\Get(
     *      path="/v1.0/reports/negative-feedback-report",
     *      operationId="getNegativeFeedbackReport",
     *      tags={"Reports"},
     *      summary="Get Negative Feedback Report",
     *      description="Retrieves negative feedback root cause distribution donut, complaints by branch/unit bar chart, recovery rate KPIs, and customer evidence feed.",
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
     *          description="Negative feedback report retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Negative feedback report retrieved successfully"),
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
    public function getNegativeFeedbackReport(Request $request)
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
            $data = $this->negativeFeedbackReportService->getNegativeFeedbackReport((int)$businessId, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Negative feedback report retrieved successfully',
                'data'    => $data,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Negative feedback report generation failed', [
                'business_id' => $businessId,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate negative feedback report',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
