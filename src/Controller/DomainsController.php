<?php

namespace App\Controller;

use App\Entity\Domain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;


class DomainsController extends AbstractController
{
    private const DELETED_DOMAINS_URL = 'https://www.dns.pl/deleted_domains.txt';

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer)
    {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
    }

    #[Route('/download-domains/', name: 'app_download_domains')]
    public function index(): JsonResponse
    {
        set_time_limit(600);

        $domainsArray = [];
        $counter = 0;

        $file = $this->getDomainsFromURL(DomainsController::DELETED_DOMAINS_URL);

        foreach ($file as $domain) {
            $domainsArray[] = new Domain($domain);
            $counter++;
        }

        $this->saveDomains($domainsArray);
        $this->sendEmail($counter);

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

    public function sendEmail(int $count): void
    {
        /*
                $emailAddress = $this->getParameter('ADMIN_EMAIL');
                $emailSubject = 'Pobrano nowe domeny w liczbie '. $count . ' z listy usuniętych domen - Deleted Domains App';
                $emailBody = '<p>Data: '. date('Y m d').'</p>'
                    .
                    '<br/>'
                    .
                    '<p>System <i>Deleted Domains App</i> pobrał z pliku tekstowego udostępnionego przez serwis DNS nowe domeny w liczbie: <b>'. $count .'</b>.</p>'
                    .
                    '<br/>'
                    .
                    '<p>Pobrane dzisiaj domeny zostaną zweryfikowane poprzez API za <b>'. $this->getParameter('DAYS_TO_VALIDATE') .'</b> dni.</p>'
                    .
                    '<br/>'
                    .
                    '<p>Otrzymasz w wiadomości email listę domen zweyfikowanych pozytywnie..</p>'
                ;
                $emailHeaders = [
                    'From' => 'Deleted Domains App <noreply@mgmedia.pl>',
                    'X-Mailer' => 'PHP/' . phpversion(),
                    'MIME-Version' => '1.0',
                    'Content-Type' => 'text/html; charset=iso-8859-1'
                ];

                mail($emailAddress, $emailSubject, $emailBody, $emailHeaders);
        */

        $email = (new Email())
            ->from('noreply@mgmedia.pl')
            ->to($this->getParameter('ADMIN_EMAIL'))
            ->subject('Test')
            ->html('Test!')
        ;

        $mailer = $this->mailer;
        $mailer->send($email);
    }

}
