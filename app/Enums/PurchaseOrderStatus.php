<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case PendingFinanceSignature = 'pending_finance_signature';
    case PendingGmSignature = 'pending_gm_signature';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case PendingReceipt = 'pending_receipt';       
    case Received = 'received';                   
    case CallbackRequested = 'callback_requested'; 
}