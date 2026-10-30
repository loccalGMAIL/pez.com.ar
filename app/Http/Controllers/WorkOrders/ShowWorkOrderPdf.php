<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Support\WorkOrderPdf;
use Illuminate\Http\Response;

/**
 * Public, signed link to a work order's intake slip.
 */
class ShowWorkOrderPdf extends Controller
{
    public function __invoke(WorkOrder $workOrder): Response
    {
        return response(WorkOrderPdf::render($workOrder), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.WorkOrderPdf::filename($workOrder).'"',
        ]);
    }
}
