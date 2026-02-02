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

final class DashboardController extends AbstractController
{
    #[Route('/dashboard_freelancer', name: 'dashboard_freelancer')]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        InvoiceRepository $invoiceRepository,
        ClientRepository $clientRepository,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // 1. Determine Selected Date (Defaults to current month/year if not in URL)
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');

        $selectedMonth = $request->query->getInt('month', $currentMonth);
        $selectedYear = $request->query->getInt('year', $currentYear);

        $selectedDate = new \DateTimeImmutable("$selectedYear-$selectedMonth-01");

        // 2. Invoices & Revenue 
        $monthlyRevenue = $invoiceRepository->getRevenueForMonth($user, $selectedDate);
        $draftCount = $invoiceRepository->countByStatus($user, 'DRAFT');
        $sentCount = $invoiceRepository->countByStatus($user, 'SENT');
        
        // 3. Client Metrics 
        $totalClients = $clientRepository->countTotalClients($user);
        $newClientsThisMonth = $clientRepository->countNewClientsForMonth($user, $selectedDate);

        // 4. Recent Activity
        $recentInvoices = $invoiceRepository->findRecentInvoices($user, 5);

        return $this->render('dashboard/index.html.twig', [
            'monthly_revenue' => $monthlyRevenue,
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
        ]);
    }
}