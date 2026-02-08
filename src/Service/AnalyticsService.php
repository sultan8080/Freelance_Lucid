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

    public function getDashboardData(User $user, int $year): array
    {
        $cacheKey = sprintf('analytics_%d_%d', $user->getId(), $year);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $year) {
            $item->expiresAfter(300);

            // 1. Fetch Aggregates
            $monthlyResult = $this->invoiceRepository->getMonthlyTotalsByYear($user, $year);
            $yearlyTotal = $this->invoiceRepository->getYearlyTotalByYear($user, $year);
            $totalInvoicesCount = $this->invoiceRepository->countInvoicesByYear($user, $year);
            $paidInvoicesCount = $this->invoiceRepository->countPaidInvoicesByYear($user, $year);
            
            // 2. Fetch Lists (Top Clients)
            $rawTopClients = $this->invoiceRepository->findTopClientsByRevenue($user, $year, 5);
            
            // Format Client Names
            $topClients = [];
            foreach ($rawTopClients as $client) {
                // Priority: Company Name > First Last > Unknown
                $displayName = !empty($client['companyName']) 
                    ? $client['companyName'] 
                    : trim($client['firstName'] . ' ' . $client['lastName']);

                if (empty($displayName)) {
                    $displayName = 'Client #' . $client['clientId'];
                }

                $topClients[] = [
                    'clientName'   => $displayName, // This key is now safe for Twig
                    'clientId'     => $client['clientId'],
                    'totalRevenue' => $client['totalRevenue'],
                ];
            }
            
            // 3. Fetch Pending Invoices
            $allPending = $this->invoiceRepository->findPendingInvoices($user);
            $pendingInvoices = array_slice($allPending, 0, 15);

            // 4. Normalize Chart
            $monthlyRevenue = array_fill(1, 12, 0.0);
            foreach ($monthlyResult as $row) {
                $month = (int) $row['month'];
                $monthlyRevenue[$month] = (float) $row['total'];
            }

            // 5. Calculate KPIs
            $avgInvoice = $paidInvoicesCount > 0 ? ($yearlyTotal / $paidInvoicesCount) : 0.0;
            $paidRatio = $totalInvoicesCount > 0 ? ($paidInvoicesCount / $totalInvoicesCount) * 100.0 : 0.0;
            $ceilingPercent = $this->ceilingLimit > 0 
                ? min(100.0, ($yearlyTotal / $this->ceilingLimit) * 100.0) 
                : 0.0;

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