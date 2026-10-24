<?php

namespace App\Http\Controllers;

use App\Exports\BusinessExport;
use App\Models\DailyView;

use App\Models\Notification;

use App\Models\Question;
use App\Models\Business;
use App\Models\ReviewNew;
use App\Models\ReviewValue;
use App\Models\Tag;
use App\Models\User;

use App\Http\Requests\StoreBusinessRequest;
use App\Http\Requests\StoreBusinessByOwnerRequest;
use App\Http\Requests\UpdateBusinessRequest;
use App\Services\Business\BusinessProfileService;
use App\Services\Review\ReviewService;
use App\Traits\AuthorizesRoles;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDF;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Auth\Access\AuthorizationException;

class BusinessController extends Controller
{
    use AuthorizesRoles;

    protected $businessProfileService;
    protected $reviewService;

    /**
     * Constructor to inject BusinessService
     */
    public function __construct(BusinessProfileService $businessProfileService, ReviewService $reviewService)
    {
        $this->businessProfileService = $businessProfileService;
        $this->reviewService = $reviewService;
    }

    // ##################################################
    // This method is to store business
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/v1.0/business",
     *      operationId="storeRestaurant",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Store a new business",
     *      description="Create a new business with the provided details",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"Name","Address","PostCode"},
     *            @OA\Property(property="Name", type="string", example="My Restaurant"),
     *            @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *            @OA\Property(property="PostCode", type="string", example="SW1A 1AA")

     *         )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Business created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business created successfully"),
     *              @OA\Property(property="data", type="object",
     *                   @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                      @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *                      @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *                      @OA\Property(property="OwnerID", type="integer", example=1),
     *                      @OA\Property(property="Status", type="string", example="Inactive"),
     *                      @OA\Property(property="Key_ID", type="string", example="abc123"),
     *                      @OA\Property(property="expiry_date", type="string", format="date", example="15-12-2025")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */

    public function storeRestaurant(StoreBusinessRequest $request)
    {
        // VALIDATE REQUEST
        $payload_data = $request->validated();

        // ADD OWNER ID
        $payload_data["OwnerID"] = $request->user()->id;
        // ADD STATUS
        $payload_data["Status"] = "Inactive";

        // ADD KEY AND EXPIRY DATE
        $payload_data["Key_ID"] = Str::random(10);
        $payload_data["expiry_date"] = now()->addDays(15)->format('d-m-Y');

        // CREATE BUSINESS
        $business = Business::create($payload_data);


        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business created successfully",
            "data" => $business
        ], 201);
    }
    // ##################################################
    // This method is to store business
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/business/by-owner-id",
     *      operationId="storeRestaurantByOwnerId",
     *      tags={"z.unused"},
     *       security={
     *           {"bearerAuth": {}}
     *       },
     *      summary="Store a new business by specifying owner ID",
     *      description="Create a new business with the provided details and specified owner ID",
     *
     *  @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *            required={"Name","Address","PostCode","OwnerID"},
     *            @OA\Property(property="Name", type="string", example="My Restaurant"),
     *            @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *            @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *            @OA\Property(property="OwnerID", type="integer", example=1)
     *         )
     *      ),
     *      @OA\Response(
     *          response=201,
     *          description="Business created successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business created successfully"),
     *              @OA\Property(property="data", type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                  @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *                  @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *                  @OA\Property(property="OwnerID", type="integer", example=1),
     *                  @OA\Property(property="Status", type="string", example="Inactive"),
     *                  @OA\Property(property="Key_ID", type="string", example="abc123d"),
     *                  @OA\Property(property="expiry_date", type="string", format="date", example="15-12-2025")
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */

    public function storeRestaurantByOwnerId(StoreBusinessByOwnerRequest $request)
    {
        // VALIDATE REQUEST
        $payload_data = $request->validated();

        // ADD STATUS
        $payload_data["Status"] = "Inactive";

        // ADD KEY AND EXPIRY DATE
        $payload_data["Key_ID"] = Str::random(10);
        $payload_data["expiry_date"] = now()->addDays(15)->format('d-m-Y');

        // CREATE BUSINESS
        $business = Business::create($payload_data);


        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business created successfully",
            "data" => $business
        ], 201);
    }
    /**
     *
     * @OA\Delete(
     *      path="/v1.0/business/{id}",
     *      operationId="deleteBusinessById",
     *      tags={"business_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Delete business by ID (Super Admin only)",
     *      description="Delete a business and its associated daily views. Only super admins can perform this action.",
     *
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="Business ID to delete",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Business deleted successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business deleted successfully")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Super admin access required",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to delete businesses")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      )
     * )
     */
    public function deleteBusinessById($id, Request $request)
    {
        // CHECK SUPER ADMIN PERMISSION
        if (!$request->user()->hasRole("superadmin")) {
            throw new AccessDeniedHttpException('You do not have permission to delete businesses');
        }

        // CHECK IF BUSINESS EXISTS
        $business = Business::findOrFail($id);

        // EXECUTE DELETION WITHIN DATABASE TRANSACTION
        DB::transaction(function () use ($business, $id) {
            // DELETE BUSINESS
            $business->delete();

            // DELETE ASSOCIATED DAILY VIEWS
            DailyView::where("business_id", $id)->delete();
        });

        // RETURN SUCCESS RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business deleted successfully"
        ], 200);
    }




    // ##################################################
    // This method is to upload business image
    // ##################################################
    /**
     *
     * @OA\Post(
     *      path="/v1.0/business/upload-image/{businessId}",
     *      operationId="uploadRestaurantImage",
     *      tags={"business_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Upload business logo image",
     *      description="Upload and update the logo image for a specific business. Only business owners or super admins can perform this action.",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\MediaType(
     *              mediaType="multipart/form-data",
     *              @OA\Schema(
     *                  @OA\Property(
     *                      property="logo",
     *                      type="string",
     *                      format="binary",
     *                      description="Image file to upload (jpeg, png, jpg, gif, svg - max 2MB)"
     *                  ),
     *                  required={"logo"}
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Business logo uploaded successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business logo uploaded successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                  @OA\Property(property="Logo", type="string", example="img/business/1234567890_abc123.png")
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=403,
     *          description="Forbidden - Not business owner or super admin",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="You do not have permission to update this business")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=422,
     *          description="Validation failed",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Validation failed"),
     *              @OA\Property(property="errors", type="object")
     *          )
     *      )
     * )
     */
    public function uploadRestaurantImage($businessId, Request $request)
    {
        // VALIDATE REQUEST
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // CHECK IF BUSINESS EXISTS
        $business = Business::find($businessId);
        if (!$business) {
            throw new NotFoundHttpException('Business not found');
        }

        // CHECK OWNERSHIP OR SUPER ADMIN PERMISSION
        if ($business->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
            throw new AccessDeniedHttpException('This business does not belong to you');
        }

        // GENERATE UNIQUE FILENAME
        $imageName = time() . '_' . uniqid() . '.' . $request->logo->extension();

        // MOVE FILE TO PUBLIC DIRECTORY
        $destinationPath = public_path('img/business');
        $request->logo->move($destinationPath, $imageName);

        // BUILD FULL IMAGE PATH
        $imagePath = "img/business/" . $imageName;

        // UPDATE BUSINESS LOGO
        $business->update([
            "Logo" => $imagePath
        ]);

        // GET USER WITH RELATIONSHIP
        $user = User::with('business', 'branch')->findOrFail($request->user()->id);

        // SET DEFAULT_BRANCH_ID BASED ON ROLE
        if ($user->hasRole('branch_manager')) {
            // CHECK IF USER HAS A BRANCH ASSIGNED TO THEM
            if (!$user->branch) {
                throw new AuthorizationException('You do not have a branch assigned to you');
            }
            $user->setAttribute('default_branch_id', $user->branch?->branch_id ?? null);
        } else if ($user->hasRole('business_owner')) {
            $user->setAttribute('default_branch_id', $user->business?->default_branch_id ?? null);
            $user->setAttribute('branch_count', $user->business?->branches->count() ?? null);
        }

        // SET TOKEN AND PERMISSION
        $user->setAttribute('token', $user->createToken('authToken')->accessToken);
        $user->setAttribute('permissions', $user->getAllPermissions()->pluck('name'));
        // SET ROLE AS SINGLE OBJECT
        $user->setAttribute('role', $user->roles->first());

        // RETURN SUCCESS RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => $user
        ], 200);
    }
    // ##################################################
    // This method is to update business details
    // ##################################################
    /**
     * @OA\Patch(
     *     path="/v1.0/business/{businessId}",
     *     operationId="UpdateBusiness",
     *     tags={"business_management"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     summary="This method is to update business",
     *     description="This method is to update business",
     *
     *     @OA\Parameter(
     *         name="businessId",
     *         in="path",
     *         description="Business ID",
     *         required=true,
     *         example="1"
     *     ),
     *
     *     @OA\Parameter(
     *         name="_method",
     *         in="query",
     *         description="HTTP method override",
     *         required=true,
     *         example="PATCH"
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="All fields are optional for update",
     *         @OA\JsonContent(
     *             @OA\Property(property="Name", type="string", example="My Restaurant", description="Business name"),
     *             @OA\Property(property="Address", type="string", example="123 Main Street, City", description="Business address"),
     *             @OA\Property(property="PostCode", type="string", example="12345", description="Postal code"),
     *             @OA\Property(property="OwnerID", type="integer", example=1, description="Owner user ID"),
     *             @OA\Property(property="Status", type="string", example="Active", description="Business status (Active/Inactive)"),
     *             @OA\Property(property="Logo", type="string", example="img/business/logo.png", description="Logo image path"),
     *             @OA\Property(property="Key_ID", type="string", example="abc123", description="Unique business key"),
     *             @OA\Property(property="expiry_date", type="string", format="date", example="15-12-2025", description="Subscription expiry date"),
     *             @OA\Property(property="service_plan_id", type="integer", example=1, description="ID of the service plan"),
     *             @OA\Property(property="trial_end_date", type="string", format="date", example="15-12-2025", description="Trial period end date"),
     *             @OA\Property(property="About", type="string", example="Best restaurant in town", description="Business description"),
     *             @OA\Property(property="Webpage", type="string", format="url", example="https://example.com", description="Business website"),
     *             @OA\Property(property="PhoneNumber", type="string", example="+44123456789", description="Contact phone number"),
     *             @OA\Property(property="EmailAddress", type="string", format="email", example="business@example.com", description="Contact email"),
     *             @OA\Property(property="homeText", type="string", example="Welcome to our restaurant", description="Homepage text"),
     *             @OA\Property(property="AdditionalInformation", type="string", example="Open 24/7", description="Additional business info"),
     *             @OA\Property(property="GoogleMapApi", type="string", example="AIza...", description="Google Maps API key"),
     *             @OA\Property(property="review_type", type="string", example="star", description="Type of review system"),
     *             @OA\Property(property="show_image", type="boolean", example=true, description="Show images in reviews"),
     *             @OA\Property(property="google_map_iframe", type="string", example="<iframe>...</iframe>", description="Google Maps iframe embed code"),
     *             @OA\Property(property="Is_guest_user", type="boolean", example=true, description="Allow guest user reviews"),
     *             @OA\Property(property="is_review_slider", type="boolean", example=true, description="Enable review slider"),
     *             @OA\Property(property="review_only", type="boolean", example=false, description="Review only mode (no ratings)"),
     *             @OA\Property(property="is_branch", type="boolean", example=true, description="Business has branches"),
     *             @OA\Property(property="has_rule_management", type="boolean", example=false, description="Enable rule management"),
     *             @OA\Property(property="is_treat_manager_as_staff", type="boolean", example=false, description="Enable rule management"),
     *             @OA\Property(property="is_sidebar_hidden", type="boolean", example=false, description="Hide or collapse the sidebar navigation"),
     *             @OA\Property(property="header_image", type="string", example="/header_image/default.png", description="Header image path"),
     *             @OA\Property(property="rating_page_image", type="string", example="/rating_page/default.png", description="Rating page background image"),
     *             @OA\Property(property="placeholder_image", type="string", example="/placeholder/default.png", description="Placeholder image"),
     *             @OA\Property(property="primary_color", type="string", example="#FF5733", description="Primary brand color"),
     *             @OA\Property(property="secondary_color", type="string", example="#33FF57", description="Secondary brand color"),
     *             @OA\Property(property="client_primary_color", type="string", example="#5733FF", description="Client-facing primary color"),
     *             @OA\Property(property="client_secondary_color", type="string", example="#FF3357", description="Client-facing secondary color"),
     *             @OA\Property(property="client_tertiary_color", type="string", example="#3357FF", description="Client-facing tertiary color"),
     *             @OA\Property(property="user_review_report", type="boolean", example=true, description="Enable reports for registered user reviews"),
     *             @OA\Property(property="guest_user_review_report", type="boolean", example=true, description="Enable reports for guest user reviews"),
     *             @OA\Property(property="pin", type="string", example="1234", description="Security PIN"),
     *             @OA\Property(property="time_zone", type="string", example="UTC", description="Business timezone"),
     *             @OA\Property(property="is_guest_user_overall_review", type="boolean", example=true, description="Allow overall review from guests"),
     *             @OA\Property(property="is_guest_user_survey", type="boolean", example=true, description="Enable survey for guests"),
     *             @OA\Property(property="is_guest_user_survey_required", type="boolean", example=false, description="Make guest survey required"),
     *             @OA\Property(property="is_guest_user_show_stuffs", type="boolean", example=true, description="Show staff selection to guests"),
     *             @OA\Property(property="is_guest_user_show_stuff_image", type="boolean", example=true, description="Show staff images to guests"),
     *             @OA\Property(property="is_guest_user_show_stuff_name", type="boolean", example=true, description="Show staff names to guests"),
     *             @OA\Property(property="is_registered_user_overall_review", type="boolean", example=true, description="Allow overall review from registered users"),
     *             @OA\Property(property="is_registered_user_survey", type="boolean", example=true, description="Enable survey for registered users"),
     *             @OA\Property(property="is_registered_user_survey_required", type="boolean", example=false, description="Make registered user survey required"),
     *             @OA\Property(property="is_registered_user_show_stuffs", type="boolean", example=true, description="Show staff selection to registered users"),
     *             @OA\Property(property="is_registered_user_show_stuff_image", type="boolean", example=true, description="Show staff images to registered users"),
     *             @OA\Property(property="is_registered_user_show_stuff_name", type="boolean", example=true, description="Show staff names to registered users"),
     *             @OA\Property(property="enable_ip_check", type="boolean", example=false, description="Enable IP address verification"),
     *             @OA\Property(property="enable_location_check", type="boolean", example=true, description="Enable location verification"),
     *             @OA\Property(property="latitude", type="number", format="float", example=40.7128, description="Business latitude coordinate"),
     *             @OA\Property(property="longitude", type="number", format="float", example=-74.0060, description="Business longitude coordinate"),
     *             @OA\Property(property="review_distance_limit", type="number", format="float", example=1.5, description="Maximum distance (km) to allow reviews"),
     *             @OA\Property(property="threshold_rating", type="number", format="float", example=3.5, description="Minimum rating threshold for public reviews"),
     *             @OA\Property(
     *                 property="review_labels",
     *                 type="array",
     *                 description="Custom review labels",
     *                 @OA\Items(type="string", example="Excellent Service")
     *             ),
     *             @OA\Property(property="guest_survey_id", type="integer", nullable=true, example=1, description="ID of survey for guest users"),
     *             @OA\Property(property="registered_user_survey_id", type="integer", nullable=true, example=2, description="ID of survey for registered users")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Business updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Business updated successfully"),
     *             @OA\Property(property="business", type="object", description="Updated business object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent()
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Unprocessable Content",
     *         @OA\JsonContent()
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="This is not your business",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This is not your business")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No Business Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No Business Found")
     *         )
     *     )
     * )
     */


    public function UpdateBusiness($businessId, UpdateBusinessRequest $request)
    {

        // Validate
        $request_payload = $request->validated();

        // Check Business
        $business = Business::findOrFail($businessId);

        // Check Ownership or Super admin
        if ($business->OwnerID != $request->user()->id && !$request->user()->hasRole("superadmin")) {
            throw new AccessDeniedHttpException('This business does not belong to you');
        }

        // GET USER WITH RELATIONSHIP
        $user = User::with('business', 'branch')->findOrFail($business->OwnerID);

        // SET DEFAULT_BRANCH_ID BASED ON ROLE
        if ($user->hasRole('branch_manager')) {
            // CHECK IF USER HAS A BRANCH ASSIGNED TO THEM
            if (!$user->branch) {
                throw new AuthorizationException('You do not have a branch assigned to you');
            }
            $user->setAttribute('default_branch_id', $user->branch?->branch_id ?? null);
        } else if ($user->hasRole('business_owner')) {
            $user->setAttribute('default_branch_id', $user->business?->default_branch_id ?? null);
            $user->setAttribute('branch_count', $user->business?->branches->count() ?? null);
        }

        // SET TOKEN AND PERMISSION
        $user->setAttribute('token', $user->createToken('authToken')->accessToken);
        $user->setAttribute('permissions', $user->getAllPermissions()->pluck('name'));
        // SET ROLE AS SINGLE OBJECT
        $user->setAttribute('role', $user->roles->first());

        // Update and handle subscription history tracking
        \Illuminate\Support\Facades\DB::transaction(function () use ($business, $request_payload) {
            $newPlanId = $request_payload['service_plan_id'] ?? null;
            $currentPlanId = $business->service_plan_id;

            if ($newPlanId && $newPlanId != $currentPlanId) {
                // 1. Mark previous active entries as canceled (History)
                \App\Models\BusinessSubscription::where('business_id', $business->id)
                    ->where('status', 'active')
                    ->update(['status' => 'canceled']);

                // 2. Fetch the new plan details
                $newPlan = \App\Models\ServicePlan::find($newPlanId);

                if ($newPlan) {
                    // 3. Determine end date. Prioritize the passed trial_end_date, otherwise use duration.
                    $endDate = null;
                    if (!empty($request_payload['trial_end_date'])) {
                        $endDate = \Carbon\Carbon::parse($request_payload['trial_end_date']);
                    } else {
                        $durationMonths = $newPlan->duration_months ?: 1;
                        $endDate = now()->addMonths($durationMonths);
                    }

                    // 4. Create new subscription entry
                    \App\Models\BusinessSubscription::create([
                        'business_id' => $business->id,
                        'service_plan_id' => $newPlan->id,
                        'start_date' => now(),
                        'end_date' => $endDate,
                        'status' => 'active',
                        'amount' => 0,
                        'paid_at' => null,
                        'stripe_status' => 'manual',
                        'openai_token_limit' => $newPlan->openai_token_limit,
                    ]);
                }
            } else {
                // Plan is the SAME (or not provided in payload). If trial_end_date is provided, we just extend the current subscription.
                if (!empty($request_payload['trial_end_date'])) {
                    $latestSub = \App\Models\BusinessSubscription::where('business_id', $business->id)
                        ->where('service_plan_id', $currentPlanId)
                        ->whereIn('status', ['active', 'expired', 'canceled'])
                        ->orderByDesc('end_date')
                        ->first();

                    if ($latestSub) {
                        $latestSub->update([
                            'end_date' => \Carbon\Carbon::parse($request_payload['trial_end_date']),
                            'status' => 'active'
                        ]);
                    } else {
                        // If they literally have no subscription history for this plan, create a manual one
                        $currentPlan = \App\Models\ServicePlan::find($currentPlanId);
                        if ($currentPlan) {
                            \App\Models\BusinessSubscription::create([
                                'business_id' => $business->id,
                                'service_plan_id' => $currentPlan->id,
                                'start_date' => now(),
                                'end_date' => \Carbon\Carbon::parse($request_payload['trial_end_date']),
                                'status' => 'active',
                                'amount' => 0,
                                'paid_at' => null,
                                'stripe_status' => 'manual',
                                'openai_token_limit' => $currentPlan->openai_token_limit,
                            ]);
                        }
                    }
                }
            }
            // Nullify trial_end_date for the business table since it is now managed via subscriptions
            if (array_key_exists('trial_end_date', $request_payload)) {
                $request_payload['trial_end_date'] = null;
            }

            // Update the business record
            $business->update($request_payload);
        });

        // Return
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => $user
        ], 200);
    }

    // ##################################################
    // This method is to update default branch
    // ##################################################
    /**
     * @OA\Patch(
     *     path="/v1.0/business/default-branch",
     *     operationId="updateDefaultBranch",
     *     tags={"business_management"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     summary="Update default branch for the authenticated user's business",
     *     description="Update the default branch ID for the business owned by the authenticated user. The business is determined by the user's ownership.",

     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="default_branch_id", type="integer", nullable=true, example=1, description="ID of the default branch (null to remove default branch)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Default branch updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Default branch updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                 @OA\Property(property="default_branch_id", type="integer", nullable=true, example=1)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),

     *     @OA\Response(
     *         response=404,
     *         description="Business or branch not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Business not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function updateDefaultBranch(Request $request)
    {
        // Validate request
        $request->validate([
            'default_branch_id' => 'nullable|integer|exists:branches,id'
        ]);

        // Get the authenticated user
        $user = $request->user();

        // Find business owned by this user
        $business = Business::findOrFail($user->business_id);

        // If default_branch_id is provided, ensure it belongs to this business
        // if ($request->filled('default_branch_id')) {
        //     $branch = $business->branches()->find($request->default_branch_id);
        //     if (!$branch) {
        //         throw new NotFoundHttpException('Branch not found or does not belong to this business');
        //     }
        // }

        // Update only the default_branch_id field
        $business->update([
            'default_branch_id' => $request->default_branch_id
        ]);

        // Reload user with relationships
        $user = User::with('business', 'roles')->find($user->id);

        // Add default_branch_id to user response
        $user->setAttribute('default_branch_id', $business->default_branch_id);
        $user->setAttribute('branch_count', $user->business?->branches->count() ?? null);
        $user->setAttribute('role', $user->roles->first());

        // Generate new access token
        $user->setAttribute('token', $user->createToken('authToken')->accessToken);
        $user->setAttribute('permissions', $user->getAllPermissions()->pluck('name'));


        // Return success response with token and user data
        return response()->json([
            "success" => true,
            "message" => "Default branch updated successfully",
            "data" => $user
        ], 200);
    }

    // ##################################################
    // This method is to get business by id
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/v1.0/business/{businessId}",
     *      operationId="getBusinessById",
     *      tags={"business_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get business details by ID",
     *      description="Retrieve detailed information about a specific business including owner information",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Business found successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business found successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                  @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *                  @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *                  @OA\Property(property="EmailAddress", type="string", example="business@example.com"),
     *                  @OA\Property(property="PhoneNumber", type="string", example="+44123456789"),
     *                  @OA\Property(property="Webpage", type="string", example="https://example.com"),
     *                  @OA\Property(property="About", type="string", example="About the business"),
     *                  @OA\Property(property="Logo", type="string", example="img/business/logo.png"),
     *                  @OA\Property(property="Status", type="string", example="Active"),
     *                  @OA\Property(property="Key_ID", type="string", example="abc123"),
     *                  @OA\Property(property="OwnerID", type="integer", example=1),

     *                  @OA\Property(property="expiry_date", type="string", format="date", example="15-12-2025"),
     *                  @OA\Property(property="created_at", type="string", format="datetime", example="2025-01-01T12:00:00.000000Z"),
     *                  @OA\Property(property="updated_at", type="string", format="datetime", example="2025-01-15T12:00:00.000000Z"),
     *                  @OA\Property(
     *                      property="owner",
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="John Doe"),
     *                      @OA\Property(property="email", type="string", example="owner@example.com")
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      )
     * )
     */
    public function getBusinessById($businessId)
    {

        // GET BUSINESS WITH OWNER AND DEFAULT BRANCH
        $business = Business::with(["owner", "defaultBranch"])
            ->where("id", $businessId)
            ->first();

        // CHECK IF BUSINESS EXISTS
        if (!$business) {
            throw new NotFoundHttpException('Business not found');
        }

        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business found successfully",
            "data" => $business
        ], 200);
    }



    // ##################################################
    // This method is to get all businesses
    // ##################################################
    /**
     *
     * @OA\Get(
     *      path="/v1.0/business",
     *      operationId="getAllBusinesses",
     *      tags={"business_management"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get all businesses with optional filters and pagination",
     *      description="Retrieve a list of all businesses with optional search and filtering capabilities. Supports both paginated and non-paginated responses.",
     *
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="Search term to filter businesses by name",
     *          required=false,
     *          example="Restaurant",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="Status",
     *          in="query",
     *          description="Filter by business status (Active, Inactive)",
     *          required=false,
     *          example="Active",
     *          @OA\Schema(type="string", enum={"Active", "Inactive"})
     *      ),
     *
     *      @OA\Parameter(
     *          name="owner_id",
     *          in="query",
     *          description="Filter by owner ID",
     *          required=false,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Number of items per page (if pagination is required)",
     *          required=false,
     *          example="15",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number for pagination",
     *          required=false,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          description="sort by field name",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_order",
     *          in="query",
     *          description="sort order asc or desc order",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="search key only allowed Name and email",
     *          required=false,
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="period",
     *          in="query",
     *          description="Time period for metrics",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              enum={"last_7_days", "last_30_days", "last_90_days", "this_week", "last_week", "this_month", "last_month", "this_quarter", "last_quarter", "this_year", "last_year"},
     *              default="last_30_days"
     *          )
     *      ),
     *
     *      @OA\Parameter(
     *          name="is_active",
     *          in="query",
     *          description="search key only allowed Name and email",
     *          required=false,
     *          @OA\Schema(type="boolean")
     *      ),
     *
     *      @OA\Parameter(
     *          name="response_type",
     *          in="query",
     *          description="Export format (PDF, CSV, or JSON by default)",
     *          required=false,
     *          example="json",
     *          @OA\Schema(type="string", enum={"json", "pdf", "csv"})
     *      ),
     *
     *      @OA\Parameter(
     *          name="file_name",
     *          in="query",
     *          description="Custom filename for PDF/CSV export (without extension)",
     *          required=false,
     *          example="businesses_export",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Businesses retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Businesses retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  description="Array of business objects",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                      @OA\Property(property="Address", type="string", example="123 Main Street"),
     *                      @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *                      @OA\Property(property="Status", type="string", example="Active"),
     *                      @OA\Property(property="OwnerID", type="integer", example=1),

     *                      @OA\Property(
     *                          property="owner",
     *                          type="object",
     *                          @OA\Property(property="id", type="integer", example=1),
     *                          @OA\Property(property="name", type="string", example="John Doe"),
     *                          @OA\Property(property="email", type="string", example="owner@example.com")
     *                      )
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="meta",
     *                  type="object",
     *                  description="Pagination metadata (only when per_page is provided)",
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="from", type="integer", example=1),
     *                  @OA\Property(property="to", type="integer", example=15),
     *                  @OA\Property(property="per_page", type="integer", example=15),
     *                  @OA\Property(property="last_page", type="integer", example=5),
     *                  @OA\Property(property="total", type="integer", example=75),
     *                  @OA\Property(property="path", type="string", example="http://api.example.com/v1.0/business"),
     *                  @OA\Property(property="first_page_url", type="string", example="http://api.example.com/v1.0/business?page=1"),
     *                  @OA\Property(property="last_page_url", type="string", example="http://api.example.com/v1.0/business?page=5"),
     *                  @OA\Property(property="next_page_url", type="string", nullable=true, example="http://api.example.com/v1.0/business?page=2"),
     *                  @OA\Property(property="prev_page_url", type="string", nullable=true, example=null)
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      )
     * )
     */
    public function getAllBusinesses(Request $request)
    {
        // BUILD QUERY WITH FILTER SCOPE
        $businessQuery = Business::withTrashed()->with("owner")->filter();

        // HANDLE PDF/CSV EXPORT
        if ($request->filled('response_type') && in_array(strtoupper($request->response_type), ['PDF', 'CSV'])) {
            $businesses = $businessQuery->get();

            if (strtoupper($request->response_type) == 'PDF') {
                if ($businesses->isEmpty()) {
                    $pdf = PDF::loadView('pdf.no_data', []);
                } else {
                    $pdf = PDF::loadView('pdf.businesses', ["businesses" => $businesses]);
                }
                return $pdf->download(($request->input('file_name', 'businesses') . '.pdf'));
            } elseif (strtoupper($request->response_type) === 'CSV') {
                return Excel::download(
                    new BusinessExport($businesses),
                    ($request->input('file_name', 'businesses') . '.csv')
                );
            }
        }

        // USE RETRIEVE_DATA HELPER FOR PAGINATION AND DATA RETRIEVAL
        $result = retrieve_data($businessQuery);

        return response()->json([
            "success" => true,
            "message" => "Businesses retrieved successfully",
            "data" => $result['data'],
            "meta" => $result['meta']
        ], 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/client/businesses",
     *      operationId="getBusinessesClients",
     *      tags={"business_management.client"},
     *      security={
     *          {"bearerAuth": {}}
     *      },
     *      summary="Get businesses for client view with ratings and timing",
     *      description="Retrieve businesses with calculated ratings, timing information, and optional pagination",
     *
     *      @OA\Parameter(
     *          name="per_page",
     *          in="query",
     *          description="Number of items per page (if pagination is required)",
     *          required=false,
     *          example="15",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="page",
     *          in="query",
     *          description="Page number for pagination",
     *          required=false,
     *          example="1",
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_by",
     *          in="query",
     *          description="Field to sort by (e.g., Name, Address, created_at)",
     *          required=false,
     *          example="Name",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="sort_type",
     *          in="query",
     *          description="Sort direction (asc or desc)",
     *          required=false,
     *          example="asc",
     *          @OA\Schema(type="string", enum={"asc", "desc"})
     *      ),
     *
     *      @OA\Parameter(
     *          name="search_key",
     *          in="query",
     *          description="Search term to filter businesses by name",
     *          required=false,
     *          example="Restaurant",
     *          @OA\Schema(type="string")
     *      ),
     *
     *      @OA\Parameter(
     *          name="start_date",
     *          in="query",
     *          description="Start date for filtering reviews (Y-m-d format)",
     *          required=false,
     *          example="2025-01-01",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Parameter(
     *          name="end_date",
     *          in="query",
     *          description="End date for filtering reviews (Y-m-d format)",
     *          required=false,
     *          example="2025-12-31",
     *          @OA\Schema(type="string", format="date")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Businesses retrieved successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Businesses retrieved successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                      @OA\Property(property="header_image", type="string", example="/header_image/default.png"),
     *                      @OA\Property(property="rating_page_image", type="string", example="/rating_image/default.png"),
     *                      @OA\Property(property="placeholder_image", type="string", example="/placeholder/default.png"),
     *                      @OA\Property(property="Logo", type="string", example="img/business/logo.png"),
     *                      @OA\Property(property="Address", type="string", example="123 Main Street"),
     *                      @OA\Property(property="average_rating", type="number", format="float", example=4.5),
     *                      @OA\Property(property="total_rating_count", type="integer", example=120),
     *                      @OA\Property(property="out_of", type="integer", example=5),
     *                      @OA\Property(
     *                          property="timing",
     *                          type="object",
     *                          description="Business timing for current day",
     *                          @OA\Property(property="day", type="integer", example=1),
     *                          @OA\Property(
     *                              property="timeSlots",
     *                              type="array",
     *                              @OA\Items(
     *                                  type="object",
     *                                  @OA\Property(property="start_time", type="string", example="09:00"),
     *                                  @OA\Property(property="end_time", type="string", example="17:00")
     *                              )
     *                          )
     *                      )
     *                  )
     *              ),
     *              @OA\Property(
     *                  property="meta",
     *                  type="object",
     *                  description="Pagination metadata (only when per_page is provided)",
     *                  @OA\Property(property="current_page", type="integer", example=1),
     *                  @OA\Property(property="from", type="integer", example=1),
     *                  @OA\Property(property="to", type="integer", example=15),
     *                  @OA\Property(property="per_page", type="integer", example=15),
     *                  @OA\Property(property="last_page", type="integer", example=5),
     *                  @OA\Property(property="total", type="integer", example=75),
     *                  @OA\Property(property="path", type="string", example="http://api.example.com/client/businesses"),
     *                  @OA\Property(property="first_page_url", type="string", example="http://api.example.com/client/businesses?page=1"),
     *                  @OA\Property(property="last_page_url", type="string", example="http://api.example.com/client/businesses?page=5"),
     *                  @OA\Property(property="next_page_url", type="string", nullable=true, example="http://api.example.com/client/businesses?page=2"),
     *                  @OA\Property(property="prev_page_url", type="string", nullable=true, example=null)
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      )
     * )
     */
    public function getBusinessesClients(Request $request)
    {
        // Get today's day (0 for Sunday, 1 for Monday, ..., 6 for Saturday)
        $today = Carbon::now()->dayOfWeek;

        // BUILD QUERY WITH FILTER SCOPE
        $businessQuery = Business::filterClients();

        // USE RETRIEVE_DATA HELPER FOR PAGINATION AND DATA RETRIEVAL
        $result = retrieve_data($businessQuery);

        // Transform collection with ratings and timing
        $result['data'] = collect($result['data'])->map(function ($business) use ($today, $request) {
            return $this->businessProfileService->enrichBusinessWithRatingsAndTiming($business, $today, $request);
        })->toArray();

        return response()->json([
            "success" => true,
            "message" => "Businesses retrieved successfully",
            "data" => $result['data'],
            "meta" => $result['meta']
        ], 200);
    }

    /**
     *
     * @OA\Get(
     *      path="/v1.0/client/business/{businessId}",
     *      operationId="getClientBusinessById",
     *      tags={"business_management.client"},
     *      summary="Get business details by ID",
     *      description="Retrieve detailed information about a specific business including owner information",
     *
     *      @OA\Parameter(
     *          name="businessId",
     *          in="path",
     *          description="Business ID",
     *          required=true,
     *          @OA\Schema(type="integer")
     *      ),
     *
     *      @OA\Response(
     *          response=200,
     *          description="Business found successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Business found successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(property="id", type="integer", example=1),
     *                  @OA\Property(property="Name", type="string", example="My Restaurant"),
     *                  @OA\Property(property="Address", type="string", example="123 Main Street, City"),
     *                  @OA\Property(property="PostCode", type="string", example="SW1A 1AA"),
     *                  @OA\Property(property="EmailAddress", type="string", example="business@example.com"),
     *                  @OA\Property(property="PhoneNumber", type="string", example="+44123456789"),
     *                  @OA\Property(property="Webpage", type="string", example="https://example.com"),
     *                  @OA\Property(property="About", type="string", example="About the business"),
     *                  @OA\Property(property="Logo", type="string", example="img/business/logo.png"),
     *                  @OA\Property(property="Status", type="string", example="Active"),
     *                  @OA\Property(property="Key_ID", type="string", example="abc123"),
     *                  @OA\Property(property="OwnerID", type="integer", example=1),

     *                  @OA\Property(property="expiry_date", type="string", format="date", example="15-12-2025"),
     *                  @OA\Property(property="created_at", type="string", format="datetime", example="2025-01-01T12:00:00.000000Z"),
     *                  @OA\Property(property="updated_at", type="string", format="datetime", example="2025-01-15T12:00:00.000000Z"),
     *                  @OA\Property(
     *                      property="owner",
     *                      type="object",
     *                      @OA\Property(property="id", type="integer", example=1),
     *                      @OA\Property(property="name", type="string", example="John Doe"),
     *                      @OA\Property(property="email", type="string", example="owner@example.com")
     *                  )
     *              )
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=401,
     *          description="Unauthenticated",
     *          @OA\JsonContent(
     *              @OA\Property(property="message", type="string", example="Unauthenticated")
     *          )
     *      ),
     *
     *      @OA\Response(
     *          response=404,
     *          description="Business not found",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=false),
     *              @OA\Property(property="message", type="string", example="Business not found")
     *          )
     *      )
     * )
     */
    public function getClientBusinessById($businessId)
    {

        // GET BUSINESS WITH OWNER AND DEFAULT BRANCH
        $business = Business::with([
            "owner" => function ($query) {
                $query->select("id", "first_Name", "last_Name", "email");
            },
            "defaultBranch" => function ($query) {
                $query->select("id", "name");
            }
        ])->findOrFail($businessId);


        // RETURN RESPONSE
        return response()->json([
            "success" => true,
            "message" => "Business found successfully",
            "data" => $business
        ], 200);
    }



    /**
     * @OA\Patch(
     *     path="/v1.0/business/setup-progress",
     *     operationId="updateSetupProgress",
     *     tags={"business_management"},
     *     security={
     *         {"bearerAuth": {}}
     *     },
     *     summary="Update business setup progress",
     *     description="Update the is_setup_complete and step_no fields for the current user's business",
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="is_setup_complete", type="boolean", example=true),
     *             @OA\Property(property="step_no", type="integer", example=2)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Setup progress updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Setup progress updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Business not found")
     * )
     */
    public function updateSetupProgress(Request $request)
    {
        $user = $request->user();

        if (!$user->business_id) {
            throw new NotFoundHttpException("Business not found for this user");
        }

        $business = Business::findOrFail($user->business_id);

        // CHECK OWNERSHIP (Safety check, though business_id usually implies ownership/association)
        if ($business->OwnerID != $user->id && !$user->hasRole("superadmin")) {
            throw new AccessDeniedHttpException('You do not have permission to update this business');
        }

        $request->validate([
            'is_setup_complete' => 'nullable|boolean',
            'step_no' => 'nullable|integer',
        ]);

        $business->update($request->only(['is_setup_complete', 'step_no']));

        return response()->json([
            "success" => true,
            "message" => "Setup progress updated successfully",
            "data" => $business
        ], 200);
    }

    /**
     * Bulk permanent delete businesses (Super Admin only)
     * Ported from HRM logic
     */
    public function forceDeleteBusinessesByIds(Request $request, $ids)
    {
        if (!$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "You do not have permission to perform this action"], 403);
        }

        $idsArray = explode(',', $ids);
        $businesses = Business::withTrashed()->whereIn('id', $idsArray)->get();

        foreach ($businesses as $business) {
            DB::transaction(function () use ($business) {
                $businessId = $business->id;



                // 1. Delete CHILD tables and related Review-specific data
                \App\Models\Recommendation::where("business_id", $businessId)->delete();
                \App\Models\AiRule::where("business_id", $businessId)->delete();
                DB::table("ai_rule_triggers")->where("business_id", $businessId)->delete();
                \App\Models\InsightRecord::where("business_id", $businessId)->delete();
                \App\Models\OpenAITokenUsage::where("business_id", $businessId)->delete();
                \App\Models\Leaflet::where("business_id", $businessId)->delete();
                \App\Models\ReviewNew::where("business_id", $businessId)->delete();
                \App\Models\DailyView::where("business_id", $businessId)->delete();
                DB::table("surveys")->where("business_id", $businessId)->delete();
                DB::table("branches")->where("business_id", $businessId)->delete();

                // 2. Delete staff and associated users
                User::where("business_id", $businessId)->delete();

                // 3. Force delete the business record
                $business->forceDelete();

                // 4. Delete the business owner
                User::where("id", $business->OwnerID)->delete();


                // 4. Delete the owner
                User::where("id", $business->OwnerID)->delete();
            });
        }

        return response()->json([
            "success" => true,
            "message" => "Businesses permanently deleted successfully"
        ], 200);
    }

    /**
     * Bulk restore soft-deleted businesses (Super Admin only)
     * Ported from HRM logic
     */
    public function restoreBusinessesByIds(Request $request, $ids)
    {
        if (!$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "You do not have permission to perform this action"], 403);
        }

        $idsArray = explode(',', $ids);
        $businesses = Business::onlyTrashed()->whereIn('id', $idsArray)->get();

        foreach ($businesses as $business) {
            $business->restore();
            // Note: Users are not soft-deleted in Review, so we only restore the business
        }

        return response()->json([
            "success" => true,
            "message" => "Businesses restored successfully"
        ], 200);
    }

    /**
     * Get all soft-deleted businesses (Super Admin only)
     */
    public function getSoftDeletedBusinesses(Request $request)
    {
        if (!$request->user()->hasRole("superadmin")) {
            return response()->json(["message" => "You do not have permission to perform this action"], 403);
        }

        $businessQuery = Business::onlyTrashed()->with("owner");

        if ($request->filled('search_key')) {
            $searchTerm = $request->input('search_key');
            $businessQuery->where(function ($q) use ($searchTerm) {
                $q->where('Name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('EmailAddress', 'like', '%' . $searchTerm . '%');
            });
        }

        $result = retrieve_data($businessQuery);

        return response()->json([
            "success" => true,
            "message" => "Soft-deleted businesses retrieved successfully",
            "data" => $result['data'],
            "meta" => $result['meta']
        ], 200);
    }
}
