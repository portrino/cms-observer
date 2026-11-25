<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\MailTestType;
use App\Service\MailTester;
use App\Service\SyncMonitor;
use App\Service\SystemInformation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;

class StatusController extends AbstractController
{
    #[Route('/status', name: 'app_status')]
    public function index(Request $request, SyncMonitor $syncMonitor, MailTester $mailTester): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(MailTestType::class, ['recipient' => $user instanceof User ? $user->getEmail() : null]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $recipient = (string) $form->get('recipient')->getData();
            $error = $mailTester->send($recipient);

            null === $error
                ? $this->addFlash('success', new TranslatableMessage('status.mail_sent', ['recipient' => $recipient]))
                : $this->addFlash('error', new TranslatableMessage('status.mail_failed', ['error' => $error]));

            return $this->redirectToRoute('app_status');
        }

        return $this->render('status/index.html.twig', [
            'syncs' => $syncMonitor->states(),
            'lastSuccess' => $syncMonitor->lastSuccess(),
            'timezone' => date_default_timezone_get(),
            'mailTestForm' => $form,
            'mailTransport' => $mailTester->getTransport(),
            'mailFrom' => $mailTester->getFromAddress(),
        ]);
    }

    /** Lazily loaded into the brand dropdown, so other pages don't pay for the queries */
    #[Route('/status/system-info', name: 'app_status_system_info')]
    public function systemInfo(Request $request, SystemInformation $systemInformation): Response
    {
        return $this->render('status/_system_info.html.twig', [
            'info' => $systemInformation->collect($request),
        ]);
    }
}
