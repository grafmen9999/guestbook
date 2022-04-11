<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Conference;
use App\Form\CommentFormType;
use App\Message\CommentMessage;
use App\Repository\CommentRepository;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ConferenceController extends AbstractController
{
    private Environment $twig;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $bus;
    private string $defaultLocale;

    public function __construct(
        Environment $twig,
        EntityManagerInterface $entityManager,
        MessageBusInterface $bus,
        string $defaultLocale
    ) {
        $this->twig = $twig;
        $this->entityManager = $entityManager;
        $this->bus = $bus;
        $this->defaultLocale = $defaultLocale;
    }

    /**
     * @Route("/")
     *
     * @return Response
     */
    public function indexNoLocale(): Response
    {
        return $this->redirectToRoute('homepage', ['_locale' => $this->defaultLocale]);
    }

    /**
     * @Route("/{_locale<%app.supported_locales%>}/", name="homepage")
     *
     * @param ConferenceRepository $conferenceRepository
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function index(ConferenceRepository $conferenceRepository): Response
    {
        $response = new Response(
            $this->twig->render(
                'conference/index.html.twig',
                [
                    'conferences' => $conferenceRepository->findAll(),
                ]
            )
        );

        $response->setSharedMaxAge(3600);

        return $response;
    }

    /**
     * @Route("/{_locale<%app.supported_locales%>}/conference/{slug}", name="conference")
     *
     * @param Request           $request
     * @param Conference        $conference
     * @param CommentRepository $commentRepository
     * @param NotifierInterface $notifier
     * @param string            $photoDir
     * @return Response
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function show(
        Request $request,
        Conference $conference,
        CommentRepository $commentRepository,
        NotifierInterface $notifier,
        string $photoDir
    ): Response {
        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment->setConference($conference);

            if ($photo = $form->get('photo')->getData()) {
                $filename = bin2hex(random_bytes(6) . '.' . $photo->guessExtension());
                try {
                    $photo->move($photoDir, $filename);
                } catch (FileException $e) {
                }
                $comment->setPhotoFilename($filename);
            }

            $this->entityManager->persist($comment);
            $this->entityManager->flush();

            $context = [
                'user_ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('user-agent'),
                'referrer' => $request->headers->get('referer'),
                'permalink' => $request->getUri(),
            ];

            $reviewUrl = $this->generateUrl(
                'review_comment',
                ['id' => $comment->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $this->bus->dispatch(new CommentMessage($comment->getId(), $reviewUrl, $context));

            $notifier->send(
                new Notification(
                    'Thank you for the feedback; your comment will be posted after moderation.',
                    ['browser']
                )
            );

            return $this->redirectToRoute('conference', ['slug' => $conference->getSlug()]);
        }

        if ($form->isSubmitted()) {
            $notifier->send(
                new Notification(
                    'Can you check your submission? There are some problems with it.',
                    ['browser']
                )
            );
        }

        $offset = max(0, $request->query->getInt('offset'));
        $paginator = $commentRepository->getCommentPaginator($conference, $offset);

        return new Response(
            $this->twig->render(
                'conference/show.html.twig',
                [
                    'conference' => $conference,
                    'comments' => $paginator,
                    'previous' => $offset - CommentRepository::PAGINATOR_PER_PAGE,
                    'next' => min(count($paginator), $offset + CommentRepository::PAGINATOR_PER_PAGE),
                    'comment_form' => $form->createView(),
                ]
            )
        );
    }

    /**
     * @Route("/{_locale<%app.supported_locales%>}/conference_header", name="conference_header")
     *
     * @param ConferenceRepository $conferenceRepository
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function conferenceHeader(ConferenceRepository $conferenceRepository): Response
    {
        $response = new Response(
            $this->twig->render(
                'conference/header.html.twig',
                [
                    'conferences' => $conferenceRepository->findAll(),
                ]
            )
        );

        $response->setSharedMaxAge(3600);

        return $response;
    }
}
