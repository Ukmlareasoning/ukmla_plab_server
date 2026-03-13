<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webinar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class WebinarController extends Controller
{
    /**
     * List webinars with optional text and status filters.
     * Query params: text, status, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = Webinar::query()->withTrashed();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('event_title', 'like', $searchTerm)
                        ->orWhere('description', 'like', $searchTerm)
                        ->orWhere('id', 'like', $searchTerm);
                });
            }

            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } elseif (in_array($status, ['Active', 'Inactive'], true)) {
                    $query->where('status', $status);
                }
            }
        }

        $webinars = $query->orderBy('id', 'asc')->paginate($perPage);

        $items = collect($webinars->items())->map(function (Webinar $webinar) {
            $bannerUrl = null;
            if ($webinar->banner_image) {
                $bannerUrl = url($webinar->banner_image);
            }

            return [
                'id' => $webinar->id,
                'eventTitle' => $webinar->event_title,
                'description' => $webinar->description,
                'startDate' => $webinar->start_date?->toDateString(),
                'endDate' => $webinar->end_date?->toDateString(),
                'startTime' => $webinar->start_time,
                'endTime' => $webinar->end_time,
                'presence' => $webinar->presence,
                'zoomMeetingLink' => $webinar->zoom_meeting_link,
                'address' => $webinar->address,
                'price' => (float) $webinar->price,
                'maxAttendees' => $webinar->max_attendees,
                'bannerImage' => $bannerUrl,
                'status' => $webinar->deleted_at ? 'Deleted' : $webinar->status,
                'is_deleted' => (bool) $webinar->deleted_at,
                'created_at' => $webinar->created_at?->toIso8601String(),
                'updated_at' => $webinar->updated_at?->toIso8601String(),
                'deleted_at' => $webinar->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Webinars retrieved successfully.',
            'data' => [
                'webinars' => $items,
                'pagination' => [
                    'current_page' => $webinars->currentPage(),
                    'last_page' => $webinars->lastPage(),
                    'per_page' => $webinars->perPage(),
                    'total' => $webinars->total(),
                    'from' => $webinars->firstItem(),
                    'to' => $webinars->lastItem(),
                    'prev_page_url' => $webinars->previousPageUrl(),
                    'next_page_url' => $webinars->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a new webinar.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'presence' => ['required', 'in:Online,Onsite'],
            'zoom_meeting_link' => ['nullable', 'url', 'max:512'],
            'address' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'max_attendees' => ['required', 'integer', 'min:1'],
            'banner_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'event_title.required' => 'Event title is required.',
            'event_title.max' => 'Event title must not exceed 191 characters.',
            'description.required' => 'Description is required.',
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to the start date.',
            'start_time.required' => 'Start time is required.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.required' => 'End time is required.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'presence.required' => 'Presence option is required.',
            'presence.in' => 'Presence must be either Online or Onsite.',
            'zoom_meeting_link.url' => 'Meeting link must be a valid URL.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
            'max_attendees.required' => 'Max attendees is required.',
            'max_attendees.integer' => 'Max attendees must be an integer.',
            'max_attendees.min' => 'Max attendees must be at least 1.',
            'banner_image.required' => 'Banner image is required.',
            'banner_image.image' => 'Banner must be an image file.',
            'banner_image.mimes' => 'Banner image must be a JPG, JPEG, PNG, or WEBP file.',
            'banner_image.max' => 'Banner image must not be larger than 5MB.',
        ]);

        $validator->after(function ($v) use ($request) {
            $presence = $request->input('presence');
            if ($presence === 'Online' && !$request->filled('zoom_meeting_link')) {
                $v->errors()->add('zoom_meeting_link', 'Meeting link is required for online webinars.');
            }
            if ($presence === 'Onsite' && !$request->filled('address')) {
                $v->errors()->add('address', 'Address is required for onsite webinars.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $bannerPath = null;
        if ($request->hasFile('banner_image')) {
            $publicDir = public_path('assets/webinars');
            if (!File::exists($publicDir)) {
                File::makeDirectory($publicDir, 0755, true);
            }
            $file = $request->file('banner_image');
            $filename = uniqid('webinar_') . '.' . $file->getClientOriginalExtension();
            $file->move($publicDir, $filename);
            $bannerPath = 'assets/webinars/' . $filename;
        }

        $webinar = Webinar::create([
            'event_title' => $request->input('event_title'),
            'description' => $request->input('description'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'presence' => $request->input('presence'),
            'zoom_meeting_link' => $request->input('zoom_meeting_link'),
            'address' => $request->input('address'),
            'price' => $request->input('price', 0),
            'max_attendees' => $request->input('max_attendees'),
            'banner_image' => $bannerPath,
            'status' => 'Active',
        ]);

        $bannerUrl = $bannerPath ? url($bannerPath) : null;

        return response()->json([
            'success' => true,
            'message' => 'Webinar created successfully.',
            'data' => [
                'webinar' => [
                    'id' => $webinar->id,
                    'eventTitle' => $webinar->event_title,
                    'description' => $webinar->description,
                    'startDate' => $webinar->start_date?->toDateString(),
                    'endDate' => $webinar->end_date?->toDateString(),
                    'startTime' => $webinar->start_time,
                    'endTime' => $webinar->end_time,
                    'presence' => $webinar->presence,
                    'zoomMeetingLink' => $webinar->zoom_meeting_link,
                    'address' => $webinar->address,
                    'price' => (float) $webinar->price,
                    'maxAttendees' => $webinar->max_attendees,
                    'bannerImage' => $bannerUrl,
                    'status' => $webinar->status,
                    'is_deleted' => false,
                    'created_at' => $webinar->created_at?->toIso8601String(),
                    'updated_at' => $webinar->updated_at?->toIso8601String(),
                ],
            ],
        ], 201);
    }

    /**
     * Update an existing webinar.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $webinar = Webinar::withTrashed()->find($id);

        if (!$webinar) {
            return response()->json([
                'success' => false,
                'message' => 'Webinar not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'event_title' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'presence' => ['required', 'in:Online,Onsite'],
            'zoom_meeting_link' => ['nullable', 'url', 'max:512'],
            'address' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'max_attendees' => ['nullable', 'integer', 'min:1'],
            'banner_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'status' => ['nullable', 'in:Active,Inactive'],
        ], [
            'event_title.required' => 'Event title is required.',
            'event_title.max' => 'Event title must not exceed 191 characters.',
            'description.required' => 'Description is required.',
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to the start date.',
            'start_time.required' => 'Start time is required.',
            'start_time.date_format' => 'Start time must be in HH:MM format.',
            'end_time.required' => 'End time is required.',
            'end_time.date_format' => 'End time must be in HH:MM format.',
            'presence.required' => 'Presence option is required.',
            'presence.in' => 'Presence must be either Online or Onsite.',
            'zoom_meeting_link.url' => 'Meeting link must be a valid URL.',
            'price.numeric' => 'Price must be a valid number.',
            'price.min' => 'Price cannot be negative.',
            'max_attendees.integer' => 'Max attendees must be an integer.',
            'max_attendees.min' => 'Max attendees must be at least 1.',
            'banner_image.image' => 'Banner must be an image file.',
            'banner_image.mimes' => 'Banner image must be a JPG, JPEG, PNG, or WEBP file.',
            'banner_image.max' => 'Banner image must not be larger than 5MB.',
            'status.in' => 'Status must be either Active or Inactive.',
        ]);

        $validator->after(function ($v) use ($request) {
            $presence = $request->input('presence');
            if ($presence === 'Online' && !$request->filled('zoom_meeting_link')) {
                $v->errors()->add('zoom_meeting_link', 'Meeting link is required for online webinars.');
            }
            if ($presence === 'Onsite' && !$request->filled('address')) {
                $v->errors()->add('address', 'Address is required for onsite webinars.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $webinar->event_title = $request->input('event_title');
        $webinar->description = $request->input('description');
        $webinar->start_date = $request->input('start_date');
        $webinar->end_date = $request->input('end_date');
        $webinar->start_time = $request->input('start_time');
        $webinar->end_time = $request->input('end_time');
        $webinar->presence = $request->input('presence');
        $webinar->zoom_meeting_link = $request->input('zoom_meeting_link');
        $webinar->address = $request->input('address');
        $webinar->price = $request->input('price', 0);
        $webinar->max_attendees = $request->input('max_attendees');

        if ($request->filled('status')) {
            $webinar->status = $request->input('status');
        }

        if ($request->hasFile('banner_image')) {
            if ($webinar->banner_image && !str_starts_with($webinar->banner_image, 'http')) {
                $existingPath = public_path($webinar->banner_image);
                if (File::exists($existingPath)) {
                    File::delete($existingPath);
                }
            }

            $publicDir = public_path('assets/webinars');
            if (!File::exists($publicDir)) {
                File::makeDirectory($publicDir, 0755, true);
            }
            $file = $request->file('banner_image');
            $filename = uniqid('webinar_') . '.' . $file->getClientOriginalExtension();
            $file->move($publicDir, $filename);
            $webinar->banner_image = 'assets/webinars/' . $filename;
        }

        $webinar->save();

        $bannerUrl = $webinar->banner_image ? url($webinar->banner_image) : null;

        return response()->json([
            'success' => true,
            'message' => 'Webinar updated successfully.',
            'data' => [
                'webinar' => [
                    'id' => $webinar->id,
                    'eventTitle' => $webinar->event_title,
                    'description' => $webinar->description,
                    'startDate' => $webinar->start_date?->toDateString(),
                    'endDate' => $webinar->end_date?->toDateString(),
                    'startTime' => $webinar->start_time,
                    'endTime' => $webinar->end_time,
                    'presence' => $webinar->presence,
                    'zoomMeetingLink' => $webinar->zoom_meeting_link,
                    'address' => $webinar->address,
                    'price' => (float) $webinar->price,
                    'maxAttendees' => $webinar->max_attendees,
                    'bannerImage' => $bannerUrl,
                    'status' => $webinar->deleted_at ? 'Deleted' : $webinar->status,
                    'is_deleted' => (bool) $webinar->deleted_at,
                    'created_at' => $webinar->created_at?->toIso8601String(),
                    'updated_at' => $webinar->updated_at?->toIso8601String(),
                    'deleted_at' => $webinar->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete a webinar.
     */
    public function destroy(int $id): JsonResponse
    {
        $webinar = Webinar::find($id);

        if (!$webinar) {
            $webinar = Webinar::onlyTrashed()->find($id);
        }

        if (!$webinar) {
            return response()->json([
                'success' => false,
                'message' => 'Webinar not found.',
            ], 404);
        }

        if ($webinar->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Webinar is already deleted.',
                'data' => [
                    'webinar' => [
                        'id' => $webinar->id,
                        'eventTitle' => $webinar->event_title,
                        'description' => $webinar->description,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $webinar->created_at?->toIso8601String(),
                        'updated_at' => $webinar->updated_at?->toIso8601String(),
                        'deleted_at' => $webinar->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $webinar->delete();

        return response()->json([
            'success' => true,
            'message' => 'Webinar deleted successfully.',
            'data' => [
                'webinar' => [
                    'id' => $webinar->id,
                    'eventTitle' => $webinar->event_title,
                    'description' => $webinar->description,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $webinar->created_at?->toIso8601String(),
                    'updated_at' => $webinar->updated_at?->toIso8601String(),
                    'deleted_at' => $webinar->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft deleted webinar.
     */
    public function restore(int $id): JsonResponse
    {
        $webinar = Webinar::onlyTrashed()->find($id);

        if (!$webinar) {
            return response()->json([
                'success' => false,
                'message' => 'Webinar not found or not deleted.',
            ], 404);
        }

        $webinar->restore();

        return response()->json([
            'success' => true,
            'message' => 'Webinar restored successfully.',
            'data' => [
                'webinar' => [
                    'id' => $webinar->id,
                    'eventTitle' => $webinar->event_title,
                    'description' => $webinar->description,
                    'status' => $webinar->status,
                    'is_deleted' => false,
                    'created_at' => $webinar->created_at?->toIso8601String(),
                    'updated_at' => $webinar->updated_at?->toIso8601String(),
                    'deleted_at' => $webinar->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}

