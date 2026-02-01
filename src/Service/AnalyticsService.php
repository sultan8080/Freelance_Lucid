<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\InvoiceRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class AnalyticsService
{
    private const DEFAULT_CEILING = 77700;

    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private CacheInterface $cache,
        private int $ceilingLimit = self::DEFAULT_CEILING
    ) {}

    /**
     * Aggregates financial data for the dashboard.
     * Uses caching to minimize database load.
     * @param User $user The user for whom to fetch analytics data
     * @param int $year The year for which to fetch analytics data
     * @return
     * array{
     *   kpi: array{total_revenue: float, avg_invoice: float, paid_ratio: float, total_count: int, ceiling_limit: int, ceiling_percent: float},
     *   chart: array{data: float[]},
     *   lists: array{top_clients: array, pending: array}
     * }
     */


    public function getDashboardData(User $user, int $year): array
    {
        // Unique cache key per user and year to prevent data collision
        $cacheKey = sprintf('analytics_%d_%d', $user->getId(), $year);
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $year) {

            $item->expiresAfter(300);

            // --- 1. Fetch Raw Data from Repository ---
            $monthlyResult = $this->invoiceRepository->getMonthlyTotalsByYear($user, $year);
            $yearlyTotal = $this->invoiceRepository->getYearlyTotalByYear($user, $year);
            $totalInvoicesCount = $this->invoiceRepository->countInvoicesByYear($user, $year);
            $paidInvoicesCount = $this->invoiceRepository->countPaidInvoicesByYear($user, $year);
            $topClients = $this->invoiceRepository->findTopClientsByRevenue($user, $year, 5);
            $allPending = $this->invoiceRepository->findPendingInvoices($user);
            $pendingInvoices = array_slice($allPending, 0, 5);

            // --- 2. Normalize Chart Data ---
            // Create an array with 12 months initialized to 0.0
            $monthlyRevenue = array_fill(1, 12, 0.0);

            foreach ($monthlyResult as $row) {
                $month = (int) $row['month'];
                $monthlyRevenue[$month] = (float) $row['total'];
            }

            // --- 3. Calculate KPIs ---

            // Average Invoice Value (Avoid division by zero)
            $avgInvoice = $paidInvoicesCount > 0 ? ($yearlyTotal / $paidInvoicesCount) : 0.0;

            // Paid Ratio: What % of created invoices are fully paid?
            $paidRatio = $totalInvoicesCount > 0 ? ($paidInvoicesCount / $totalInvoicesCount) * 100.0 : 0.0;

            // Ceiling Progress: How close are we to the micro-entrepreneur limit?
            $ceilingPercent = $this->ceilingLimit > 0
                ? min(100.0, ($yearlyTotal / $this->ceilingLimit) * 100.0)
                : 0.0;

            // --- 4. Return Structure ---
            return [
                'kpi' => [
                    'total_revenue'   => $yearlyTotal,
                    'avg_invoice'     => $avgInvoice,
                    'paid_ratio'      => $paidRatio,
                    'total_count'     => $totalInvoicesCount,
                    'ceiling_limit'   => $this->ceilingLimit,
                    'ceiling_percent' => $ceilingPercent,
                ],
                'chart' => [
                    // Reset array keys to 0..11 for Chart.js
                    'data' => array_values($monthlyRevenue),
                ],
                'lists' => [
                    'top_clients' => $topClients,
                    'pending'     => $pendingInvoices,
                ],
            ];
        });
    }
}
