<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use App\Services\WorkTimeLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Work Time Log controller for managing timer durasi pelaksana on Work Orders.
 * Routes: accept, start, pause, resume.
 * Authorization is handled by WorkTimeLogService.
 */
class WorkTimeLogController extends Controller
{
    public function __construct(private readonly WorkTimeLogService $workTimeLogService) {}

    /**
     * Accept a work order assignment for the current user.
     */
    public function accept(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $log = $this->workTimeLogService->accept($request->user(), $workOrder);

        return response()->json([
            'success' => true,
            'time_log' => [
                'accepted_at' => $log->accepted_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Start working on a work order.
     */
    public function start(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $log = $this->workTimeLogService->start($request->user(), $workOrder);

        return response()->json([
            'success' => true,
            'time_log' => [
                'started_at' => $log->started_at?->toIso8601String(),
                'state' => $log->timerState(),
                'elapsed_seconds' => $log->elapsedSeconds(),
            ],
        ]);
    }

    /**
     * Pause working on a work order.
     */
    public function pause(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $log = $this->workTimeLogService->pause($request->user(), $workOrder);

        return response()->json([
            'success' => true,
            'time_log' => [
                'paused_at' => $log->paused_at?->toIso8601String(),
                'state' => $log->timerState(),
                'elapsed_seconds' => $log->elapsedSeconds(),
            ],
        ]);
    }

    /**
     * Resume working on a work order.
     */
    public function resume(Request $request, WorkOrder $workOrder): JsonResponse
    {
        $log = $this->workTimeLogService->resume($request->user(), $workOrder);

        return response()->json([
            'success' => true,
            'time_log' => [
                'state' => $log->timerState(),
                'elapsed_seconds' => $log->elapsedSeconds(),
            ],
        ]);
    }
}