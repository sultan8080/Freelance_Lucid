<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard_freelancer', name: 'dashboard_freelancer')]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        InvoiceRepository $invoiceRepository,
        ClientRepository $clientRepository,
        ChartBuilderInterface $chartBuilder
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // 1. Determine Selected Date
        $selectedMonth = $request->query->getInt('month', (int) date('n'));
        $selectedYear = $request->query->getInt('year', (int) date('Y'));
        $selectedDate = new \DateTimeImmutable("$selectedYear-$selectedMonth-01");

        //  A. DAILY REVENUE CHART & TOTAL KPI  ---

        // 1. Get start and end of the selected month
        $startOfMonth = $selectedDate;
        $endOfMonth = $selectedDate->modify('last day of this month')->setTime(23, 59, 59);
        $daysInMonth = (int) $endOfMonth->format('d');

        // 2. Fetch invoices for this specific month (SENT + PAID)
        $monthlyInvoices = $invoiceRepository->createQueryBuilder('i')
            ->where('i.user = :user')
            ->andWhere('i.createdAt BETWEEN :start AND :end')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->setParameter('statuses', ['SENT', 'PAID']) 
            ->getQuery()
            ->getResult();

        // 3. Initialize Array (Day 1 to End) & Revenue Counter
        $dailyData = array_fill(1, $daysInMonth, 0);
        $calculatedRevenue = 0; // This will replace the repository call

        foreach ($monthlyInvoices as $inv) {
            $amount = $inv->getTotalHt();
            
            // Add to Chart Day
            $day = (int) $inv->getCreatedAt()->format('j');
            $dailyData[$day] += $amount;

            // Add to Total KPI 
            $calculatedRevenue += $amount; 
        }

        // 4. Create the Revenue Line Chart
        $revenueChart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $revenueChart->setData([
            'labels' => array_keys($dailyData),
            'datasets' => [[
                'label' => 'Daily Revenue (€)',
                'backgroundColor' => 'rgba(59, 130, 246, 0.1)', // Blue transparent
                'borderColor' => 'rgb(59, 130, 246)', // Blue solid
                'borderWidth' => 2,
                'data' => array_values($dailyData),
                'tension' => 0.3, 
                'fill' => true,
                'pointRadius' => 3,
                'pointHoverRadius' => 6,
            ]],
        ]);

        $revenueChart->setOptions([
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        'title' => "function(context) { 
                            return '" . $selectedDate->format('M') . " ' + context[0].label; 
                        }"
                    ]
                ]
            ],
            'scales' => [
                'y' => [
                    'grid' => ['color' => 'rgba(255, 255, 255, 0.05)'], 
                    'ticks' => ['color' => '#94a3b8'],
                    'beginAtZero' => true
                ],
                'x' => [
                    'grid' => ['display' => false], 
                    'ticks' => ['color' => '#94a3b8']
                ],
            ],
            'maintainAspectRatio' => false,
        ]);


        // --- B. TOP CLIENTS CHART (Doughnut) ---

        $allInvoices = $invoiceRepository->findBy(['user' => $user, 'status' => ['SENT', 'PAID']]);
        $clientRevenueMap = [];

        foreach ($allInvoices as $inv) {
            $cName = $inv->getClient()->getCompanyName() ?: $inv->getClient()->getFullName();
            if (!isset($clientRevenueMap[$cName])) {
                $clientRevenueMap[$cName] = 0;
            }
            $clientRevenueMap[$cName] += $inv->getTotalHt();
        }
        arsort($clientRevenueMap); 
        $topClients = array_slice($clientRevenueMap, 0, 5);

        $clientChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
        $clientChart->setData([
            'labels' => array_keys($topClients),
            'datasets' => [[
                'data' => array_values($topClients),
                'backgroundColor' => [
                    'rgba(168, 85, 247, 0.8)', // Purple
                    'rgba(59, 130, 246, 0.8)', // Blue
                    'rgba(236, 72, 153, 0.8)', // Pink
                    'rgba(16, 185, 129, 0.8)', // Emerald
                    'rgba(245, 158, 11, 0.8)', // Amber
                ],
                'borderWidth' => 0,
            ]],
        ]);
        $clientChart->setOptions([
            'plugins' => [
                'legend' => ['position' => 'right', 'labels' => ['color' => '#cbd5e1', 'usePointStyle' => true]],
            ],
            'cutout' => '70%',
            'maintainAspectRatio' => false,
        ]);


        // --- C. OTHER METRICS ---

        $draftCount = $invoiceRepository->countByStatus($user, 'DRAFT');
        $sentCount = $invoiceRepository->countByStatus($user, 'SENT');
        
        $totalClients = $clientRepository->countTotalClients($user);
        $newClientsThisMonth = $clientRepository->countNewClientsForMonth($user, $selectedDate);

        $recentInvoices = $invoiceRepository->findRecentInvoices($user, 5);


        // --- D. RENDER ---

        return $this->render('dashboard/index.html.twig', [
            // CRITICAL: Use the calculated revenue so it matches the chart!
            'monthly_revenue' => $calculatedRevenue, 
            
            'counts' => [
                'draft' => $draftCount,
                'sent' => $sentCount,
            ],
            'clients' => [
                'total' => $totalClients,
                'new_this_month' => $newClientsThisMonth
            ],
            'recent_invoices' => $recentInvoices,
            'selected_date' => $selectedDate,
            'selected_month' => $selectedMonth, 
            'selected_year' => $selectedYear,
            'revenue_chart' => $revenueChart,
            'client_chart' => $clientChart,
        ]);
    }
}