<?php

namespace App\Controller;

use Aftermarketpl\Api\Client;
use Aftermarketpl\Api\Exception\Exception;
use App\DTO\DomainCheckDto;
use App\Repository\DomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


class VerificationController extends AbstractController
{
    private const MAX_DOMAINS_PER_REQUEST = 5;

    private EntityManagerInterface $entityManager;
    private DomainRepository $domainRepository;

    public function __construct(EntityManagerInterface $entityManager, DomainRepository $domainRepository)
    {

        $this->entityManager = $entityManager;
        $this->domainRepository = $domainRepository;
    }


    #[Route('/verify/', name: 'app_verify')]
    public function index(): JsonResponse
    {
        set_time_limit(600);

        $client = new Client(array(
            "key" => $this->getApiKey(),
            "secret" => $this->getApiSecret(),
        ));

        $availableDomains = [];
        $notAvailableDomains = [];
        $domainsFromDatabase = $this->getDomainsToArray();
        if (count($domainsFromDatabase) === 0){
            return $this->json([
                'No domains to verify.'
            ]);
        }
        $names = [];
        foreach ($domainsFromDatabase as $domain) {
            $names[] = $domain->getName();
        }

        try {
            $jsonResponse = $client->send("/domain/check", [
                "names" => $names
            ]);

            foreach ($jsonResponse as $item) {
                if ($item->status == "expiring") {
                    $availableDomains[] = new DomainCheckDto($item->name);
                } else {
                    $notAvailableDomains[] = new DomainCheckDto($item->name);
                }
            }

            $domainsStatusInform = $this->updateDomainsStatus($domainsFromDatabase, $availableDomains, "inform");
            $domainsStatusRemove = $this->updateDomainsStatus($domainsFromDatabase, $notAvailableDomains, "remove");

            return $this->json([
                "Updated " . count(array_merge($domainsStatusInform, $domainsStatusRemove)) . " domains in database."
            ]);
        } catch (Exception $e) {
            return $this->json([
                "Exception: " . $e->getMessage()
            ]);
        }

    }

    private function getApiKey(): string
    {
        return strval($this->getParameter('API_KEY'));
    }

    private function getApiSecret(): string
    {
        return strval($this->getParameter('API_SECRET'));
    }

    public function getDomainsToArray(): array
    {
        try {
            $domainsArray = $this->domainRepository->findNewDomainsOlderThanDays(
                $this->getParameter('DAYS_TO_VALIDATE'),
                VerificationController::MAX_DOMAINS_PER_REQUEST
            );
            return $domainsArray;
        } catch (\Exception $e) {
            return [$e->getMessage()];
        }
    }

    public function updateDomainsStatus(array $domainsFromDatabase, array $domainsValidated, string $status): array
    {
        $domainsToUpdate = [];

        foreach ($domainsValidated as $domainValidated) {
            foreach ($domainsFromDatabase as $domainFromDatabase) {
                if ($domainValidated->getName() == $domainFromDatabase->getName()) {
                    $domainFromDatabase->setStatus($status);
                    $domainsToUpdate[] = $domainFromDatabase;
                }
            }
        }

        try {
            $this->entityManager->beginTransaction();

            foreach ($domainsToUpdate as $domainToUpdate) {
                $this->entityManager->persist($domainToUpdate);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            return [$e->getMessage()];
        }
        return $domainsToUpdate;
    }

}