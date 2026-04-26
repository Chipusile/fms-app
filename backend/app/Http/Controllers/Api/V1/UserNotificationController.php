<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Helpers\ApiResponse;
use App\Http\Resources\Api\V1\UserNotificationResource;
use App\Models\UserNotification;
use App\Services\Workflow\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', UserNotification::class);

        $notifications = UserNotification::query()
            ->with('user')
            ->where('user_id', $request->user()->id)
            ->when($request->filled('filter.status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->filled('filter.type'), fn ($query, $type) => $query->where('type', $type))
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request, 20));

        return ApiResponse::success(
            UserNotificationResource::collection($notifications),
            meta: [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => UserNotification::query()
                    ->where('user_id', $request->user()->id)
                    ->where('status', 'unread')
                    ->count(),
            ]
        );
    }

    public function markRead(UserNotification $userNotification): JsonResponse
    {
        $this->authorize('update', $userNotification);

        $userNotification = $this->notificationService->markRead($userNotification);

        return ApiResponse::success(new UserNotificationResource($userNotification), 'Notification marked as read.');
    }

    public function acknowledge(UserNotification $userNotification): JsonResponse
    {
        $this->authorize('update', $userNotification);

        $userNotification = $this->notificationService->acknowledge($userNotification);

        return ApiResponse::success(new UserNotificationResource($userNotification), 'Notification acknowledged.');
    }
}
