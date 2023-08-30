<?php

namespace App\Controller;

use App\Entity\Domain;
use App\Repository\DomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;


class ManagerController extends AbstractController
{

    private DomainRepository $domainRepository;
    private MailerInterface $mailer;

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer, DomainRepository $domainRepository)
    {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->domainRepository = $domainRepository;
    }

    #[Route('/manage/', name: 'app_manage')]
    public function index(): JsonResponse
    {
        set_time_limit(600);

        $domainsToRemove = $this->getDomainsFromDatabase('remove');
        $domainsToInform = $this->getDomainsFromDatabase('inform');

        if(count($domainsToRemove) > 0){
            $this->removeDomainsFromDatabase($domainsToRemove);
        }
        if(count($domainsToInform) > 0){
            $this->sendMessage($domainsToInform);
        }

        return $this->json(
            "Removed " . count($domainsToRemove) . " domains. "
            .
            "Sent an email with " . count($domainsToInform) . " domains. "
        );

    }

    public function getDomainsFromDatabase(string $status): array
    {
        try {
            $domainsArray = $this->domainRepository->findBy(
                ['status' => $status],
                null
            );
            return $domainsArray;
        } catch (\Exception $e) {
            return [$e->getMessage()];
        }
    }

    private function removeDomainsFromDatabase(array $domainsToRemove): void
    {
        $this->domainRepository->removeDomainsFromDatabase($domainsToRemove);
    }

    private function sendMessage(array $domainsToInform): void
    {
        $this->sendEmail($domainsToInform);
        $this->domainRepository->markDomainsForRemoval($domainsToInform);
    }

    public function sendEmail(array $domains): void
    {
        $count = sizeof($domains);

        $emailDomainsList = '';
        foreach ($domains as $domain) {
            if ($domain instanceof Domain) {
                $emailDomainsList .= '<tr>';
                $emailDomainsList .= '<td>' . $domain->getName() . '</td>';
                $emailDomainsList .= '<td>' . $domain->getDateAdded()->format('Y-m-d') . '</td>';
                $emailDomainsList .= '</tr>';
            }
        }

        $emailSubject = 'Zweryfikowano nowe domeny w liczbie ' . $count . ' z listy usuniętych domen - Deleted Domains App';
        $emailBody = '<p>Data: ' . date('Y m d') . '</p>'
            .
            '<br/>'
            .
            '<p>System <i>Deleted Domains App</i> zweryfikował domeny w liczbie: <b>' . $count . '</b>.</p>'
            .
            '<br/>'
            .
            '<p><table style="text-align: left !important;"><thead><tr><th>Domena</th><th>Data pobrania</th></tr></thead><tbody>'
            . $emailDomainsList
            .'</tbody></table></p>'
        ;

        $email = (new Email())
            ->from(new Address('noreply@mgmedia.pl', 'Deleted Domains App'))
            ->to($this->getParameter('ADMIN_EMAIL'))
            ->subject($emailSubject)
            ->html($emailBody);

        $mailer = $this->mailer;
        try {
            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new Exception($e->getMessage());
        }
    }

}
