<?php

namespace App\Services\Report;

use App\Models\Branch;
use App\Models\BusinessAiSummary;
use App\Models\ReviewNew;
use App\Services\Report\Traits\ReportAnalyticsTrait;
use App\Services\Review\ReviewMetricsService;
use App\Services\Rule\RuleEngineService;
use App\Services\Rule\RuleReportService;
use App\Services\Staff\StaffPerformanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SentimentAnalysisReportService
{
    use ReportAnalyticsTrait;

    protected RuleReportService $ruleReportService;
    protected StaffPerformanceService $staffPerformanceService;
    protected ReviewMetricsService $reviewMetricsService;

    public function __construct(
        RuleReportService $ruleReportService,
        StaffPerformanceService $staffPerformanceService,
        ReviewMetricsService $reviewMetricsService
    ) {
        $this->ruleReportService = $ruleReportService;
        $this->staffPerformanceService = $staffPerformanceService;
        $this->reviewMetricsService = $reviewMetricsService;
    }

    /**
     * Get complete Sentiment Analysis Report payload matching Frontend UI schema
     */
    public function getSentimentAnalysisReport(int $businessId, array $filters = []): array
    {
        $startDate = !empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();

        $endDate = !empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : Carbon::now()->endOfDay();

        // Base review query scoped by business and filters
        $query = ReviewNew::where('review_news.business_id', $businessId)
            ->whereBetween('review_news.created_at', [$startDate, $endDate]);

        if (!empty($filters['branch_id'])) {
            $query->where('review_news.branch_id', $filters['branch_id']);
        }

        if (!empty($filters['survey_id'])) {
            $query->where('review_news.survey_id', $filters['survey_id']);
        }

        if (!empty($filters['source'])) {
            $query->where('review_news.source', $filters['source']);
        }

        if (!empty($filters['sentiment'])) {
            $query->where('review_news.sentiment_label', strtolower($filters['sentiment']));
        }

        if (!empty($filters['staff_id'])) {
            $query->where('review_news.staff_id', $filters['staff_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->whereExists(function ($subQuery) use ($filters) {
                $subQuery->select(DB::raw(1))
                    ->from('review_value_news as rvn')
                    ->join('q_q_sub_categories as qqsc', 'qqsc.question_id', '=', 'rvn.question_id')
                    ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                    ->join('question_categories as qc_parent', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
                    ->whereColumn('rvn.review_id', 'review_news.id')
                    ->where('qc_parent.id', $filters['category_id']);
            });
        }

        if (!empty($filters['rating'])) {
            $ratingVal = (int) $filters['rating'];
            $query->whereExists(function ($subQuery) use ($ratingVal) {
                $subQuery->select(DB::raw(1))
                    ->from('review_value_news as rvn_r')
                    ->join('stars as s_r', 'rvn_r.star_id', '=', 's_r.id')
                    ->whereColumn('rvn_r.review_id', 'review_news.id')
                    ->groupBy('rvn_r.review_id')
                    ->havingRaw('ROUND(AVG(s_r.value), 1) = ?', [$ratingVal]);
            });
        }

        $reviews = (clone $query)->withCalculatedRating()->get();
        $totalReviews = $reviews->count();

        // 1. Sentiment counts & score using ReviewMetricsService & RuleEngine thresholds
        $sentimentMetrics = $this->calculateSentimentBreakdownDetailed($reviews);
        $posCount = $sentimentMetrics['positive'];
        $neuCount = $sentimentMetrics['neutral'];
        $negCount = $sentimentMetrics['negative'];

        $posPct = $totalReviews > 0 ? round(($posCount / $totalReviews) * 100, 1) : 0.0;
        $neuPct = $totalReviews > 0 ? round(($neuCount / $totalReviews) * 100, 1) : 0.0;
        $negPct = $totalReviews > 0 ? round(($negCount / $totalReviews) * 100, 1) : 0.0;

        // Sentiment Index Score: Net Sentiment = (% Positive - % Negative) on scale -100 to +100
        $sentimentIndex = round($posPct - $negPct, 1);
        $sentimentIndexStr = ($sentimentIndex > 0 ? '+' : '') . number_format($sentimentIndex, 1);

        // Comparison period metrics
        $diffInDays = $startDate->diffInDays($endDate) + 1;
        $prevStartDate = (clone $startDate)->subDays($diffInDays);
        $prevEndDate = (clone $startDate)->subSecond();

        $prevQuery = ReviewNew::where('review_news.business_id', $businessId)
            ->whereBetween('review_news.created_at', [$prevStartDate, $prevEndDate]);

        if (!empty($filters['branch_id'])) {
            $prevQuery->where('review_news.branch_id', $filters['branch_id']);
        }
        if (!empty($filters['survey_id'])) {
            $prevQuery->where('review_news.survey_id', $filters['survey_id']);
        }
        if (!empty($filters['source'])) {
            $prevQuery->where('review_news.source', $filters['source']);
        }
        if (!empty($filters['sentiment'])) {
            $prevQuery->where('review_news.sentiment_label', strtolower($filters['sentiment']));
        }
        if (!empty($filters['staff_id'])) {
            $prevQuery->where('review_news.staff_id', $filters['staff_id']);
        }
        if (!empty($filters['category_id'])) {
            $prevQuery->whereExists(function ($subQuery) use ($filters) {
                $subQuery->select(DB::raw(1))
                    ->from('review_value_news as rvn')
                    ->join('q_q_sub_categories as qqsc', 'qqsc.question_id', '=', 'rvn.question_id')
                    ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                    ->join('question_categories as qc_parent', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
                    ->whereColumn('rvn.review_id', 'review_news.id')
                    ->where('qc_parent.id', $filters['category_id']);
            });
        }
        if (!empty($filters['rating'])) {
            $ratingVal = (int) $filters['rating'];
            $prevQuery->whereExists(function ($subQuery) use ($ratingVal) {
                $subQuery->select(DB::raw(1))
                    ->from('review_value_news as rvn_r')
                    ->join('stars as s_r', 'rvn_r.star_id', '=', 's_r.id')
                    ->whereColumn('rvn_r.review_id', 'review_news.id')
                    ->groupBy('rvn_r.review_id')
                    ->havingRaw('ROUND(AVG(s_r.value), 1) = ?', [$ratingVal]);
            });
        }

        $prevReviews = $prevQuery->get();
        $prevTotal = $prevReviews->count();
        $prevSentimentMetrics = $this->calculateSentimentBreakdownDetailed($prevReviews);

        $prevPosPct = $prevTotal > 0 ? round(($prevSentimentMetrics['positive'] / $prevTotal) * 100, 1) : 0.0;
        $prevNeuPct = $prevTotal > 0 ? round(($prevSentimentMetrics['neutral'] / $prevTotal) * 100, 1) : 0.0;
        $prevNegPct = $prevTotal > 0 ? round(($prevSentimentMetrics['negative'] / $prevTotal) * 100, 1) : 0.0;
        $prevSentimentIndex = round($prevPosPct - $prevNegPct, 1);

        $posChange = round($posPct - $prevPosPct, 1);
        $posChangeStr = ($posChange >= 0 ? '+' : '') . $posChange . '%';

        $neuChange = round($neuPct - $prevNeuPct, 1);
        $neuChangeStr = ($neuChange >= 0 ? '+' : '') . $neuChange . '%';

        $negChange = round($negPct - $prevNegPct, 1);
        $negChangeStr = ($negChange >= 0 ? '+' : '') . $negChange . '%';

        $indexChange = round($sentimentIndex - $prevSentimentIndex, 1);
        $indexChangeStr = ($indexChange >= 0 ? '+' : '') . $indexChange . ' pts';

        $kpis = [
            [
                'label' => 'Positive Sentiment',
                'value' => $posPct . '%',
                'change' => $posChangeStr,
                'isPositive' => $posChange >= 0,
                'subtext' => number_format($posCount) . ' positive mentions',
            ],
            [
                'label' => 'Neutral Sentiment',
                'value' => $neuPct . '%',
                'change' => $neuChangeStr,
                'isPositive' => $neuChange <= 0, // decrease in neutral/negative is positive
                'subtext' => number_format($neuCount) . ' neutral mentions',
            ],
            [
                'label' => 'Negative Sentiment',
                'value' => $negPct . '%',
                'change' => $negChangeStr,
                'isPositive' => $negChange <= 0, // negative reduction is positive
                'subtext' => number_format($negCount) . ' negative mentions',
            ],
            [
                'label' => 'Sentiment Index Score',
                'value' => $sentimentIndexStr,
                'change' => $indexChangeStr,
                'isPositive' => $indexChange >= 0,
                'subtext' => 'Scale: -100 to +100',
            ],
        ];

        // 2. Main Visual: 100% Stacked Sentiment Distribution Over Time
        $sentimentOverTime = $this->getSentimentDistributionOverTime($businessId, $startDate, $endDate, $filters);

        // 3. Breakdown Visual 1: Sentiment Breakdown by Branch
        $sentimentByBranch = $this->getSentimentBreakdownByBranch($businessId, $startDate, $endDate, $filters);

        // 4. Breakdown Visual 2: Sentiment Breakdown by Category
        $sentimentByCategory = $this->getSentimentBreakdownByCategory($businessId, $startDate, $endDate, $filters);

        // 5. AI Summary Briefing
        $aiSummary = $this->getSentimentAiBriefing($businessId, $reviews, $posPct, $sentimentByCategory, $sentimentOverTime);

        // 6. Key Insights
        $keyInsights = $this->getSentimentKeyInsights($sentimentByCategory, $sentimentByBranch, $posPct);

        // 7. Recommended Actions
        $recommendedActions = $this->getSentimentRecommendedActions($sentimentByCategory, $sentimentByBranch, $negCount);

        // 8. Customer Evidence Rows
        $evidenceRows = $this->getSentimentEvidenceRows($query, $filters);

        return [
            'title' => 'Sentiment Analysis Report',
            'description' => 'Analyse how customers feel about the business and how sentiment changes over time.',
            'kpis' => $kpis,
            'sentimentOverTime' => $sentimentOverTime,
            'sentimentByBranch' => $sentimentByBranch,
            'sentimentByCategory' => $sentimentByCategory,
            'aiSummary' => $aiSummary,
            'keyInsights' => $keyInsights,
            'recommendedActions' => $recommendedActions,
            'evidenceRows' => $evidenceRows,
            'meta' => [
                'report_title' => 'Sentiment Analysis Report',
                'version' => 'Live V2',
                'period' => [
                    'start_date' => $startDate->toIso8601String(),
                    'end_date' => $endDate->toIso8601String(),
                    'days_count' => $diffInDays,
                ],
                'total_reviews' => $totalReviews,
                'sentiment_index' => $sentimentIndex,
                'filters_applied' => array_filter($filters),
            ],
        ];
    }

    /**
     * Compute positive, neutral, negative breakdown with robust fallback
     */
    protected function calculateSentimentBreakdownDetailed($reviews): array
    {
        $posThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $pos = 0;
        $neu = 0;
        $neg = 0;

        foreach ($reviews as $r) {
            $label = strtolower(trim($r->sentiment_label ?? ''));
            if ($label === 'positive' || $label === 'very_positive') {
                $pos++;
            } elseif ($label === 'negative' || $label === 'very_negative') {
                $neg++;
            } elseif ($label === 'neutral') {
                $neu++;
            } elseif (isset($r->sentiment_score) && is_numeric($r->sentiment_score)) {
                $score = (float) $r->sentiment_score;
                if ($score >= $posThreshold) {
                    $pos++;
                } elseif ($score < $negThreshold) {
                    $neg++;
                } else {
                    $neu++;
                }
            } else {
                $rating = (float) ($r->calculated_rating ?? 5.0);
                if ($rating >= 4.0) {
                    $pos++;
                } elseif ($rating <= 2.0) {
                    $neg++;
                } else {
                    $neu++;
                }
            }
        }

        return [
            'positive' => $pos,
            'neutral'  => $neu,
            'negative' => $neg,
            'total'    => count($reviews),
        ];
    }

    /**
     * Calculate 100% Stacked Sentiment Distribution Over Time
     */
    protected function getSentimentDistributionOverTime(int $businessId, Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $diffDays = $startDate->diffInDays($endDate);

        // Group into Weeks if 15 to 90 days, Months if > 90 days, or Days if <= 14 days
        if ($diffDays > 90) {
            $format = '%Y-%m';
            $labelType = 'month';
        } elseif ($diffDays >= 14) {
            $format = '%x-W%v'; // ISO year & week
            $labelType = 'week';
        } else {
            $format = '%Y-%m-%d';
            $labelType = 'day';
        }

        $query = ReviewNew::where('business_id', $businessId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (!empty($filters['survey_id'])) {
            $query->where('survey_id', $filters['survey_id']);
        }
        if (!empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }
        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }

        $posThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $rows = (clone $query)
            ->select([
                DB::raw("DATE_FORMAT(created_at, '{$format}') as bucket_key"),
                DB::raw("MIN(created_at) as min_date"),
                DB::raw("COUNT(id) as total_count"),
                DB::raw("SUM(CASE WHEN sentiment_label IN ('positive', 'very_positive') OR (sentiment_label IS NULL AND sentiment_score >= {$posThreshold}) THEN 1 ELSE 0 END) as positive_count"),
                DB::raw("SUM(CASE WHEN sentiment_label IN ('negative', 'very_negative') OR (sentiment_label IS NULL AND sentiment_score < {$negThreshold}) THEN 1 ELSE 0 END) as negative_count"),
                DB::raw("SUM(CASE WHEN sentiment_label = 'neutral' OR (sentiment_label IS NULL AND sentiment_score >= {$negThreshold} AND sentiment_score < {$posThreshold}) THEN 1 ELSE 0 END) as neutral_count"),
            ])
            ->groupBy('bucket_key')
            ->orderBy('min_date', 'asc')
            ->get();

        $series = [];
        $weekIndex = 1;

        foreach ($rows as $row) {
            $total = (int) $row->total_count;
            $pos = (int) $row->positive_count;
            $neg = (int) $row->negative_count;
            $neu = (int) $row->neutral_count;

            if ($total > 0) {
                $posPct = (int) round(($pos / $total) * 100);
                $negPct = (int) round(($neg / $total) * 100);
                // Ensure 100% stacked total
                $neuPct = max(0, 100 - $posPct - $negPct);
            } else {
                $posPct = 0;
                $neuPct = 100;
                $negPct = 0;
            }

            if ($labelType === 'week') {
                $periodLabel = "Week {$weekIndex}";
                $weekIndex++;
            } elseif ($labelType === 'month') {
                $periodLabel = Carbon::parse($row->min_date)->format('M Y');
            } else {
                $periodLabel = Carbon::parse($row->min_date)->format('M d');
            }

            $series[] = [
                'period'   => $periodLabel,
                'Positive' => $posPct,
                'Neutral'  => $neuPct,
                'Negative' => $negPct,
            ];
        }

        // If no data exists, return fallback friendly empty periods
        if (empty($series)) {
            return [
                ['period' => 'Week 1', 'Positive' => 0, 'Neutral' => 100, 'Negative' => 0],
            ];
        }

        return $series;
    }

    /**
     * Compute Sentiment Breakdown by Branch (horizontal stacked bar charts)
     */
    protected function getSentimentBreakdownByBranch(int $businessId, Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $branches = Branch::where('business_id', $businessId)->get();

        $posThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $results = [];

        foreach ($branches as $branch) {
            $query = ReviewNew::where('business_id', $businessId)
                ->where('branch_id', $branch->id)
                ->whereBetween('created_at', [$startDate, $endDate]);

            if (!empty($filters['survey_id'])) {
                $query->where('survey_id', $filters['survey_id']);
            }
            if (!empty($filters['source'])) {
                $query->where('source', $filters['source']);
            }

            $data = (clone $query)
                ->select([
                    DB::raw("COUNT(id) as total_count"),
                    DB::raw("SUM(CASE WHEN sentiment_label IN ('positive', 'very_positive') OR (sentiment_label IS NULL AND sentiment_score >= {$posThreshold}) THEN 1 ELSE 0 END) as positive_count"),
                    DB::raw("SUM(CASE WHEN sentiment_label IN ('negative', 'very_negative') OR (sentiment_label IS NULL AND sentiment_score < {$negThreshold}) THEN 1 ELSE 0 END) as negative_count"),
                    DB::raw("SUM(CASE WHEN sentiment_label = 'neutral' OR (sentiment_label IS NULL AND sentiment_score >= {$negThreshold} AND sentiment_score < {$posThreshold}) THEN 1 ELSE 0 END) as neutral_count"),
                ])
                ->first();

            $total = (int) ($data->total_count ?? 0);
            $pos = (int) ($data->positive_count ?? 0);
            $neg = (int) ($data->negative_count ?? 0);

            if ($total > 0) {
                $posPct = (int) round(($pos / $total) * 100);
                $negPct = (int) round(($neg / $total) * 100);
                $neuPct = max(0, 100 - $posPct - $negPct);
            } else {
                $posPct = 0;
                $neuPct = 0;
                $negPct = 0;
            }

            $results[] = [
                'name'     => $branch->name,
                'positive' => $posPct,
                'neutral'  => $neuPct,
                'negative' => $negPct,
                'volume'   => $total,
            ];
        }

        // Sort by positive % desc
        usort($results, fn($a, $b) => $b['positive'] <=> $a['positive']);

        // Return up to 6 branches
        return array_slice($results, 0, 6);
    }

    /**
     * Compute Sentiment Breakdown by Question Category
     */
    protected function getSentimentBreakdownByCategory(int $businessId, Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $highRatingThreshold = RuleEngineService::getHighRatingThreshold();
        $lowRatingThreshold = RuleEngineService::getLowRatingThreshold();

        $categories = DB::table('question_categories as qc_parent')
            ->join('question_categories as qc_sub', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
            ->join('q_q_sub_categories as qqsc', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
            ->join('review_value_news as rvn', 'rvn.question_id', '=', 'qqsc.question_id')
            ->join('review_news as r', 'r.id', '=', 'rvn.review_id')
            ->join('stars as s', 's.id', '=', 'rvn.star_id')
            ->where('r.business_id', $businessId)
            ->whereBetween('r.created_at', [$startDate, $endDate])
            ->when(!empty($filters['branch_id']), fn($q) => $q->where('r.branch_id', $filters['branch_id']))
            ->when(!empty($filters['survey_id']), fn($q) => $q->where('r.survey_id', $filters['survey_id']))
            ->when(!empty($filters['source']), fn($q) => $q->where('r.source', $filters['source']))
            ->select([
                'qc_parent.title as name',
                DB::raw("COUNT(rvn.id) as total_responses"),
                DB::raw("SUM(CASE WHEN s.value >= {$highRatingThreshold} THEN 1 ELSE 0 END) as positive_count"),
                DB::raw("SUM(CASE WHEN s.value <= {$lowRatingThreshold} THEN 1 ELSE 0 END) as negative_count"),
                DB::raw("SUM(CASE WHEN s.value > {$lowRatingThreshold} AND s.value < {$highRatingThreshold} THEN 1 ELSE 0 END) as neutral_count"),
            ])
            ->groupBy('qc_parent.id', 'qc_parent.title')
            ->orderByRaw("COUNT(rvn.id) DESC")
            ->take(6)
            ->get();

        $results = [];
        foreach ($categories as $cat) {
            $total = (int) $cat->total_responses;
            $pos = (int) $cat->positive_count;
            $neg = (int) $cat->negative_count;

            if ($total > 0) {
                $posPct = (int) round(($pos / $total) * 100);
                $negPct = (int) round(($neg / $total) * 100);
                $neuPct = max(0, 100 - $posPct - $negPct);
            } else {
                $posPct = 0;
                $neuPct = 0;
                $negPct = 0;
            }

            $results[] = [
                'name'     => $cat->name,
                'positive' => $posPct,
                'neutral'  => $neuPct,
                'negative' => $negPct,
                'volume'   => $total,
            ];
        }

        // Default categories if empty
        if (empty($results)) {
            $defaultCategories = ['Staff & Hospitality', 'Food Quality', 'Ambiance', 'Value & Pricing', 'Speed & Wait Times'];
            foreach ($defaultCategories as $defCat) {
                $results[] = [
                    'name'     => $defCat,
                    'positive' => 0,
                    'neutral'  => 0,
                    'negative' => 0,
                    'volume'   => 0,
                ];
            }
        }

        return $results;
    }

    /**
     * AI Summary Briefing for Sentiment
     */
    protected function getSentimentAiBriefing(int $businessId, $reviews, float $posPct, array $sentimentByCategory, array $sentimentOverTime): array
    {
        $aiSummaryRecord = BusinessAiSummary::where('business_id', $businessId)
            ->orderBy('created_at', 'desc')
            ->first();

        $total = $reviews->count();

        // Calculate highest negative category
        $worstCategory = null;
        foreach ($sentimentByCategory as $cat) {
            if ($cat['negative'] > 0 && ($worstCategory === null || $cat['negative'] > $worstCategory['negative'])) {
                $worstCategory = $cat;
            }
        }

        $lastPeriod = end($sentimentOverTime);
        $lastPeriodLabel = $lastPeriod['period'] ?? 'recent period';
        $lastPeriodPos = $lastPeriod['Positive'] ?? $posPct;

        $title = $total > 0
            ? "Positive sentiment reached {$lastPeriodPos}% in {$lastPeriodLabel}"
            : "Sentiment Analysis (No reviews recorded for period)";

        $negativeMentionText = $worstCategory
            ? "Negative sentiment is concentrated in the \"{$worstCategory['name']}\" category ({$worstCategory['negative']}% negative)."
            : "Negative sentiment remains low across all monitored categories.";

        $text = $total > 0
            ? "Upward sentiment momentum was driven by high customer engagement. {$negativeMentionText}"
            : "No customer sentiment telemetry recorded in the selected date range.";

        if ($aiSummaryRecord && !empty($aiSummaryRecord->summary)) {
            return [
                'badge' => 'Sentiment Trends',
                'title' => $title,
                'text'  => $aiSummaryRecord->summary,
            ];
        }

        return [
            'badge' => 'Sentiment Trends',
            'title' => $title,
            'text'  => $text,
        ];
    }

    /**
     * Key Insights: Category Leader & Friction Point
     */
    protected function getSentimentKeyInsights(array $sentimentByCategory, array $sentimentByBranch, float $posPct): array
    {
        // Category Leader: category with highest positive %
        $leader = null;
        foreach ($sentimentByCategory as $cat) {
            if ($cat['volume'] > 0 && ($leader === null || $cat['positive'] > $leader['positive'])) {
                $leader = $cat;
            }
        }

        // Friction Point: category or branch with highest negative %
        $friction = null;
        foreach ($sentimentByCategory as $cat) {
            if ($cat['volume'] > 0 && ($friction === null || $cat['negative'] > $friction['negative'])) {
                $friction = $cat;
            }
        }

        $leaderText = $leader && $leader['positive'] > 0
            ? "{$leader['name']} is the top emotional driver, generating {$leader['positive']}% positive customer sentiment."
            : "Overall customer sentiment is positive across primary service touchpoints.";

        $frictionText = $friction && $friction['negative'] > 0
            ? "Customer friction observed in {$friction['name']} with {$friction['negative']}% negative sentiment mentions."
            : "Minimal friction points detected during the selected date range.";

        return [
            [
                'type' => 'Category Leader',
                'text' => $leaderText,
            ],
            [
                'type' => 'Friction Point',
                'text' => $frictionText,
            ],
        ];
    }

    /**
     * Recommended Actions
     */
    protected function getSentimentRecommendedActions(array $sentimentByCategory, array $sentimentByBranch, int $negCount): array
    {
        $actions = [];

        // Identify category with highest negative %
        $topNegativeCat = null;
        foreach ($sentimentByCategory as $cat) {
            if ($cat['negative'] > 0 && ($topNegativeCat === null || $cat['negative'] > $topNegativeCat['negative'])) {
                $topNegativeCat = $cat;
            }
        }

        // Identify branch with lowest positive %
        $needsFocusBranch = null;
        foreach ($sentimentByBranch as $branch) {
            if ($branch['volume'] > 0 && ($needsFocusBranch === null || $branch['positive'] < $needsFocusBranch['positive'])) {
                $needsFocusBranch = $branch;
            }
        }

        if ($topNegativeCat && $topNegativeCat['negative'] > 0) {
            $actions[] = [
                'priority' => 'High',
                'title'    => "Address {$topNegativeCat['name']} Pain Points",
                'desc'     => "Negative sentiment in {$topNegativeCat['name']} reached {$topNegativeCat['negative']}%. Audit operational processes and staff guidance.",
            ];
        } else {
            $actions[] = [
                'priority' => 'High',
                'title'    => 'Maintain Positive Experience Standards',
                'desc'     => 'Customer sentiment is performing solidly. Continue reinforcing attentive guest service routines.',
            ];
        }

        if ($needsFocusBranch && $needsFocusBranch['positive'] < 75 && $needsFocusBranch['volume'] > 0) {
            $actions[] = [
                'priority' => 'Medium',
                'title'    => "{$needsFocusBranch['name']} Service Coaching",
                'desc'     => "Location {$needsFocusBranch['name']} recorded {$needsFocusBranch['positive']}% positive sentiment. Provide targeted customer care coaching.",
            ];
        } else {
            $actions[] = [
                'priority' => 'Medium',
                'title'    => 'Optimize High-Traffic Service Windows',
                'desc'     => 'Focus customer service teams on peak hours to reduce wait time dissatisfaction and maintain sentiment.',
            ];
        }

        return array_slice($actions, 0, 2);
    }

    /**
     * Customer Evidence Rows matching { date, branch, sentiment, category, comment }
     */
    protected function getSentimentEvidenceRows($query, array $filters): array
    {
        $limit = !empty($filters['per_page']) ? (int) $filters['per_page'] : 15;
        $reviews = (clone $query)
            ->with(['branch'])
            ->withCalculatedRating()
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();

        $reviewIds = $reviews->pluck('id')->toArray();
        $categoriesMap = [];

        if (!empty($reviewIds)) {
            $catRows = DB::table('review_value_news as rvn')
                ->join('q_q_sub_categories as qqsc', 'qqsc.question_id', '=', 'rvn.question_id')
                ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                ->join('question_categories as qc_parent', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
                ->whereIn('rvn.review_id', $reviewIds)
                ->select('rvn.review_id', 'qc_parent.title')
                ->get();

            foreach ($catRows as $crow) {
                if (!isset($categoriesMap[$crow->review_id])) {
                    $categoriesMap[$crow->review_id] = $crow->title;
                }
            }
        }

        $items = [];
        foreach ($reviews as $r) {
            $branchName = $r->branch->name ?? 'General Branch';
            $ratingVal = (int) round($r->calculated_rating ?? 5);

            // Sentiment label resolution
            $sentimentLabel = $r->sentiment_label;
            if (!$sentimentLabel && isset($r->sentiment_score)) {
                $sentimentLabel = RuleEngineService::getSentimentLabelFromScore((float) $r->sentiment_score);
            }
            if (!$sentimentLabel) {
                $sentimentLabel = $ratingVal >= 4 ? 'positive' : ($ratingVal <= 2 ? 'negative' : 'neutral');
            }

            $catName = $categoriesMap[$r->id] ?? 'General Feedback';

            $items[] = [
                'date'      => $r->created_at->format('Y-m-d'),
                'branch'    => $branchName,
                'sentiment' => ucfirst($sentimentLabel),
                'category'  => $catName,
                'comment'   => $r->comment ?? $r->description ?? 'No customer comment provided.',
            ];
        }

        return $items;
    }
}
