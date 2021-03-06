<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Comment;
use App\Message\CommentMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\HttpCache\HttpCache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Workflow\Registry;
use Twig\Environment;

/**
 * @Route("/admin")
 */
class AdminController extends AbstractController
{
    private Environment $twig;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $bus;

    public function __construct(Environment $twig, EntityManagerInterface $entityManager, MessageBusInterface $bus)
    {
        $this->twig = $twig;
        $this->entityManager = $entityManager;
        $this->bus = $bus;
    }

    /**
     * @Route("/comment/review/{id}", name="review_comment")
     *
     * @param Request  $request
     * @param Comment  $comment
     * @param Registry $registry
     *
     * @return Response
     */
    public function reviewComment(Request $request, Comment $comment, Registry $registry): Response
    {
        $accepted = !$request->query->get('reject');

        $machine = $registry->get($comment);

        if ($machine->can($comment, 'published')) {
            $transition = $accepted ? 'publish' : 'reject';
        } elseif ($machine->can($comment, 'publish_ham')) {
            $transition = $accepted ? 'publish_ham' : 'reject_ham';
        } else {
            return new Response('Comment already reviewed or not in the right state.');
        }

        $machine->apply($comment, $transition);
        $this->entityManager->flush();

        if ($accepted) {
            $reviewUrl = $this->generateUrl(
                'review_comment',
                ['id' => $comment->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $this->bus->dispatch(new CommentMessage($comment->getId(), $reviewUrl));
        }

        return $this->render(
            'admin/review.html.twig',
            [
                'transition' => $transition,
                'comment' => $comment,
            ]
        );
    }

    /**
     * @Route("/http-cache/{uri<.*>}", methods={"PURGE"})
     *
     * @param KernelInterface $kernel
     * @param Request         $request
     * @param string          $uri
     *
     * @return Response
     */
    public function purgeHttpCache(KernelInterface $kernel, Request $request, string $uri): Response
    {
        if ('prod' === $kernel->getEnvironment()) {
            return new Response('KO', 400);
        }

        $store = (new class($kernel) extends HttpCache {})->getStore();
        $store->purge($request->getSchemeAndHttpHost() . '/' . $uri);

        return new Response('Done');
    }
}
