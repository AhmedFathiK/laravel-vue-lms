<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialAnalyticsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:view.financial_dashboard');
    }

    public function getStats(Request $request): JsonResponse
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $incomeQuery = Payment::where('status', 'completed');
        $expenseQuery = Expense::where('status', 'completed');

        if ($fromDate) {
            $incomeQuery->whereDate('created_at', '>=', $fromDate);
            $expenseQuery->whereDate('date', '>=', $fromDate);
        }

        if ($toDate) {
            $incomeQuery->whereDate('created_at', '<=', $toDate);
            $expenseQuery->whereDate('date', '<=', $toDate);
        }

        $totalIncome = $incomeQuery->sum('amount');
        $totalExpenses = $expenseQuery->sum('amount');
        $netProfit = $totalIncome - $totalExpenses;

        return response()->json([
            'total_income' => round($totalIncome, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
        ]);
    }

    public function getChartData(Request $request): JsonResponse
    {
        $fromDate = $request->input('from_date', Carbon::now()->subMonths(11)->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', Carbon::now()->endOfMonth()->toDateString());

        // Helper to generate months array
        $start = Carbon::parse($fromDate);
        $end = Carbon::parse($toDate);
        $months = [];

        while ($start->lte($end)) {
            $months[] = $start->format('Y-m');
            $start->addMonth();
        }

        // Aggregate Income
        $incomeData = Payment::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Aggregate Expenses
        $expenseData = Expense::select(
            DB::raw("DATE_FORMAT(date, '%Y-%m') as month"),
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'completed')
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->groupBy('month')
            ->pluck('total', 'month')
            ->toArray();

        // Fill data for all months
        $labels = [];
        $incomeDataset = [];
        $expenseDataset = [];
        $profitDataset = [];

        foreach ($months as $month) {
            $labels[] = Carbon::createFromFormat('Y-m', $month)->format('M Y');

            $inc = floatval($incomeData[$month] ?? 0);
            $exp = floatval($expenseData[$month] ?? 0);

            $incomeDataset[] = $inc;
            $expenseDataset[] = $exp;
            $profitDataset[] = $inc - $exp;
        }

        return response()->json([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Income',
                    'data' => $incomeDataset,
                    'borderColor' => '#28a745',
                    'backgroundColor' => 'rgba(40, 167, 69, 0.1)',
                ],
                [
                    'label' => 'Expenses',
                    'data' => $expenseDataset,
                    'borderColor' => '#dc3545',
                    'backgroundColor' => 'rgba(220, 53, 69, 0.1)',
                ],
                [
                    'label' => 'Net Profit',
                    'data' => $profitDataset,
                    'borderColor' => '#007bff',
                    'backgroundColor' => 'rgba(0, 123, 255, 0.1)',
                ],
            ]
        ]);
    }
}
