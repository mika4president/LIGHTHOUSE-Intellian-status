<?php

namespace App\Controller\Admin;

use App\Entity\Schip;
use App\Entity\Scheepsdata;
use App\Repository\ScheepsdataRepository;
use App\Repository\SchipRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\ColorScheme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private SchipRepository $schipRepository,
        private ScheepsdataRepository $scheepsdataRepository,
        private AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public function index(): Response
    {
        $countShips = $this->schipRepository->count([]);
        $countPushesToday = $this->scheepsdataRepository->countReceivedSince(
            (new \DateTimeImmutable())->setTime(0, 0, 0)
        );
        $countTracking = $this->scheepsdataRepository->countTrackingNow();
        $recentScheepsdata = $this->scheepsdataRepository->findRecent(15);

        $shipIndexUrl = $this->adminUrlGenerator->setController(SchipCrudController::class)->generateUrl();
        $scheepsdataIndexUrl = $this->adminUrlGenerator->setController(ScheepsdataCrudController::class)->generateUrl();

        $recentWithUrls = [];
        foreach ($recentScheepsdata as $sd) {
            $recentWithUrls[] = [
                'entity' => $sd,
                'detailUrl' => $this->adminUrlGenerator->setController(ScheepsdataCrudController::class)
                    ->setEntityId($sd->getId())
                    ->setAction('detail')
                    ->generateUrl(),
            ];
        }

        return $this->render('admin/dashboard.html.twig', [
            'countShips' => $countShips,
            'countPushesToday' => $countPushesToday,
            'countTracking' => $countTracking,
            'recentWithUrls' => $recentWithUrls,
            'shipIndexUrl' => $shipIndexUrl,
            'scheepsdataIndexUrl' => $scheepsdataIndexUrl,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Lighthouse – Admin')
            ->setDefaultColorScheme(ColorScheme::DARK);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToRoute('Schotelstatus (dashboard)', 'fa fa-map-marker-alt', 'app_dashboard_index');
        yield MenuItem::linkToCrud('Schepen', 'fa fa-ship', Schip::class);
        yield MenuItem::linkToCrud('Scheepsdata', 'fa fa-database', Scheepsdata::class);
    }
}
