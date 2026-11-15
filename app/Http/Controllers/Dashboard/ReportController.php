<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Report\ExportVenueReport;
use App\Actions\Report\GetVenueFinancialReport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\VenueContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(
        Request $request,
        VenueContext $venueContext,
        GetVenueFinancialReport $action,
    ): Response {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('update', $venue);

        $period = $request->string('period')->toString() ?: 'daily';
        $date = $request->string('date')->toString() ?: null;
        $month = $request->string('month')->toString() ?: null;

        return Inertia::render('Dashboard/Reports/Index', [
            'report' => $action->execute($venue, $period, $date, $month),
        ]);
    }

    public function export(
        Request $request,
        VenueContext $venueContext,
        ExportVenueReport $action,
    ): HttpResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('update', $venue);

        $period = $request->string('period')->toString() ?: 'daily';
        $date = $request->string('date')->toString() ?: null;
        $month = $request->string('month')->toString() ?: null;

        /** @var User $actor */
        $actor = $request->user();
        $csv = $action->execute($actor, $venue, $period, $date, $month);

        $filename = 'laporan-'.$venue->slug.'-'.($period === 'monthly' ? ($month ?: now()->format('Y-m')) : ($date ?: now()->toDateString())).'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
