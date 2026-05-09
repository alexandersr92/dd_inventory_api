<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Enums\ReportType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    /**
     * Get a list of the reports generated.
     */
    public function index(Request $request)
    {
        $orgId = Auth::user()->organization_id;
        $storeID = $request->query('store_id');
        $per_page = $request->query('per_page', 20);
        $order = $request->query('order', 'desc');

        $reports = Report::where('organization_id', $orgId)
            ->where('store_id', $storeID)
            ->orderBy('created_at', $order)
            ->paginate($per_page);

        return response()->json($reports);
    }

    /**
     * Get the available types of reports.
     */
    public function types()
    {
        return response()->json([
            'types' => ReportType::cases()
        ]);
    }

    /**
     * Generate a new report.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $orgId = Auth::user()->organization_id;
        $userId = Auth::id();
        $type = $request->type;
        $filters = $request->except(['type']);

        try {
            $strategy = \App\Services\Reports\ReportStrategyFactory::make($type);
            $report = $strategy->generate($orgId, $userId, $filters);

            return response()->json([
                'message' => 'Report generated successfully',
                'report' => $report
            ], 201);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate report', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Download the specific report PDF.
     */
    public function download(Report $report)
    {
        if ($report->organization_id !== Auth::user()->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$report->file_path || !Storage::disk('public')->exists($report->file_path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($report->file_path, $report->name . '.pdf');
    }

    /**
     * Delete a report and its physical file.
     */
    public function destroy(Report $report)
    {
        $orgId = Auth::user()->organization_id;

        if ($report->organization_id !== $orgId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Eliminar archivo físico si existe
        if ($report->file_path && Storage::disk('public')->exists($report->file_path)) {
            Storage::disk('public')->delete($report->file_path);
        }

        $report->delete();

        return response()->json(['message' => 'Report deleted successfully']);
    }
}
