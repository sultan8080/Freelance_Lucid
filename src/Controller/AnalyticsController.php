<?php

namespace App\Controller;

use App\Service\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class AnalyticsController extends AbstractController
{
    #[Route('/analytics', name: 'app_analytics')]
    public function index(
        Request $request,
        AnalyticsService $analyticsService,
        ChartBuilderInterface $chartBuilder
    ): Response {

        // 1.  Determine Selected Year
        $currentYear = (int) date('Y');
        $selectedYear = $request->query->getInt('year', $currentYear);

        // 2. Get Data from Service (Cached & Calculated)
        //  pass the current logged-in user and the year to fetch relevant data
        $data = $analyticsService->getDashboardData($this->getUser(), $selectedYear);

        // 3. Configure the Revenue Chart
        $chart = $chartBuilder->createChart(Chart::TYPE_LINE);
        $chart->setData([
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'datasets' => [[
                'label' => 'Revenue ' . $selectedYear,
                'backgroundColor' => 'rgba(99, 102, 241, 0.2)', // Indigo 500 (Transparent)
                'borderColor' => 'rgb(99, 102, 241)',           // Indigo 500 (Solid)
                'data' => $data['chart']['data'],               // Data from Service
                'tension' => 0.4,                               // Smooth curve
                'fill' => true,
            ]],
        ]);

        $chart->setOptions([
            'plugins' => [
                'legend' => ['display' => false], // Hide default legend for a cleaner UI
                'tooltip' => [
                    'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                    'padding' => 10,
                    'cornerRadius' => 8,
                ]
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => 'rgba(255, 255, 255, 0.05)' // Subtle grid lines for dark mode
                    ],
                    'ticks' => ['color' => '#9ca3af'] // Gray-400 text
                ],
                'x' => [
                    'grid' => ['display' => false], // Hide X-axis grid
                    'ticks' => ['color' => '#9ca3af']
                ]
            ],
            'maintainAspectRatio' => false,
        ]);

        // 4. Render View
        return $this->render('analytics/index.html.twig', [
            'kpi' => $data['kpi'],
            'lists' => $data['lists'],
            'chart' => $chart,
            'current_year' => $currentYear,
            'selected_year' => $selectedYear,
        ]);
    }
}
