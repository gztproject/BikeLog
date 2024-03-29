<?php
// src/Controller/MailerController.php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerController extends AbstractController
{

    /**
     *
     * @Route("/email", methods={"GET"}, name="email")
     */
    public function sendEmail(MailerInterface $mailer): Response
    {
        $email = (new Email())->from('bikeLog@gzt.si')
            ->to('gasper.doljak@gmail.com')
            ->
        // ->cc('cc@example.com')
        // ->bcc('bcc@example.com')
        // ->replyTo('fabien@example.com')
        // ->priority(Email::PRIORITY_HIGH)
        subject('Time for Symfony Mailer!')
            ->text('Sending emails is fun again!')
            ->html('<p>See Twig integration for better HTML integration!</p>');

        $mailer->send($email);

        // ...
    }
}
?>