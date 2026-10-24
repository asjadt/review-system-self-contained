<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchMemberController;
use App\Http\Controllers\BusinessDaysController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomWebhookController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessServiceController;
use App\Http\Controllers\BusinessAreaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\LeafletController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\OpenAITokenReportController;
use App\Http\Controllers\QuestionCategoryController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\ReviewNewController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SuperAdminDashboardController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveyQuestionController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\QrCodeSettingController;
use App\Http\Controllers\BusinessAiModuleController;
use App\Http\Controllers\RuleReportController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\BusinessSubscriptionController;
use App\Http\Controllers\ServicePlanController;
use App\Http\Controllers\LogoColorController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\SurveyPageSettingController;
use App\Http\Controllers\ExecutiveReportController;
use App\Http\Controllers\FeedbackOverviewReportController;
use App\Http\Controllers\SentimentAnalysisReportController;
use App\Http\Controllers\EmotionAnalysisReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
*/

// ============================================================================
// AuthController – Public auth
// ============================================================================
Route::post('/resend-email-verify-mail', [AuthController::class, "resendEmailVerifyByToken"]);
Route::post('/auth', [AuthController::class, "userLogin"]);
Route::post('/auth/register', [AuthController::class, "userRegister"]);

// ============================================================================
// ForgotPasswordController – Public forgot/reset
// ============================================================================
Route::post('/v1.0/forgot-password', [ForgotPasswordController::class, "storeForgetPassword"]);
Route::patch('/v1.0/forget-password/reset/{token}', [ForgotPasswordController::class, "changePasswordByToken"]);

// ============================================================================
// CustomWebhookController – Webhooks
// ============================================================================
Route::post('webhooks/stripe', [CustomWebhookController::class, "handleStripeWebhook"])->name('stripe.webhook');
Route::get('subscription/redirect', [SubscriptionController::class, "redirectUserToStripe"])->name('subscription.redirect');
Route::get('subscription/success', [SubscriptionController::class, "stripePaymentSuccess"])->name('subscription.success_payment');
Route::get('subscription/failed', [SubscriptionController::class, "stripePaymentFailed"])->name('subscription.failed_payment');

Route::get('subscription/renewal/redirect', [SubscriptionController::class, "redirectUserToStripeRenewal"])->name('subscription.renewal.redirect');
Route::get('subscription/renewal/success', [SubscriptionController::class, "stripeRenewPaymentSuccess"])->name('subscription.success_renewal');
Route::get('subscription/renewal/failed', [SubscriptionController::class, "stripeRenewPaymentFailed"])->name('subscription.failed_renewal');

Route::apiResource('service-plans', ServicePlanController::class);


// ============================================================================
// OwnerController – Public owner registration helpers
// ============================================================================
Route::post('/owner/user/registration', [OwnerController::class, "createUser2"]);

// ============================================================================
// AuthController – Auxiliary public auth helpers
// ============================================================================
Route::post('/v1.0/auth/check-user-email', [AuthController::class, "checkUserEmail"]);
Route::post('/v1.0/auth/check-branch-email', [AuthController::class, "checkBranchEmail"]);
Route::post('/v1.0/auth/check-branch-code', [BranchController::class, "checkBranchCode"]);

// ============================================================================
// Protected Routes (auth:api)
// ============================================================================
Route::middleware(['auth:api'])->group(function () {


    Route::post('/v1.0/register-device-token', [DeviceTokenController::class, 'createDeviceToken']);

    // Subscription Management
    Route::controller(BusinessSubscriptionController::class)->group(function () {
        Route::get('/v1.0/my-subscription', 'getMySubscription');
    });
    Route::post('/v1.0/subscriptions/create-intent', [CustomWebhookController::class, 'createIntent']);

    // modules  management section
    Route::put('/v1.0/modules/toggle-active', [ModuleController::class, "toggleActiveModule"]);
    Route::put('/v1.0/business-modules/enable', [ModuleController::class, "enableBusinessModule"]);
    Route::put('/v1.0/service-plan-modules/enable', [ModuleController::class, "enableServicePlanModule"]);
    Route::get('/v1.0/business-modules/{business_id}', [ModuleController::class, "getBusinessModules"]);
    Route::get('/v1.0/modules', [ModuleController::class, "getModules"]);
    // end modules management section

    // QR Code Settings
    Route::get('v1.0/qr-code-settings/{businessId}', [QrCodeSettingController::class, 'getQRCodeSettings']);
    Route::put('v1.0/qr-code-settings', [QrCodeSettingController::class, 'updateQRCodeSettings']);

    // =====================================================================================
    Route::middleware(['superadmin'])->group(function () {

        // Business AI Modules Routes
        Route::group(['prefix' => 'v1.0'], function () {
            // Super admin routes
            Route::get('/business-ai-modules/{businessId}', [BusinessAiModuleController::class, 'getBusinessAiModules']);
            Route::patch('/business-ai-modules/{businessId}', [BusinessAiModuleController::class, 'updateBusinessAiModules']);
            Route::get('/business-ai-modules/{businessId}/token-usage', [BusinessAiModuleController::class, 'getBusinessAITokenUsage']);

            // Business owner routes (can view but not modify)
            Route::get('/business-ai-modules/{businessId}/enabled', [BusinessAiModuleController::class, 'getEnabledBusinessAiModules']);
        });

        // -------------------------------------------------------------------------
        // UserController – Admin user reporting & destructive ops
        // -------------------------------------------------------------------------
        Route::get('/v1.0/owner-list', [UserController::class, 'getOwnerReport']);
        Route::patch('/v1.0/owner/update-user', [OwnerController::class, 'updateUser']);



        // -------------------------------------------------------------------------
        // EmailTemplateController – Email template management & types
        // -------------------------------------------------------------------------
        Route::prefix('/v1.0/email-templates')->group(function () {
            Route::post('/', [EmailTemplateController::class, 'createEmailTemplate']);
            Route::put('/{id}', [EmailTemplateController::class, 'updateEmailTemplate']);
            Route::get('/', [EmailTemplateController::class, 'getEmailTemplates']);
            Route::get('/{id}', [EmailTemplateController::class, 'getEmailTemplateById']);
            Route::delete('/{ids}', [EmailTemplateController::class, 'deleteEmailTemplateById']);
        });
        Route::get('/v1.0/email-template-types', [EmailTemplateController::class, 'getEmailTemplateTypes']);

        // -------------------------------------------------------------------------
        // SuperAdminDashboardController – Super Admin Dashboard
        // -------------------------------------------------------------------------
        Route::get('/v1.0/dashboard/business-metrics', [SuperAdminDashboardController::class, 'getBusinessMetrics']);
        Route::get('/v1.0/dashboard/review-trends', [SuperAdminDashboardController::class, 'getReviewTrends']);
        Route::get('/v1.0/dashboard/customer-registration-trends', [SuperAdminDashboardController::class, 'getCustomerRegistrationTrends']);

        // -------------------------------------------------------------------------
        // SuperAdminController – Customer management (Super Admin)
        // -------------------------------------------------------------------------
        Route::get('/v1.0/customers/metrics', [SuperAdminController::class, 'customerMetrics']);
        Route::get('/v1.0/customers/{customerId}/reviews', [SuperAdminController::class, 'getCustomerReviews']);
        Route::patch('/v1.0/customers/{customerId}/email', [SuperAdminController::class, 'customerEmailChange']);
    });

    // ============================================================================
    // OwnerController – Owner + business creation (protected)
    // ============================================================================
    Route::post('/v1.0/create-user-with-business', [OwnerController::class, "createUserWithBusiness"]);
    Route::patch('/v1.0/owner/update', [OwnerController::class, "updateUserByUser"]);
    Route::patch('/v1.0/owner/review-reply', [OwnerController::class, "reviewReply"]);

    Route::post('/v1.0/header-image/{business_id}', [OwnerController::class, 'createHeaderImage']);
    Route::post('/v1.0/placeholder-image/{business_id}', [OwnerController::class, 'createPlaceholderImage']);
    Route::post('/v1.0/rating-page-image/{business_id}', [OwnerController::class, 'createRatingPageImage']);

    // ============================================================================
    // ReviewNewController – Create review (protected)
    // ============================================================================
    Route::post('/v1.0/review-new/{businessId}', [ReviewNewController::class, "createReview"]);
    Route::get('/v1.0/reviews', [ReviewNewController::class, 'getAllReviews']);




    // ============================================================================
    // QuestionController – CRUD & ordering (protected)
    // ============================================================================
    Route::get('/v1.0/questions', [QuestionController::class, 'getAllQuestions']);
    Route::get('/v1.0/questions/{id}', [QuestionController::class, 'questionById']);
    Route::post('/v1.0/questions/update-question-starts-and-tags', [QuestionController::class, 'updateQuestionStartsAndTags']);
    Route::post('/v1.0/questions', [QuestionController::class, 'createQuestion']);
    Route::delete('/v1.0/questions/{ids}', [QuestionController::class, 'deleteQuestion']);
    Route::patch('/v1.0/questions/ordering', [QuestionController::class, 'displayQuestionOrder']);
    Route::patch('/v1.0/questions/set-overall', [QuestionController::class, 'setOverallQuestions']);
    Route::patch('/v1.0/questions/toggle', [QuestionController::class, 'toggleQuestionActivation']);
    Route::patch('/v1.0/questions/{id}', [QuestionController::class, 'updatedQuestion'])->whereNumber('id');

    // #################
    // Question Category Management Routes
    // #################
    Route::post('/v1.0/question-categories', [QuestionCategoryController::class, 'createQuestionCategory']);

    Route::get('/v1.0/question-categories', [QuestionCategoryController::class, 'getAllQuestionCategories']);
    Route::get('/v1.0/question-categories/{id}', [QuestionCategoryController::class, 'getQuestionCategoryById']);

    Route::patch('/v1.0/question-categories/{id}', [QuestionCategoryController::class, 'updateQuestionCategory']);
    Route::patch('/v1.0/question-categories-toggle', [QuestionCategoryController::class, 'toggleQuestionCategory']);
    Route::delete('/v1.0/question-categories/{ids}', [QuestionCategoryController::class, 'deleteQuestionCategories']);
    Route::post('/v1.0/question-categories/with-sub-category', [QuestionCategoryController::class, 'createQuestionCategoryWithSubCategory']);

    // #################
    // Business Service Management Routes
    // #################
    Route::post('/v1.0/business-services', [BusinessServiceController::class, 'createBusinessService']);
    Route::post('/v1.0/business-services/with-area', [BusinessServiceController::class, 'createBusinessServiceWithArea']);
    Route::get('/v1.0/business-services', [BusinessServiceController::class, 'getAllBusinessServices']);
    Route::get('/v1.0/business-services/{id}', [BusinessServiceController::class, 'businessServiceById']);
    Route::patch('/v1.0/business-services/toggle', [BusinessServiceController::class, 'businessServiceToggle']);
    Route::patch('/v1.0/business-services/{id}', [BusinessServiceController::class, 'updateBusinessService']);
    Route::delete('/v1.0/business-services/{ids}', [BusinessServiceController::class, 'deleteBusinessServices']);

    // #################
    // Business Area Management Routes
    // #################
    Route::post('/v1.0/business-areas', [BusinessAreaController::class, 'createBusinessArea']);
    Route::get('/v1.0/business-areas', [BusinessAreaController::class, 'getAllBusinessAreas']);
    Route::get('/v1.0/business-areas/{id}', [BusinessAreaController::class, 'businessAreaById']);
    Route::patch('/v1.0/business-areas/{id}', [BusinessAreaController::class, 'updateBusinessArea']);
    Route::delete('/v1.0/business-areas/{ids}', [BusinessAreaController::class, 'deleteBusinessAreas']);
    Route::patch('/v1.0/business-areas/toggle', [BusinessAreaController::class, 'toggleBusinessArea']);

    // #################
    // User Management Routes (Business Owner)
    // #################
    Route::post('/v1.0/users', [UserController::class, 'createUser']);
    Route::get('/v1.0/users', [UserController::class, 'getAllBusinessUsers']);
    Route::get('/v1.0/users/{id}', [UserController::class, 'getUserById']);
    Route::patch('/v1.0/users/{id}', [UserController::class, 'updateBusinessUser']);
    Route::delete('/v1.0/users/{id}', [UserController::class, 'deleteUserById']);
    Route::patch('/v1.0/users/toggle-status', [UserController::class, 'toggleUserStatus']);




    // ============================================================================
    // LeafletController – CRUD (protected)
    // ============================================================================
    Route::post('/v1.0/leaflet/create', [LeafletController::class, 'insertLeaflet']);
    Route::put('/v1.0/leaflet/update/{id}', [LeafletController::class, 'editLeaflet']);
    Route::get('/v1.0/leaflet/get', [LeafletController::class, 'getAllLeaflet']);
    Route::get('/v1.0/leaflet/get/{id}', [LeafletController::class, 'leafletById']);
    Route::delete('/v1.0/leaflet/{ids}', [LeafletController::class, 'leafletDeleteById']);
    Route::post('/v1.0/leaflet-image', [LeafletController::class, 'insertLeafletImage']);

    // ============================================================================
    // StaffController – CRUD + image upload (protected)
    // ============================================================================
    // Route::get('/v1.0/staffs', [StaffController::class, 'getAllStaffs']);
    Route::get('/v1.0/staffs/metrics', [StaffController::class, 'getStaffMetrics']);
    Route::get('/v1.0/staffs/compliment-ratio', [StaffController::class, 'getComplimentRatio']);
    Route::get('/v1.0/staffs/top-staffs', [StaffController::class, 'getTopStaff']);
    // Route::get('/v1.0/staffs/{id}', [StaffController::class, 'getStaffById']);
    // Route::post('/v1.0/staffs', [StaffController::class, 'createStaff']);
    // Route::patch('/v1.0/staffs/{id}', [StaffController::class, 'updateStaff']);
    // Route::delete('/v1.0/staffs/{id}', [StaffController::class, 'deleteStaff']);
    Route::get('/v1.0/staffs/{staffId}/performance-report', [StaffController::class, 'staffPerformanceReport']);
    Route::get('/v1.0/staffs/{staffId}/rating-trends', [StaffController::class, 'staffRatingTrends']);
    Route::get('/v1.0/staffs/{staffId}/reviews', [StaffController::class, 'getStaffReviews']);
    Route::post('/v1.0/staff-image', [StaffController::class, 'uploadStaffImage']);

    // ============================================================================
    // TagController – Tags CRUD (protected)
    // ============================================================================

    Route::post('/v1.0/tags', [TagController::class, 'createTag']);
    Route::get('/v1.0/tags', [TagController::class, 'getAllTags']);
    Route::get('/v1.0/tags/{id}', [TagController::class, 'getTagById']);
    Route::patch('/v1.0/tags/multiple/{businessId}', [TagController::class, 'createMultipleTags']);
    Route::patch('/v1.0/tags/{id}', [TagController::class, 'updateTag']);
    Route::delete('/v1.0/tags/{ids}', [TagController::class, 'deleteTag']);

    // ============================================================================
    // NotificationController – Notifications Management (protected)
    // ============================================================================
    // Super Admin Only - Create notification
    Route::post('/v1.0/notification', [NotificationController::class, "createNotification"]);

    // Authenticated User Routes - Get, update status, mark all read, delete
    Route::get('/v1.0/notification', [NotificationController::class, "getNotification"]);
    Route::patch('/v1.0/notification/{notificationId}/status', [NotificationController::class, "changeNotificationStatus"]);
    Route::patch('/v1.0/notification/mark-all-read', [NotificationController::class, "markAsAllRead"]);
    Route::delete('/v1.0/notification/{notificationId}', [NotificationController::class, "deleteNotification"]);

    // ============================================================================
    // AuthController – Authenticated helpers
    // ============================================================================
    Route::get('/v1.0/auth/user-by-token', [AuthController::class, "userByToken"]);
    Route::patch('/v1.0/upload/profile-image', [OwnerController::class, "updateImage"]);
    // Route::post('/auth/check-pin/{id}', [AuthController::class, "verifyPin"]); // unused


    // ============================================================================
    // SurveyController – CRUD & ordering/toggle (protected)
    // ============================================================================
    Route::post('/v1.0/surveys', [SurveyController::class, "createSurvey"]);
    Route::put('/v1.0/surveys', [SurveyController::class, "updateSurvey"]);
    Route::post('/v1.0/surveys/ordering', [SurveyController::class, "orderSurveys"]);
    Route::get('/v1.0/surveys/{business_id}/{perPage}', [SurveyController::class, "getSurveys"]);
    Route::get('/v1.0/surveys/{business_id}', [SurveyController::class, "getAllSurveys"]);
    Route::patch('/v1.0/surveys/{id}/toggle-active', [SurveyController::class, "toggleSurveyActive"]);
    Route::delete('/v1.0/surveys/{id}', [SurveyController::class, "deleteSurveyById"]);

    // Survey Page Settings
    Route::put('/v1.0/survey-page-settings', [SurveyPageSettingController::class, 'updateSurveySettings']);

    // ============================================================================
    // SurveyQuestionController – Display order (protected)
    // ============================================================================
    Route::patch('/v1.0/survey-questions/display-order', [SurveyQuestionController::class, 'surveyQuestionDisplayOrder']);
    Route::get('/v1.0/survey-questions', [SurveyQuestionController::class, 'getBySurveyAndQuestionIds']);

    // ============================================================================
    // RolesController – Roles/permissions (protected)
    // ============================================================================
    Route::get('/v1.0/initial-role-permissions', [RolesController::class, "getInitialRolePermissions"]);
    Route::get('/v1.0/initial-permissions', [RolesController::class, "getInitialPermissions"]);
    Route::post('/v1.0/roles', [RolesController::class, "createRole"]);
    Route::put('/v1.0/roles', [RolesController::class, "updateRole"]);
    Route::get('/v1.0/roles', [RolesController::class, "getRoles"]);
    Route::get('/v1.0/roles/{id}', [RolesController::class, "getRoleById"]);
    Route::delete('/v1.0/roles/{ids}', [RolesController::class, "deleteRolesByIds"]);

    // ============================================================================
    // BusinessController – Business CRUD & tables (protected)
    // ============================================================================
    Route::post('/v1.0/business', [BusinessController::class, "storeRestaurant"]);
    Route::post('/v1.0/business/by-owner-id', [BusinessController::class, "storeRestaurantByOwnerId"]);
    Route::get('/v1.0/business', [BusinessController::class, "getAllBusinesses"]);

    // Specific routes MUST come before parameterized routes
    Route::patch('/v1.0/business/default-branch', [BusinessController::class, "updateDefaultBranch"]);
    Route::patch('/v1.0/business/setup-progress', [BusinessController::class, "updateSetupProgress"]);
    Route::patch('/v1.0/business/upload-image/{businessId}', [BusinessController::class, "uploadRestaurantImage"]);

    Route::delete("/v1.0/business/force-delete-by-ids/{ids}", [BusinessController::class, "forceDeleteBusinessesByIds"]);
    Route::post("/v1.0/business/restore-by-ids/{ids}", [BusinessController::class, "restoreBusinessesByIds"]);
    Route::get("/v1.0/business/deleted/all", [BusinessController::class, "getSoftDeletedBusinesses"]);

    // Parameterized routes come AFTER specific routes
    Route::get('/v1.0/business/{businessId}', [BusinessController::class, "getBusinessById"]);
    Route::patch('/v1.0/business/{businessId}', [BusinessController::class, "UpdateBusiness"]);
    Route::delete('/v1.0/business/{id}', [BusinessController::class, "deleteBusinessById"]);


    // ============================================================================
    // BranchController – Branch CRUD (protected, /v1.0/branches)
    // ============================================================================


    Route::prefix('v1.0/branches')->group(function () {
        Route::get('/', [BranchController::class, 'getBranches']);
        Route::post('/', [BranchController::class, 'createBranch']);

        // Branch overview with comparison metrics
        Route::get('/overview', [BranchController::class, 'getBranchOverview']);

        // Specific routes before parameterized routes
        Route::get('/{branchId}/metrics', [BranchController::class, 'branchMetric']);
        Route::get('/{branchId}/ai-insights', [BranchController::class, 'branchAiInsights']);
        // Staff Performance endpoint
        Route::get('/{branchId}/staff-performance', [BranchController::class, 'getBranchStaffPerformance']);
        // Recent Reviews endpoint
        Route::get('/{branchId}/recent-reviews', [BranchController::class, 'branchRecentReviews']);
        // Branch Recommendations endpoint
        Route::get('/{branchId}/recommendations', [BranchController::class, 'branchBranchRecommendations']);

        Route::get('/{id}', [BranchController::class, 'getBranchById']);
        Route::patch('/{id}', [BranchController::class, 'updateBranch']);
        Route::patch('/{id}/toggle-active', [BranchController::class, 'toggleBranchActive']);
        Route::delete('/{id}', [BranchController::class, 'deleteBranches']);
    });

    // ============================================================================
    // BranchMemberController – Branch member management (protected, /v1.0/branch-members)
    // ============================================================================
    Route::prefix('/v1.0/branch-members')->group(function () {
        Route::post('/assign', [BranchMemberController::class, 'assignBranchMember']);
        Route::post('/{id}/unassign', [BranchMemberController::class, 'unassignBranchMember']);
        Route::get('/', [BranchMemberController::class, 'getAllBranchMembers']);
    });

    // ============================================================================
    // BusinessDaysController – Business hours/days (protected)
    // ============================================================================
    Route::patch('/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "updateBusinessDays"]);
    Route::get('/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "getBusinessDays"]);



    // ============================================================================
    // ForgotPasswordController – Authenticated password change (protected)
    // ============================================================================
    Route::patch('/v1.0/auth/change-password', [ForgotPasswordController::class, "changePassword"]);




    // Questions (protected)
    Route::get('/review-new/get/questions-all', [ReviewNewController::class, 'getQuestionAll'])->name('getQuestionAll');

    Route::get('/review-new/get/questions-all-report', [ReviewNewController::class, "getQuestionAllReport"]);




    // ============================================================================`
    // CustomerController – Customers (protected)
    // ============================================================================
    Route::get('/v1.0/customers', [CustomerController::class, "getCustomers"]);


    // ============================================================================

    Route::get('/v1.0/reports/staff-comparison', [DashboardController::class, 'staffComparison']);


    // Route::get('/v1.0/branch-dashboard/{branchId}', [DashboardController::class, 'getBranchDashboard']);
    Route::get('/v1.0/reports/branch-comparison', [DashboardController::class, 'branchComparison']);




    // ============================================================================
    // ReviewNewController – Review by id (protected)
    // ============================================================================
    Route::get('/v1.0/review-new/{reviewId}', [ReviewNewController::class, "getReviewById"]);
    Route::put('/v1.0/reviews/{reviewId}/reply', [ReviewNewController::class, 'updateReviewReply']);
    Route::get('/openai-tokens/report', [OpenAITokenReportController::class, 'getTokenUsageReport']);



    Route::get('/v1.0/dashboard/unified', [DashboardController::class, 'getUnifiedDashboardData']);
    Route::get('/v1.0/dashboard/ai-insights', [DashboardController::class, 'getAiInsightsData']);






    // ============================================================================
    // NoteController – Notes management
    // ============================================================================
    Route::post('/v1.0/notes', [NoteController::class, 'createNote']);
    Route::get('/v1.0/notes', [NoteController::class, 'getAllNotes']);
    Route::get('/v1.0/notes/{id}', [NoteController::class, 'getNoteById']);
    Route::put('/v1.0/notes/{id}', [NoteController::class, 'updateNote']);
    Route::delete('/v1.0/notes/delete/{ids}', [NoteController::class, 'deleteNotes']);



    // ============================================================================
    // RuleReportController - Default Rule-Based Reports (NEW)
    // ============================================================================
    Route::prefix('v1.0/reports')->group(function () {
        // 1. Sentiment Analysis Report
        Route::get('/sentiment-analysis', [RuleReportController::class, 'sentimentAnalysis']);

        // 2. Emotion Intensity Report
        Route::get('/emotion-intensity', [RuleReportController::class, 'emotionIntensity']);

        // 3. Rating/Comment Mismatch Report
        Route::get('/rating-comment-mismatch', [RuleReportController::class, 'ratingCommentMismatch']);

        // 4. Category Issues Report
        Route::get('/category-issues', [RuleReportController::class, 'categoryIssues']);

        // 5. Service Types Report
        Route::get('/service-types', [RuleReportController::class, 'serviceTypes']);

        // 6. Business Areas Report
        Route::get('/business-areas', [RuleReportController::class, 'businessAreas']);

        // 7. Staff Mentions Report
        Route::get('/staff-mentions', [RuleReportController::class, 'staffMentions']);

        // 8. Staff Performance Risk Report
        Route::get('/staff-performance-risk', [RuleReportController::class, 'staffPerformanceRisk']);

        // 9. Flagged Reviews Report
        Route::get('/flagged-reviews', [RuleReportController::class, 'flaggedReviews']);

        // 10. Executive Customer Experience Overview Report
        Route::get('/executive-overview', [ExecutiveReportController::class, 'getExecutiveOverview']);

        // 11. Feedback Overview Report
        Route::get('/feedback-overview', [FeedbackOverviewReportController::class, 'getFeedbackOverview']);

        // 12. Sentiment Analysis Report (V2 UI Analytics)
        Route::get('/sentiment-analysis-report', [SentimentAnalysisReportController::class, 'getSentimentAnalysisReport']);

        // 13. Emotion Analysis Report (V2 UI Analytics)
        Route::get('/emotion-analysis-report', [EmotionAnalysisReportController::class, 'getEmotionAnalysisReport']);
    });

    // ============================================================================
    // AI RULES EXTENDED - Wizard, Bulk Operations, Metrics
    // ============================================================================
    require __DIR__ . '/ai_rules_extended.php';
});

// ============================================================================
// OwnerController – Public owner routes
// ============================================================================
// Route::post('/owner', [OwnerController::class, "createUser"]);
Route::post('/v1.0/register-guest-users', [OwnerController::class, "createGuestUser"]);
Route::post('/owner/pin/{ownerId}', [OwnerController::class, "updatePin"]);
Route::get('/owner/{ownerId}', [OwnerController::class, "getOwnerById"]);




Route::get('/review-new/get/questions-all-overall/customer', [ReviewNewController::class, 'getQuestionAllUnauthorizedOverall'])->name('getQuestionAllUnauthorizedOverall');




// ============================================================================
// ReviewNewController – Public guest review operations
// ============================================================================
Route::post('/v1.0/review-new-guest/{businessId}', [ReviewNewController::class, "storeReviewByGuest"]);
Route::post('/v1.0/reviews/overall/ordering', [ReviewNewController::class, "orderOverallReviews"]);
Route::post('/v1.0/voice/transcribe', [ReviewNewController::class, "transcribeVoice"]);

// ============================================================================
// BusinessController – Client public endpoints
// ============================================================================
Route::get('/v1.0/client/businesses', [BusinessController::class, "getBusinessesClients"]);
Route::post('/v1.0/client/create-user-with-business', [OwnerController::class, "createUserWithBusinessClient"]);
Route::get('/v1.0/client/business/{businessId}', [BusinessController::class, "getClientBusinessById"]);

// ============================================================================
// BusinessDaysController & StaffController – Client public endpoints
// ============================================================================
Route::get('/client/v1.0/business-days/{restaurentId}', [BusinessDaysController::class, "getBusinessDays"]);
Route::get('/v1.0/client/staffs', [StaffController::class, 'getClientAllStaffs']);

// ============================================================================
// ReviewNewController – Client review analytics & privacy (public client)
// ============================================================================
Route::get('/v1.0/client/review-new/rating-analysis/{businessId}', [ReviewNewController::class, "getAverageRatingClient"]);
Route::get('/v1.0/client/review-new/{businessId}', [ReviewNewController::class, "getReviewByBusinessIdClient"]);
Route::put('/v1.0/client/reviews/make-private/{ids}', [ReviewNewController::class, 'makeReviewsPrivate']);
Route::put('/v1.0/client/reviews/update-guest-email/{ids}', [ReviewNewController::class, 'updateGuestEmailsByReviews']);

// ============================================================================
// QuestionController – Client business questions (public client)
// ============================================================================
Route::get('/v1.0/client/questions/{business_id}', [QuestionController::class, 'getAllQuestionClient']);
Route::get('/v1.0/client/surveys/{id}', [SurveyController::class, "getSurveyByIdClient"]);
Route::post('/v1.0/client/email/send-email', [EmailController::class, "sendEmail"]);

// Client Users (with branch filtering)
Route::get('/v1.0/clients/users/{business_id}', [UserController::class, 'getUserClient']);

// Survey Page Settings
Route::get('/v1.0/survey-page-settings/{businessId}', [SurveyPageSettingController::class, 'getSurveySettingsByBusinessId']);

// Logo Color Extraction
Route::post('/logo/extract-colors', [LogoColorController::class, 'extract']);
