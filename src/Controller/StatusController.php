<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\MailTestType;
use App\Service\MailTester;
use App\Service\SyncMonitor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
                ? $this->addFlash('success', sprintf('The mail server accepted the test mail to %s. Check that it arrives, also in the spam folder.', $recipient))
                : $this->addFlash('error', sprintf('The test mail could not be sent: %s', $error));

            return $this->redirectToRoute('app_status');
        }

        return $this->render('status/index.html.twig', [
            'syncs' => $syncMonitor->states(),
            'mailTestForm' => $form,
            'mailTransport' => $mailTester->getTransport(),
            'mailFrom' => $mailTester->getFromAddress(),
        ]);
    }
}
