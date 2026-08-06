<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DashboardSummaryResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Return dashboard summary counts.
     */
    public function summary(Request $request): DashboardSummaryResource
    {
        $user = $request->user();

        // Count of submitted PRs awaiting PO conversion
        $pendingRequestsCount = PurchaseRequest::where('status', PurchaseRequestStatus::Submitted)->count();

        // Count of POs pending any signature (finance or GM)
        $pendingOrdersCount = PurchaseOrder::whereIn('status', [
            PurchaseOrderStatus::PendingFinanceSignature,
            PurchaseOrderStatus::PendingGmSignature,
        ])->count();

        // Count of POs pending the current user's signature
        $pendingMySignatureCount = 0;
        if ($user->isFinanceOfficer()) {
            $pendingMySignatureCount = PurchaseOrder::where('status', PurchaseOrderStatus::PendingFinanceSignature)->count();
        } elseif ($user->isGeneralManager()) {
            $pendingMySignatureCount = PurchaseOrder::where('status', PurchaseOrderStatus::PendingGmSignature)->count();
        }

        // Recently completed: approved POs in the last 7 days
        $recentlyCompletedCount = PurchaseOrder::where('status', PurchaseOrderStatus::Approved)
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        return new DashboardSummaryResource([
            'pending_requests_count' => $pendingRequestsCount,
            'pending_orders_count' => $pendingOrdersCount,
            'pending_my_signature_count' => $pendingMySignatureCount,
            'recently_completed_count' => $recentlyCompletedCount,
        ]);
    }
}
