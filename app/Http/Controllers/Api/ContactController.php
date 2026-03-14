<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * List contacts with optional text and status filters.
     * Query params: text, status, page, per_page, apply_filters
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;

        $query = Contact::query()->withTrashed();

        $applyFilters = filter_var($request->query('apply_filters', false), FILTER_VALIDATE_BOOLEAN);

        if ($applyFilters) {
            if ($text = $request->query('text')) {
                $searchTerm = '%' . $text . '%';
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('full_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhere('subject', 'like', $searchTerm)
                        ->orWhere('message', 'like', $searchTerm);
                });
            }

            if ($status = $request->query('status')) {
                if ($status === 'Deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->whereNull('deleted_at');
                }
            }
        }

        $contacts = $query->orderBy('id', 'desc')->paginate($perPage);

        $items = collect($contacts->items())->map(function (Contact $contact) {
            return [
                'id' => $contact->id,
                'full_name' => $contact->full_name,
                'email' => $contact->email,
                'subject' => $contact->subject,
                'message' => $contact->message,
                'reply_subject' => $contact->reply_subject,
                'reply_message' => $contact->reply_message,
                'replied_at' => $contact->replied_at?->toIso8601String(),
                'status' => $contact->deleted_at ? 'Deleted' : 'Active',
                'is_deleted' => (bool) $contact->deleted_at,
                'created_at' => $contact->created_at?->toIso8601String(),
                'updated_at' => $contact->updated_at?->toIso8601String(),
                'deleted_at' => $contact->deleted_at?->toIso8601String(),
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Contacts retrieved successfully.',
            'data' => [
                'contacts' => $items,
                'pagination' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                    'from' => $contacts->firstItem(),
                    'to' => $contacts->lastItem(),
                    'prev_page_url' => $contacts->previousPageUrl(),
                    'next_page_url' => $contacts->nextPageUrl(),
                ],
            ],
        ], 200);
    }

    /**
     * Store a reply for a contact (no email sent; managed from backend only).
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $contact = Contact::withTrashed()->find($id);

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'reply_subject' => ['required', 'string', 'max:191'],
            'reply_message' => ['required', 'string', 'max:65535'],
        ], [
            'reply_subject.required' => 'Reply subject is required.',
            'reply_subject.max' => 'Reply subject must not exceed 191 characters.',
            'reply_message.required' => 'Reply message is required.',
            'reply_message.max' => 'Reply message is too long.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $contact->reply_subject = $request->input('reply_subject');
        $contact->reply_message = $request->input('reply_message');
        $contact->replied_at = now();
        $contact->save();

        return response()->json([
            'success' => true,
            'message' => 'Reply saved successfully.',
            'data' => [
                'contact' => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'email' => $contact->email,
                    'subject' => $contact->subject,
                    'message' => $contact->message,
                    'reply_subject' => $contact->reply_subject,
                    'reply_message' => $contact->reply_message,
                    'replied_at' => $contact->replied_at?->toIso8601String(),
                    'status' => $contact->deleted_at ? 'Deleted' : 'Active',
                    'is_deleted' => (bool) $contact->deleted_at,
                    'created_at' => $contact->created_at?->toIso8601String(),
                    'updated_at' => $contact->updated_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Soft delete a contact.
     */
    public function destroy(int $id): JsonResponse
    {
        $contact = Contact::find($id);

        if (!$contact) {
            $contact = Contact::onlyTrashed()->find($id);
        }

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found.',
            ], 404);
        }

        if ($contact->deleted_at) {
            return response()->json([
                'success' => true,
                'message' => 'Contact is already deleted.',
                'data' => [
                    'contact' => [
                        'id' => $contact->id,
                        'full_name' => $contact->full_name,
                        'status' => 'Deleted',
                        'is_deleted' => true,
                        'created_at' => $contact->created_at?->toIso8601String(),
                        'updated_at' => $contact->updated_at?->toIso8601String(),
                        'deleted_at' => $contact->deleted_at?->toIso8601String(),
                    ],
                ],
            ], 200);
        }

        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully.',
            'data' => [
                'contact' => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'status' => 'Deleted',
                    'is_deleted' => true,
                    'created_at' => $contact->created_at?->toIso8601String(),
                    'updated_at' => $contact->updated_at?->toIso8601String(),
                    'deleted_at' => $contact->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }

    /**
     * Restore a soft-deleted contact.
     */
    public function restore(int $id): JsonResponse
    {
        $contact = Contact::onlyTrashed()->find($id);

        if (!$contact) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found or not deleted.',
            ], 404);
        }

        $contact->restore();

        return response()->json([
            'success' => true,
            'message' => 'Contact restored successfully.',
            'data' => [
                'contact' => [
                    'id' => $contact->id,
                    'full_name' => $contact->full_name,
                    'email' => $contact->email,
                    'subject' => $contact->subject,
                    'status' => 'Active',
                    'is_deleted' => false,
                    'created_at' => $contact->created_at?->toIso8601String(),
                    'updated_at' => $contact->updated_at?->toIso8601String(),
                    'deleted_at' => $contact->deleted_at?->toIso8601String(),
                ],
            ],
        ], 200);
    }
}
