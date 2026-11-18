<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\SiteReportRequest;
use App\Entity\Site;
use App\Service\SiteReportIngestor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1')]
class SiteReportController extends AbstractController
{
    public function __construct(private readonly SiteReportIngestor $ingestor)
    {
    }

    #[Route('/sites/report', name: 'api_site_report', methods: ['POST'])]
    public function report(#[CurrentUser] Site $site, #[MapRequestPayload] SiteReportRequest $request): JsonResponse
    {
        $domains = $request->domains ?? [];
        if ([] !== $domains && !$site->matchesAnyDomain($domains)) {
            $this->ingestor->reject($site, $domains);

            return $this->json([
                'status' => 'rejected',
                'reason' => 'domain_mismatch',
                'message' => sprintf('Report from %s does not match the site URL %s', implode(', ', $domains), $site->getUrl()),
            ], Response::HTTP_CONFLICT);
        }

        $this->ingestor->ingest($site, $request);

        return $this->json(['status' => 'ok'], Response::HTTP_ACCEPTED);
    }
}
