<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use HasFactory, SoftDeletes;

    protected $appends = [
        'is_subscribed',
        'estimated_tokens_per_review',
        'estimated_reviews_limit',
        'processed_reviews_this_month',
        'remaining_reviews_this_month'
    ];

    protected $table = "businesses";

    protected $fillable = [
        "Name",
        'is_active',
        'last_recommendation_at',
        "Address",
        "PostCode",
        "OwnerID",
        "Status",
        "business_type",
        "Logo",
        "Key_ID",
        "expiry_date",
        "About",
        "Webpage",
        "PhoneNumber",
        "EmailAddress",
        "homeText",
        "AdditionalInformation",
        "GoogleMapApi",
        'review_type',
        "show_image",
        'google_map_iframe',
        'Is_guest_user',
        'is_review_slider',
        'review_only',
        'is_branch',
        "header_image",
        "rating_page_image",
        "placeholder_image",
        "primary_color",
        "secondary_color",
        "client_primary_color",
        "client_secondary_color",
        "client_tertiary_color",
        "user_review_report",
        "guest_user_review_report",
        "pin",
        "time_zone",

        'is_guest_user_overall_review',
        'is_guest_user_survey',
        'is_guest_user_survey_required',
        'is_guest_user_show_stuffs',
        'is_guest_user_show_stuff_image',
        'is_guest_user_show_stuff_name',

        // Registered user fields
        'is_registered_user_overall_review',
        'is_registered_user_survey',
        'is_registered_user_survey_required',
        'is_registered_user_show_stuffs',
        'is_registered_user_show_stuff_image',
        'is_registered_user_show_stuff_name',

        'enable_ip_check',
        'enable_location_check',
        'latitude',
        'longitude',
        'review_distance_limit',
        'threshold_rating',
        'review_labels',

        //
        'guest_survey_id',
        'registered_user_survey_id',
        'default_color_threshold',
        'default_branch_id',
        'has_rule_management',
        'is_treat_manager_as_staff',
        'openai_token_limit',
        'service_plan_id',
        'service_plan_discount_code',
        'service_plan_discount_amount',
        'trial_end_date',
        'start_date',
        'is_setup_complete',
        'step_no',
        'rolling_ai_insight',
        'is_show_public_page_to_customer',
        'is_sidebar_hidden',
    ];

    protected $casts = [
        'review_labels' => 'array',
        'default_color_threshold' => 'array',
        'is_treat_manager_as_staff' => 'boolean',
        'last_recommendation_at' => 'datetime',
        'rolling_ai_insight' => 'array',
        'is_show_public_page_to_customer' => 'boolean',
        'is_sidebar_hidden' => 'boolean',
    ];

    protected $hidden = [
        "pin"
    ];

    /**
     * Dynamically retrieve the trial end date.
     * If the column is empty or 0000-00-00, it safely falls back to the current subscription's end date.
     */
    public function getTrialEndDateAttribute($value)
    {
        if (empty($value) || $value === '0000-00-00') {
            return $this->current_subscription?->end_date;
        }
        
        return $value;
    }



    protected static function boot()
    {
        parent::boot();

        static::creating(function ($business) {
            // Set default color threshold if not provided
            if (empty($business->default_color_threshold)) {
                $business->default_color_threshold = [
                    [
                        "score_range" => [80, 100],
                        "status" => "Excellent",
                        "color" => "bg-green-500",
                    ],
                    [
                        "score_range" => [65, 79],
                        "status" => "Good",
                        "color" => "bg-lime-500",
                    ],
                    [
                        "score_range" => [50, 64],
                        "status" => "Average",
                        "color" => "bg-yellow-500",
                    ],
                    [
                        "score_range" => [40, 49],
                        "status" => "Needs Attention",
                        "color" => "bg-orange-500",
                    ],
                    [
                        "score_range" => [0, 39],
                        "status" => "Critical",
                        "color" => "bg-red-500",
                    ],
                ];
            }
        });

        static::updating(function ($business) {
            if ($business->isDirty('service_plan_id') || $business->getOriginal('service_plan_id') != $business->service_plan_id) {
                // Delete custom overrides so it inherits modules from the new service plan
                \App\Models\BusinessModule::where('business_id', $business->id)->delete();
            }
        });

        static::deleting(function ($business) {
            $folder_path = "business_{$business->OwnerID}/business_{$business->id}";
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($folder_path)) {
                \Illuminate\Support\Facades\Storage::disk('public')->deleteDirectory($folder_path);
            }
        });
    }

    public function businessModules()
    {
        return $this->hasMany(BusinessModule::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(BusinessSubscription::class);
    }

    public function scopeFilterClients($query)
    {
        return $query->where('is_active', true);
    }

    public function current_subscription()
    {
        return $this->hasOne(BusinessSubscription::class)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->orderBy('end_date', 'desc');
    }

    public function subscription()
    {
        return $this->hasOne(BusinessSubscription::class, 'business_id', 'id')
            ->orderByDesc("business_subscriptions.id");
    }

    private function isValidSubscription($subscription)
    {
        if (!$subscription) {
            return false;
        } // No subscription

        // Return false if start_date or end_date is empty
        if (empty($subscription->start_date) || empty($subscription->end_date)) {
            return false;
        }


        $startDate = Carbon::parse($subscription->start_date)->startOfDay();
        $endDate = Carbon::parse($subscription->end_date)->endOfDay();
        $today = Carbon::today(); // Get today's date (start of day)

        // Return false if the subscription hasn't started
        if ($startDate->isFuture()) {
            return false;
        };

        // Return false if the subscription has expired (end_date is before today)
        if ($endDate->isPast() && !$endDate->isSameDay($today)) {
            return false;
        };

        return true;
    }

    private function isTrialDateValid($trial_end_date)
    {
        // Return false if trial_end_date is empty or null
        if (empty($trial_end_date)) {
            return false;
        }

        // Parse the date and check validity
        $parsedDate = Carbon::parse($trial_end_date);
        return !($parsedDate->isPast() && !$parsedDate->isToday());
    }

    public function getIsSubscribedAttribute(): bool
    {
        $user = auth()->user();
        if (empty($user) && !app()->runningInConsole()) {
            return false;
        }

        // Return false if the business is not active
        if (!$this->is_active) {
            return false;
        }

        $validTrialDate = $this->isTrialDateValid($this->trial_end_date);
        $validExpiryDate = $this->isTrialDateValid($this->expiry_date);
        $latest_subscription = $this->current_subscription;

        // If no valid subscription AND no valid trial date AND no valid expiry date, return false
        if (!$this->isValidSubscription($latest_subscription) && !$validTrialDate && !$validExpiryDate) {
            return false;
        }

        return true;
    }

    /**
     * Check if business has reached its OpenAI token limit
     */
    public function getIsTokenLimitReachedAttribute(): bool
    {
        $limit = $this->openai_token_limit;

        // -1 means unlimited
        if ($limit === -1) {
            return false;
        }

        // If limit is 0 and not explicitly -1, consider it reached (no quota)
        if ($limit === 0 || $limit === null) {
            return true;
        }

        // Calculate usage for current billing cycle
        $usage = \App\Utils\TokenUsageUtil::calculateUsage(
            business: $this
        );
        
        return $usage['ai_tokens']['used'] >= $limit;
    }

    public function getEstimatedTokensPerReviewAttribute(): int
    {
        if (!$this->id) {
            return 0;
        }
        $processor = resolve(\App\Services\AIProcessor\OpenAIProcessorService::class);
        $enabledModulesMap = $processor->getBusinessAiModules($this->id);
        $activeModules = array_keys(array_filter($enabledModulesMap));
        return \App\Services\AIProcessor\OpenAIProcessorService::estimateTokensPerReview($activeModules);
    }

    public function getEstimatedReviewsLimitAttribute(): int
    {
        $limit = $this->openai_token_limit;
        if ($limit === -1) {
            return -1;
        }
        if ($limit === 0 || $limit === null) {
            return 0;
        }
        $estPerReview = $this->estimated_tokens_per_review;
        return (int)floor($limit / $estPerReview);
    }

    public function getProcessedReviewsThisMonthAttribute(): int
    {
        $usage = \App\Utils\TokenUsageUtil::calculateUsage(
            business: $this
        );
        $startDate = $usage['billing_cycle_start'] ?? now()->startOfMonth();
        $endDate = $usage['billing_cycle_end'];

        $query = \App\Models\OpenAITokenUsage::where('business_id', $this->id)
            ->where('created_at', '>=', $startDate);
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->count();
    }

    public function getRemainingReviewsThisMonthAttribute(): int
    {
        $limit = $this->estimated_reviews_limit;
        if ($limit === -1) {
            return -1;
        }
        return max(0, $limit - $this->processed_reviews_this_month);
    }

    public function getIsSelfRegisteredBusinessesAttribute()
    {
        return $this->attributes['is_self_registered_businesses'] ?? false;
    }

    public function times()
    {
        return $this->hasMany(BusinessDay::class, 'business_id', 'id');
    }


    public function customers()
    {
        return $this->belongsToMany(User::class, "review_news", 'business_id', 'user_id');
    }


    public function owner()
    {
        return $this->hasOne(User::class, 'id', 'OwnerID');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function defaultBranch()
    {
        return $this->belongsTo(Branch::class, 'default_branch_id');
    }

    public function service_plan()
    {
        return $this->belongsTo(ServicePlan::class, 'service_plan_id');
    }


    public function guest_survey()
    {
        return $this->belongsTo(Survey::class, 'guest_survey_id', 'id');
    }

    public function registered_user_survey()
    {
        return $this->belongsTo(Survey::class, 'registered_user_survey_id', 'id');
    }

    public function qrCodeSettings()
    {
        return $this->hasOne(QrCodeSetting::class);
    }

    /**
     * Scope a query to filter businesses based on search parameters
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query)
    {
        $period = request()->get('period', 'all_time');

        $dateRange = $period === 'all_time' ? null : getDateRangeByPeriod($period);

        // DATE RANGE FILTER
        if ($dateRange) {
            $query->whereBetween('businesses.created_at', [
                Carbon::parse($dateRange['start'])->startOfDay(),
                Carbon::parse($dateRange['end'])->endOfDay()
            ]);
        }

        // ACTIVE STATUS FILTER
        if (request()->filled('is_active')) {
            $query->where('businesses.is_active', request()->input('is_active'));
        }

        // Enhanced search across multiple fields
        if (request()->filled('search_key')) {
            $searchTerm = request()->input('search_key');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('businesses.Name', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.Address', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.PostCode', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.Status', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.About', 'like', '%' . $searchTerm . '%')
                    // ->orWhere('businesses.PhoneNumber', 'like', '%' . $searchTerm . '%')
                    ->orWhere('businesses.EmailAddress', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter by status
        if (request()->filled('Status')) {
            $query->where('businesses.Status', request()->input('Status'));
        }

        // Filter by owner ID
        if (request()->filled('owner_id')) {
            $query->where('businesses.OwnerID', request()->input('owner_id'));
        }

        // Filter by active status (not deleted)
        if (request()->boolean('active_only')) {
            $query->whereNull('businesses.deleted_at');
        }

        return $query;
    }

    /**
     * Scope a query to filter businesses for client-facing views
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActiveStatus($query, $is_active)
    {
        return $query->when($is_active !== null, function ($query) use ($is_active) {
            $query->where(function ($subQuery) use ($is_active) {
                if ($is_active) {
                    // For active or subscribed businesses
                    $subQuery->where('is_active', 1)
                        ->where(function ($q) {
                            $q->where(function ($innerQuery) {
                                $innerQuery->whereNotNull('trial_end_date')
                                    ->where(function ($trialEndQuery) {
                                        $trialEndQuery->where('trial_end_date', '>', now())
                                            ->orWhere('trial_end_date', now()->toDateString());
                                    });
                            })
                                ->orWhereHas('current_subscription', function ($subQuery) {
                                    $subQuery->where(function ($subscriptionQuery) {
                                        $subscriptionQuery->where('start_date', '<=', now())
                                            ->where(function ($endDateQuery) {
                                                $endDateQuery->whereNull('end_date')
                                                    ->orWhere('end_date', '>=', now());
                                            });
                                    });
                                });
                        });
                } else {
                    // For inactive or unsubscribed businesses
                    $subQuery->where('is_active', 0)
                        ->orWhere(function ($q) {
                            $q->where(function ($innerQuery) {
                                $innerQuery->whereNull('trial_end_date')
                                    ->orWhere(function ($trialQuery) {
                                        $trialQuery->whereNotNull('trial_end_date')
                                            ->where('trial_end_date', '<', now())
                                            ->where('trial_end_date', '!=', now()->toDateString());
                                    });
                            })
                                ->whereDoesntHave('current_subscription', function ($subQuery) {
                                    $subQuery->where('start_date', '<=', now())
                                        ->where(function ($endDateQuery) {
                                            $endDateQuery->whereNull('end_date')
                                                ->orWhere('end_date', '>=', now());
                                        });
                                });
                        });
                }
            });
        });
    }

    public function isModuleEnabled(string $moduleName): bool
    {
        $module = \App\Models\Module::where('name', $moduleName)->first();
        if (!$module || !$module->is_enabled) {
            return false;
        }

        // Check business specific module override first
        $businessModule = $this->businessModules()->where('module_id', $module->id)->first();
        if ($businessModule !== null) {
            return (bool)$businessModule->is_enabled;
        }

        // Fallback to service plan modules
        if ($this->service_plan_id) {
            $servicePlanModule = \App\Models\ServicePlanModule::where([
                'service_plan_id' => $this->service_plan_id,
                'module_id' => $module->id,
            ])->first();
            if ($servicePlanModule) {
                return (bool)$servicePlanModule->is_enabled;
            }
        }

        return false;
    }

    public function getIsBranchAttribute($value)
    {
        return $this->isModuleEnabled('multi_branch');
    }

    public function getHasRuleManagementAttribute($value)
    {
        return $this->isModuleEnabled('rules_management');
    }

    public function setTimeZoneAttribute($value)
    {
        $this->attributes['time_zone'] = $value ?: ($this->time_zone ?? 'UTC');
    }
}

