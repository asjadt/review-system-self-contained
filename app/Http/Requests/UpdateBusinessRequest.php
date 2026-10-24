<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\ValidSurvey;

class UpdateBusinessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $business = $this->route('businessId')
            ? \App\Models\Business::find($this->route('businessId'))
            : null;

        if (!$business) {
            return false;
        }

        return $business->OwnerID === $this->user()->id || $this->user()->hasRole('superadmin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Name' => 'required|string|max:255',
            'Layout' => 'nullable|string',
            'Address' => 'required|string',
            'PostCode' => 'required|string',
            'show_image' => 'nullable|boolean',
            'review_type' => 'nullable|string',
            'google_map_iframe' => 'nullable|string',
            'Key_ID' => 'nullable|string',
            'Status' => 'nullable|string',
            'About' => 'nullable|string',
            'Webpage' => 'nullable|string',
            'PhoneNumber' => 'nullable|string',
            'EmailAddress' => 'nullable|email',
            'homeText' => 'nullable|string',
            'AdditionalInformation' => 'nullable|string',
            'GoogleMapApi' => 'nullable|string',
            'Is_guest_user' => 'nullable|boolean',
            'is_review_slider' => 'nullable|boolean',
            'review_only' => 'nullable|boolean',
            'is_branch' => 'nullable|boolean',
            'has_rule_management' => 'nullable|boolean',
            'is_treat_manager_as_staff' => 'nullable|boolean',
            'is_show_public_page_to_customer' => 'nullable|boolean',
            'is_sidebar_hidden' => 'nullable|boolean',
            'header_image' => 'nullable|string',
            'primary_color' => 'nullable|string',
            'secondary_color' => 'nullable|string',
            'client_primary_color' => 'nullable|string',
            'client_secondary_color' => 'nullable|string',
            'client_tertiary_color' => 'nullable|string',
            'user_review_report' => 'nullable|boolean',
            'guest_user_review_report' => 'nullable|boolean',
            'pin' => 'nullable|string',
            'is_report_email_enabled' => 'nullable|boolean',
            'time_zone' => 'nullable|string',
            'is_guest_user_overall_review' => 'nullable|boolean',
            'is_guest_user_survey' => 'nullable|boolean',
            'is_guest_user_survey_required' => 'nullable|boolean',
            'is_guest_user_show_stuffs' => 'nullable|boolean',
            'is_guest_user_show_stuff_image' => 'nullable|boolean',
            'is_guest_user_show_stuff_name' => 'nullable|boolean',
            'is_registered_user_overall_review' => 'nullable|boolean',
            'is_registered_user_survey' => 'nullable|boolean',
            'is_registered_user_survey_required' => 'nullable|boolean',
            'is_registered_user_show_stuffs' => 'nullable|boolean',
            'is_registered_user_show_stuff_image' => 'nullable|boolean',
            'is_registered_user_show_stuff_name' => 'nullable|boolean',
            'enable_ip_check' => 'nullable|boolean',
            'enable_location_check' => 'nullable|boolean',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'review_distance_limit' => 'nullable|numeric',
            'threshold_rating' => 'nullable|numeric',
            'review_labels' => 'nullable|string',
            'guest_survey_id' => ['nullable', 'integer', new ValidSurvey($this->route('businessId'))],
            'registered_user_survey_id' => ['nullable', 'integer', new ValidSurvey($this->route('businessId'))],

            'default_color_threshold' => 'present|array',
            'service_plan_id' => 'nullable|integer|exists:service_plans,id',
            'trial_end_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',

        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'Name.required' => 'Business name is required.',
            'Address.required' => 'Address is required.',
            'PostCode.required' => 'Post code is required.',

            'EmailAddress.email' => 'Please provide a valid email address.',
            'latitude.numeric' => 'Latitude must be a number.',
            'longitude.numeric' => 'Longitude must be a number.',
            'guest_survey_id' => 'The selected guest survey is invalid or does not belong to this business.',
            'registered_user_survey_id' => 'The selected registered user survey is invalid or does not belong to this business.',
        ];
    }
}
