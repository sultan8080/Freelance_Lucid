<?php

namespace App\Controller;

use App\Service\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class AnalyticsController extends AbstractController
{
    #[Route('/analytics', name: 'app_analytics')]
    public function index(
        Request $request,
        AnalyticsService $analyticsService,
        ChartBuilderInterface $chartBuilder,
        TranslatorInterface $translator
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
            // Keep months keys as standard or translate them if needed. 
            // For ChartJS labels, it's often better to translate them in the controller.
            'labels' => [
                $translator->trans('Jan'),
                $translator->trans('Feb'),
                $translator->trans('Mar'),
                $translator->trans('Apr'),
                $translator->trans('May'),
                $translator->trans('Jun'),
                $translator->trans('Jul'),
                $translator->trans('Aug'),
                $translator->trans('Sep'),
                $translator->trans('Oct'),
                $translator->trans('Nov'),
                $translator->trans('Dec')
            ],
            'datasets' => [[
                'label' => $translator->trans('Revenue') . ' ' . $selectedYear,
                'backgroundColor' => 'rgba(99, 102, 241, 0.2)', 
                'borderColor' => 'rgb(99, 102, 241)',           
                'data' => $data['chart']['data'],               
                'tension' => 0.4,                               
                'fill' => true,
            ]],
        ]);

        $chart->setOptions([
            'plugins' => [
                'legend' => ['display' => false], 
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
                        'color' => 'rgba(255, 255, 255, 0.05)'
                    ],
                    'ticks' => ['color' => '#9ca3af'] 
                ],
                'x' => [
                    'grid' => ['display' => false], 
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
