<?php

namespace App\Models;

use App\Services\Rule\RuleEngineService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use App\Models\InsightRecord;
use Carbon\Carbon;

class ReviewNew extends Model
{
    use HasFactory;

    const PLATFORM_WEB = 'web';
    const PLATFORM_APP = 'app';

    protected $fillable = [

        'survey_id',
        'description',
        'business_id',
        'user_id',
        'comment',
        'guest_id',
        'raw_text',
        "ip_address",
        "is_overall",
        'staff_id',
        'order_no',
        "status",
        'responded_at',
        'review_type',
        'reply_content',
        'is_voice_review',
        'voice_url',
        'voice_duration',
        'transcription_metadata',
        'is_private',
        "branch_id",

        "is_ai_processed",
        'sentiment_score',
        'emotion',
        'key_phrases',
        'topics',
        'moderation_results',
        'ai_suggestions',
        'staff_suggestions',
        'language',

        "ai_confidence",
        "sentiment_label",
        'openai_raw_response',
        "is_abusive",
        "summary",
        'source',
        "rating_comment_mismatch",
        'ai_processed_at',
        'ai_model',
        'is_rolling_aggregated',
    ];

    protected $casts = [
        'is_ai_processed' => 'boolean',
        'is_voice_review' => 'boolean',
        'is_private' => 'boolean',
        'is_overall' => 'boolean',
        'is_abusive' => 'boolean',
        'rating_comment_mismatch' => 'boolean',
        'is_rolling_aggregated' => 'boolean',
        'sentiment_score' => 'float',
        'ai_insights' => 'array',
        'ai_recommendations' => 'array',
        'topics' => 'array',
        'service_analysis' => 'array',
        'openai_raw_response' => 'array',
        'mismatch_insights' => 'array',
        'ai_processed_at' => 'datetime'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($review) {
            if (!$review->source) {
                $review->source = 'direct';
            }
        });
    }

    public function getSentimentLabelAttribute($value)
    {
        // If the database already has a label (from AI processing), use it
        if ($value) {
            return $value;
        }

        // Fallback to threshold calculation if not processed yet or missing label
        $score = (float) ($this->sentiment_score ?? 0.5);
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        if ($score >= $positiveThreshold) {
            return 'positive';
        } elseif ($score < $negativeThreshold) {
            return 'negative';
        }

        return 'neutral';
    }

    /**
     * Map old moderation logic to new schema for consistent API response
     */
    public function getModerationResultsAttribute($value)
    {
        if ($value) {
            return is_string($value) ? json_decode($value, true) : $value;
        }

        return [
            'is_abusive' => (bool) ($this->is_abusive ?? false),
            'flagged' => (bool) ($this->is_abusive ?? false),
            'confidence' => (float) ($this->ai_confidence ?? 0.0)
        ];
    }

    public function getVoiceUrlAttribute($value)
    {
        if (!$value) return null;
        // Use proxy or direct link depending on environment
        return asset("storage-proxy/business_{$this->business->OwnerID}/business_{$this->business_id}/voice-reviews/{$value}");
    }

    /**
     * Relationship with business_services (many-to-many through pivot)
     */
    public function business_services()
    {
        return $this->belongsToMany(
            BusinessService::class,
            'review_business_services', // pivot table
            'review_id',                // foreign key on pivot table
            'business_service_id',       // related key on pivot table
        )
            ->withPivot('business_area_id') // include business_area_id from pivot
            ->withTimestamps();
    }




    public function value()
    {
        return $this->hasMany(ReviewValueNew::class, 'review_id', 'id');
    }

    public function business()
    {
        return $this->hasOne(Business::class, 'id', 'business_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function staff()
    {
        return $this->hasOne(User::class, 'id', 'staff_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    public function guest_user()
    {
        return $this->hasOne(GuestUser::class, 'id', 'guest_id');
    }

    public function survey()
    {
        return $this->belongsTo(Survey::class);
    }

    // ReviewNews.php (Model)

    public function review_values()
    {
        return $this->hasMany(ReviewValueNew::class, 'review_id');
    }

    /**
     * Relationship with granular rule outcomes
     */
    public function rule_outcomes()
    {
        return $this->hasOne(ReviewRuleOutcome::class, 'review_id');
    }

    /**
     * Accessor for is_flagged (mapped from rule_outcomes)
     */
    public function getIsFlaggedAttribute(): bool
    {
        return (bool) ($this->rule_outcomes->is_flagged ?? false);
    }






    /**
     * Add calculated rating to review query
     */
    public function scopeWithCalculatedRating($query)
    {
        return $query->selectRaw('
            review_news.*,
            (
                SELECT ROUND(AVG(s.value), 1)
                FROM review_value_news rvn
                INNER JOIN stars s ON rvn.star_id = s.id
                WHERE rvn.review_id = review_news.id
            ) as calculated_rating
        ');
    }

    public function getCalculatedRatingAttribute()
    {
        if (array_key_exists('calculated_rating', $this->attributes)) {
            $value = $this->attributes['calculated_rating'];
            return $value === null ? null : round((float) $value, 1);
        }

        $average = $this->review_values()
            ->join('stars', 'review_value_news.star_id', '=', 'stars.id')
            ->avg('stars.value');

        return $average === null ? null : round((float) $average, 1);
    }



    public function scopeWhereMeetsThreshold($query, $is_staff_review = 0)
    {
        // Fallback global threshold if business column is null
        $globalThreshold = (float) config('ai.sentiment.thresholds.csat', 4.0);

        return $query->whereExists(function ($subQuery) use ($globalThreshold, $is_staff_review) {
            $subQuery->select(DB::raw(1))
                ->from('review_value_news as rvn')
                ->join('questions as q', 'rvn.question_id', '=', 'q.id')
                // Join businesses to access the threshold_rating column
                ->join('businesses as b', 'review_news.business_id', '=', 'b.id')

                ->when((request()->has('staff_id') || $is_staff_review), function ($q) {
                    $q->whereExists(function ($categoryQuery) {
                        $categoryQuery->select(DB::raw(1))
                            ->from('q_q_sub_categories as qqsc')
                            ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                            ->join('question_categories as qc_parent', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
                            ->whereColumn('qqsc.question_id', 'q.id')
                            ->where('qc_parent.title', 'Staff')
                            ->where('qc_parent.is_active', 1)
                            ->where('qc_parent.is_default', 1)
                            ->whereNull('qc_parent.business_id')
                            ->where('qc_sub.is_active', 1);
                    });
                })

                ->join('stars as s', 'rvn.star_id', '=', 's.id')
                ->whereColumn('rvn.review_id', 'review_news.id')
                ->groupBy('rvn.review_id', 'b.threshold_rating')
                /* We use COALESCE so if threshold_rating is NULL,
               it defaults to your global config value.
            */
                ->havingRaw('AVG(s.value) >= COALESCE(b.threshold_rating, ?)', [$globalThreshold]);
        });
    }


    public function scopeWhereDoesNotMeetsThreshold($query, $is_staff_review = 0)
    {
        // Fallback global threshold if business column is null
        $globalThreshold = (float) config('ai.sentiment.thresholds.csat', 4.0);

        return $query->whereExists(function ($subQuery) use ($globalThreshold, $is_staff_review) {
            $subQuery->select(DB::raw(1))
                ->from('review_value_news as rvn')
                ->join('questions as q', 'rvn.question_id', '=', 'q.id')
                ->join('businesses as b', 'review_news.business_id', '=', 'b.id')

                ->when((request()->has('staff_id') || $is_staff_review), function ($q) {
                    $q->whereExists(function ($categoryQuery) {
                        $categoryQuery->select(DB::raw(1))
                            ->from('q_q_sub_categories as qqsc')
                            ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                            ->join('question_categories as qc_parent', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
                            ->whereColumn('qqsc.question_id', 'q.id')
                            ->where('qc_parent.title', 'Staff')
                            ->where('qc_parent.is_active', 1)
                            ->where('qc_parent.is_default', 1)
                            ->whereNull('qc_parent.business_id')
                            ->where('qc_sub.is_active', 1);
                    });
                })

                ->join('stars as s', 'rvn.star_id', '=', 's.id')
                ->whereColumn('rvn.review_id', 'review_news.id')
                ->groupBy('rvn.review_id', 'b.threshold_rating')
                ->havingRaw('AVG(s.value) < COALESCE(b.threshold_rating, ?)', [$globalThreshold]);
        });
    }

    // In your QuestionValue model (or the model that has 'value' relation)
    public function scopeFilterByOverall($query, $is_overall)
    {
        return $query->where('is_overall', $is_overall ? 1 : 0);
    }


    public function isVoiceReview()
    {
        return $this->is_voice_review;
    }




    public function scopeFilterByDateRange($query, bool $isComparisonDateRange = false)
    {
        if ($isComparisonDateRange) {
            $query->when(request()->filled('period'), function ($query) {
                $dateRange = getDateRangeByPeriod(request()->input('period'));
                if ($dateRange) {
                    $startDate = $dateRange['start']->subDays($dateRange['daysOffset'])->startOfDay();
                    $endDate = $dateRange['end']->subDays($dateRange['daysOffset'])->endOfDay();
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }
            });
        } else {
            if (request()->filled('start_date') || request()->filled('end_date')) {
                $query->when(request()->filled('start_date'), function ($q) {
                    $q->where('created_at', '>=', \Carbon\Carbon::parse(request()->input('start_date'))->startOfDay());
                })
                ->when(request()->filled('end_date'), function ($q) {
                    $q->where('created_at', '<=', \Carbon\Carbon::parse(request()->input('end_date'))->endOfDay());
                });
            } else {
                $query->when(request()->filled('period'), function ($q) {
                    $dateRange = getDateRangeByPeriod(request()->input('period'));
                    if ($dateRange) {
                        $q->whereBetween('created_at', [$dateRange['start']->startOfDay(), $dateRange['end']->endOfDay()]);
                    }
                });
            }
        }



        return $query;
    }


    public function scopeFilterBySentimentScore($query)
    {
        $positiveThreshold = RuleEngineService::getPositiveSentimentThreshold();
        $negativeThreshold = RuleEngineService::getNegativeSentimentThreshold();

        $sentimentScore = request()->input('sentiment_score', request()->input('sentiment'));

        $query->when(!empty($sentimentScore), function ($q) use ($positiveThreshold, $negativeThreshold, $sentimentScore) {
            // Apply AI processed check only when sentiment filtering is requested,
            // because sentiment_score is populated by AI processing.
            $q->where('review_news.is_ai_processed', 1);

            if ($sentimentScore === 'positive') {
                $q->where('review_news.sentiment_score', '>=', $positiveThreshold);
            } elseif ($sentimentScore === 'negative') {
                $q->where('review_news.sentiment_score', '<', $negativeThreshold);
            } elseif ($sentimentScore === 'neutral') {
                $q->where('review_news.sentiment_score', '>=', $negativeThreshold)
                    ->where('review_news.sentiment_score', '<', $positiveThreshold);
            }
        });

        return $query;
    }


    public function scopeGlobalReviewFilters($query, $is_staff_review = 0, $is_overall = null, $is_ai_processed = 0)
    {
        return $query
            ->when($is_ai_processed || request()->has('sentiment_score') || request()->has('sentiment') || request()->has('topics'), function ($q) {
                $q->where('review_news.is_ai_processed', 1);
            })
            ->filterByDateRange()
            ->filterBySentimentScore()
            ->filterByStaff()
            ->filterByTopics()
            ->filterBySurvey()
            ->filterByTags()
            ->filterByStar()
            ->filterByRating()
            ->filterByReviewIds()
            ->filterByRuleOutcomes()
            ->filterByCsatScore()
            ->filterByInsightId()
            ->filterByIsPrivate()
            ->filterByBusinessArea()
            ->filterByBusinessService()
            ->filterByCategory()
            ->filterBySubCategory()
            ->when($is_staff_review, function ($q) {
                $q->whereMeetsThreshold(1);
            })
            ->when(true, function ($q) use ($is_overall) {
                if ($is_overall === 'ignore') {
                    return;
                }
                if (request()->has('is_overall')) {
                    $val = request()->input('is_overall');
                    if ($val !== null && $val !== '' && $val !== 'all') {
                        $q->where('review_news.is_overall', $val);
                    }
                } elseif ($is_overall !== null) {
                    $q->where('review_news.is_overall', $is_overall);
                }
            });
    }

    public function scopeFilterByBusinessService($query)
    {
        $query->when(request()->has('business_service_ids'), function ($q) {
            $serviceIds = is_array(request('business_service_ids')) ? request('business_service_ids') : explode(',', request('business_service_ids'));
            $q->whereHas('business_services', function($subQuery) use ($serviceIds) {
                $subQuery->whereIn('review_business_services.business_service_id', $serviceIds);
            });
        });
    }

    public function scopeFilterByBusinessArea($query)
    {
        $query->when(request()->has('business_area_ids'), function ($q) {
            $areaIds = is_array(request('business_area_ids')) ? request('business_area_ids') : explode(',', request('business_area_ids'));
            $q->whereHas('business_services', function($subQuery) use ($areaIds) {
                $subQuery->whereIn('review_business_services.business_area_id', $areaIds);
            });
        });
    }

    public function scopeFilterByCategory($query)
    {
        $query->when(request()->has('category_ids'), function ($q) {
            $categoryIds = is_array(request('category_ids')) ? request('category_ids') : explode(',', request('category_ids'));
            $q->whereExists(function ($subQuery) use ($categoryIds) {
                $subQuery->select(\DB::raw(1))
                    ->from('review_value_news as rvn')
                    ->join('q_q_sub_categories as qqsc', 'qqsc.question_id', '=', 'rvn.question_id')
                    ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                    ->join('question_categories as qc_parent', 'qc_sub.parent_question_category_id', '=', 'qc_parent.id')
                    ->whereColumn('rvn.review_id', 'review_news.id')
                    ->whereIn('qc_parent.id', $categoryIds);
            });
        });
    }

    public function scopeFilterBySubCategory($query)
    {
        $query->when(request()->has('sub_category_ids'), function ($q) {
            $subCategoryIds = is_array(request('sub_category_ids')) ? request('sub_category_ids') : explode(',', request('sub_category_ids'));
            $q->whereExists(function ($subQuery) use ($subCategoryIds) {
                $subQuery->select(\DB::raw(1))
                    ->from('review_value_news as rvn')
                    ->join('q_q_sub_categories as qqsc', 'qqsc.question_id', '=', 'rvn.question_id')
                    ->join('question_categories as qc_sub', 'qqsc.question_sub_category_id', '=', 'qc_sub.id')
                    ->whereColumn('rvn.review_id', 'review_news.id')
                    ->whereIn('qc_sub.id', $subCategoryIds);
            });
        });
    }

    public function scopeFilterByIsPrivate($query)
    {
        $query->when(request()->has('is_private'), function ($q) {
            $isPrivate = filter_var(request()->input('is_private'), FILTER_VALIDATE_BOOLEAN);
            if ($isPrivate) {
                $q->where('review_news.is_private', 1);
            } else {
                $q->where(function ($sub) {
                    $sub->where('review_news.is_private', 0)
                        ->orWhereNull('review_news.is_private');
                });
            }
        });
    }

    public function scopeFilterByStaff($query)
    {
        $query
            // Apply single staff_id filter
            ->when(request()->has('staff_id'), function ($q) {
                $q->where('review_news.staff_id', request()->input('staff_id'));
            })
            // Apply multiple staff_ids filter (comma-separated)
            ->when(request()->has('staff_ids') && !empty(request()->input('staff_ids')), function ($q) {
                $staffIds = request()->input('staff_ids');

                // Handle both array and comma-separated string
                if (is_string($staffIds)) {
                    $staffIds = array_map('trim', explode(',', $staffIds));
                }

                // Filter out non-numeric values and convert to integers
                $staffIds = array_filter(array_map('intval', $staffIds), function ($id) {
                    return $id > 0;
                });

                if (!empty($staffIds)) {
                    $q->whereIn('review_news.staff_id', $staffIds);
                }
            })
            // Apply has_staff filter
            ->when(request()->has('has_staff') && request()->input('has_staff'), function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('review_news.staff_id')
                        ->orWhereHas('rule_outcomes', function ($rq) {
                            $rq->where('is_staff_mentioned', true);
                        });
                });
            });

        return $query;
    }

    public function scopeFilterByReviewIds($query)
    {
        $query->when(request()->has('review_ids') && !empty(request()->input('review_ids')), function ($q) {
            $reviewIds = request()->input('review_ids');

            // Handle both array and comma-separated string
            if (is_string($reviewIds)) {
                $reviewIds = array_filter(array_map('trim', explode(',', $reviewIds)));
            }

            if (!empty($reviewIds)) {
                $q->whereIn('review_news.id', $reviewIds);
            }
        });

        return $query;
    }

    public function scopeFilterByTopics($query)
    {
        $query->when(request()->has('topics') && !empty(request()->input('topics')), function ($q) {
            // Apply AI Processed check (Review must be processed to have topics)
            $q->where('review_news.is_ai_processed', 1);

            $topic = request()->input('topics');
            // Use whereRaw with JSON_SEARCH to search within the main_category field
            $q->whereRaw("JSON_SEARCH(review_news.topics, 'one', ?, null, '$[*].main_category') IS NOT NULL", [$topic]);
        });

        return $query;
    }

    public function scopeFilterBySurvey($query)
    {
        $hasSurveyIds = request()->has('survey_ids') && !empty(request()->input('survey_ids'));
        $hasSurveyId = request()->has('survey_id') && !empty(request()->input('survey_id'));

        $query->when($hasSurveyIds || $hasSurveyId, function ($q) {
            $surveyIdsInput = request()->input('survey_ids');
            $surveyIdInput = request()->input('survey_id');

            $allSurveyIds = [];

            if (!empty($surveyIdsInput)) {
                $allSurveyIds = is_string($surveyIdsInput) ? explode(',', $surveyIdsInput) : (array) $surveyIdsInput;
            }

            if (!empty($surveyIdInput)) {
                $singleIds = is_string($surveyIdInput) ? explode(',', $surveyIdInput) : (array) $surveyIdInput;
                $allSurveyIds = array_merge($allSurveyIds, $singleIds);
            }

            // Clean up and convert to integers
            $surveyIds = array_filter(array_map('intval', array_map('trim', $allSurveyIds)), function ($id) {
                return $id > 0;
            });

            if (!empty($surveyIds)) {
                $q->whereIn('review_news.survey_id', array_unique($surveyIds));
            }
        });

        return $query;
    }

    public function scopeFilterByTags($query)
    {
        $query->when(request()->has('tag_ids') && !empty(request()->input('tag_ids')), function ($q) {
            $tagIds = request()->input('tag_ids');

            // Handle both array and comma-separated string
            if (is_string($tagIds)) {
                $tagIds = array_map('trim', explode(',', $tagIds));
            }

            // Filter out non-numeric values and convert to integers
            $tagIds = array_filter(array_map('intval', $tagIds), function ($id) {
                return $id > 0;
            });

            if (!empty($tagIds)) {
                $q->whereHas('value.tags', function ($tagQuery) use ($tagIds) {
                    $tagQuery->whereIn('tags.id', $tagIds);
                });
            }
        });

        return $query;
    }

    public function scopeFilterByStar($query)
    {
        $query->when(request()->has('star_ids') && !empty(request()->input('star_ids')), function ($q) {
            $starIds = request()->input('star_ids');

            // Handle both array and comma-separated string
            if (is_string($starIds)) {
                $starIds = array_map('trim', explode(',', $starIds));
            }

            // Filter out non-numeric values and convert to integers
            $starIds = array_filter(array_map('intval', $starIds), function ($id) {
                return $id > 0;
            });

            if (!empty($starIds)) {
                $q->whereHas('value', function ($valueQuery) use ($starIds) {
                    $valueQuery->whereIn('star_id', $starIds);
                });
            }
        });

        return $query;
    }

    public function scopeFilterByRating($query)
    {
        $rating = request()->input('rating');
        if (!$rating) {
            return $query;
        }

        // Validate rating is between 1-5
        $rating = (int) $rating;
        if ($rating < 1 || $rating > 5) {
            return $query;
        }

        // Use self-contained subquery to avoid dependency on calculated_rating alias
        return $query->whereExists(function ($subQuery) use ($rating) {
            $subQuery->select(DB::raw(1))
                ->from('review_value_news as rvn_rating')
                ->join('stars as s_rating', 'rvn_rating.star_id', '=', 's_rating.id')
                ->whereColumn('rvn_rating.review_id', 'review_news.id')
                ->groupBy('rvn_rating.review_id')
                ->havingRaw('ROUND(AVG(s_rating.value), 1) = ?', [$rating]);
        });
    }

    public function scopeFilterByRuleOutcomes($query)
    {
        $flags = [
            'is_staff_mentioned',
            'is_sentiment_flagged',
            'is_category_detected',
            'is_critical_alert',
            'is_staff_risk',
            'is_high_emotion',
            'is_mismatch',
            'is_service_identified',
            'is_area_detected'
        ];

        $activeFlags = [];
        foreach ($flags as $flag) {
            if (request()->has($flag) && request()->input($flag)) {
                $activeFlags[] = $flag;
            }
        }

        if (!empty($activeFlags)) {
            $query->whereHas('rule_outcomes', function ($q) use ($activeFlags) {
                foreach ($activeFlags as $flag) {
                    $q->where($flag, true);
                }
            });
        }

        if (request()->has('flagged_reviews') && request()->input('flagged_reviews') !== null && request()->input('flagged_reviews') !== '') {
            $isFlagged = (bool) request()->input('flagged_reviews');
            $query->whereHas('rule_outcomes', function ($q) use ($isFlagged) {
                $q->where('is_flagged', $isFlagged);
            });
        }

        return $query;
    }

    public function scopeFilterByCsatScore($query)
    {
        if (request()->has('csat_score') && request()->input('csat_score')) {
            $query->whereMeetsThreshold(0);
        }
        return $query;
    }

    public function scopeFilterByInsightId($query)
    {
        if (request()->has('insight_id') && request()->input('insight_id')) {
            $insightId = request()->input('insight_id');
            $insight = \App\Models\InsightRecord::find($insightId);
            if ($insight && !empty($insight->review_ids)) {
                $query->whereIn('review_news.id', $insight->review_ids);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        return $query;
    }
}
