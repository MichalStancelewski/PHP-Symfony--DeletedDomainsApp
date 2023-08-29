<?php

namespace App\Controller;

use App\Entity\Domain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


class DomainsController extends AbstractController
{
    private const DELETED_DOMAINS_URL = 'https://www.dns.pl/deleted_domains.txt';

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }


    #[Route('/download-domains/', name: 'app_download_domains')]
    public function index(): JsonResponse
    {
        set_time_limit(600);

        $domainsArray = [];
        $counter = 0;

        $file = $this->getDomainsFromURL(DomainsController::DELETED_DOMAINS_URL);

        foreach($file as $domain){
            $domainsArray[] = new Domain($domain);
            $counter++;
        }

        $this->saveDomains($domainsArray);

        return $this->json(
            "Downloaded " . $counter . " domains."
        );

    }

    function getDomainsFromURL(string $url): array
    {
        $httpClient = HttpClient::create();

        try {
            $response = $httpClient->request('GET', $url);

            if ($response->getStatusCode() === 200) {
                $content = $response->getContent();

                $lines = explode("\n", $content);

                array_shift($lines);
                array_shift($lines);

                $filteredLines = array_filter(array_map('trim', $lines));

                return $filteredLines;
            }
        } catch (\Exception $e) {
            return [$e->getMessage()];
        }
    }

    public function saveDomains(array $domains): void
    {
        try {
            $this->entityManager->beginTransaction();

            foreach ($domains as $domain) {
                $this->entityManager->persist($domain);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

        } catch (\Exception $e) {
            $this->entityManager->rollback();
        }
    }

}
