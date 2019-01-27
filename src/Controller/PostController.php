<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\User;
use App\Form\CommentType;
use App\Form\PostType;
use App\Service\Pagination;
use Knp\Component\Pager\PaginatorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use WhiteOctober\BreadcrumbsBundle\Model\Breadcrumbs;

class PostController extends AbstractController
{
    private $translator;
    private $pagination;

    /**
     * PostController constructor.
     * @param TranslatorInterface $translator
     * @param Pagination $pagination
     */
    public function __construct(TranslatorInterface $translator, Pagination $pagination)
    {
        $this->translator = $translator;
        $this->pagination = $pagination;
    }

    /**
     * @param Request $request
     * @param $count
     * @param Breadcrumbs $breadcrumbs
     *
     * @return Response
     */
    public function index(Request $request, $count, Breadcrumbs $breadcrumbs): Response
    {
        $breadcrumbs->addRouteItem('Home', 'index');
        $blogPosts = $this->pagination->paginationBlogIndexQuery($request, $count);

        return $this->render('post/index.html.twig', [
            'posts' => $blogPosts,
        ]);
    }

    /**
     * @param Request $request
     * @param PaginatorInterface $paginator
     * @param Breadcrumbs $breadcrumbs
     *
     * @return Response
     */
    public function adminIndex(Request $request, PaginatorInterface $paginator, Breadcrumbs $breadcrumbs): Response
    {
        $breadcrumbs->addRouteItem('Home', 'index');
        $breadcrumbs->addItem('Posts', $this->get('router')->generate('posts'));
        $em = $this->getDoctrine()->getManager();
        $posts = $em->getRepository(Post::class)->findAllQuery();
        $paginatePosts = $paginator->paginate($posts, $request->query->getInt('page', 1), 10);

        return $this->render('post/posts.html.twig', [
            'posts' => $paginatePosts,
        ]);
    }

    /**
     * @param Request $request
     * @param Breadcrumbs $breadcrumbs
     *
     * @throws \Doctrine\ORM\OptimisticLockException
     * @return Response
     */
    public function new(Request $request, Breadcrumbs $breadcrumbs): Response
    {
        $breadcrumbs->addItem('Home', $this->get('router')->generate('index'));
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $post = new Post();
        $post->setAuthor($this->getUser());
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($post);
            $em->flush();
            $this->addFlash(
                'notice',
                $this->translator->trans('notification.post_created', [
                    '%title%' => $post->getTitle(),
                ])
            );
            $url = $this->generateUrl(
                'post_show',
                ['slug' => $post->getSlug()]
            );
            $this->sendNotification($post->getTitle(), $url);

            return $this->redirectToRoute('blog');
        }

        return $this->render('post/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Post $post
     * @param Breadcrumbs $breadcrumbs
     *
     * @return Response
     * @ParamConverter("post", class="App:Post")
     */
    public function show(Post $post, Breadcrumbs $breadcrumbs): Response
    {
        $breadcrumbs->addItem('Home', $this->get('router')->generate('index'));
        $breadcrumbs->addItem($post->getTitle());

        return $this->render('post/show.html.twig', [
            'post' => $post,
        ]);
    }

    /**
     * @param Request $request
     * @param Post $post
     * @param Breadcrumbs $breadcrumbs
     *
     * @return RedirectResponse|Response
     * @ParamConverter("post", class="App:Post")
     */
    public function edit(Request $request, Post $post, Breadcrumbs $breadcrumbs)
    {
        $breadcrumbs->addItem('Home', $this->get('router')->generate('index'));
        $breadcrumbs->addItem($post->getTitle());
        $this->denyAccessUnlessGranted('edit', $post);
        $em = $this->getDoctrine()->getManager();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash(
                'notice',
                $this->translator->trans('notification.post_edited', [
                    '%title%' => $post->getTitle(),
                ])
            );

            return $this->redirectToRoute('blog');
        }

        return $this->render('post/edit.html.twig', [
            'form' => $form->createView(),
            'post' => $post,
        ]);
    }

    /**
     * @param Request $request
     * @param Post $post
     *
     * @return Response
     * @ParamConverter("post", options={"mapping" : {"postSlug" : "slug"}})
     */
    public function commentNew(Request $request, Post $post): Response
    {
        $comment = new Comment();
        $post->addComment($comment);

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $em->persist($comment);
            $em->flush();
            $this->addFlash(
                'notice',
                $this->translator->trans('notification.comment_created', [
                    '%title%' => $comment->getTitle(),
                ])
            );

            return $this->redirectToRoute('post_show', [
                'slug' => $post->getSlug(), ]);
        }

        return $this->render('comment/new.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    public function commentForm(Post $post): Response
    {
        $form = $this->createForm(CommentType::class);

        return $this->render('comment/new.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Post $post
     * @param TranslatorInterface $translator
     * @param Breadcrumbs $breadcrumbs
     *
     * @return Response
     * @ParamConverter("post", class="App:Post")
     */
    public function delete(Post $post, TranslatorInterface $translator, Breadcrumbs $breadcrumbs): Response
    {
        $breadcrumbs->addItem('Home', $this->get('router')->generate('index'));
        $breadcrumbs->addItem($post->getTitle());
        $this->denyAccessUnlessGranted('delete', $post);
        $em = $this->getDoctrine()->getManager();
        $post->getTags()->clear();
        $post->getComments()->clear();
        $em->remove($post);
        $em->flush();
        $this->addFlash(
            'notice',
            $this->translator->trans('notification.post_deleted')
        );

        return $this->redirectToRoute('posts');
    }

    /**
     * @param $message
     * @param $url
     * @throws \Doctrine\ORM\OptimisticLockException, Breadcrumbs $breadcrumbs
     *
     * @return RedirectResponse
     */
    public function sendNotification($message, $url): RedirectResponse
    {
        $manager = $this->get('mgilet.notification');
        $notif = $manager->createNotification('New post');
        $notif->setMessage($message);
        $notif->setLink($url);
        $em = $this->getDoctrine()->getManager();
        $currentUser = $this->getUser();
        $users = $em->getRepository(User::class)->findAll();
        foreach ($users as $user) {
            if ($currentUser->getId() !== $user->getId()) {
                $manager->addNotification([$user], $notif, true);
            }
        }

        return $this->redirectToRoute('index');
    }

    /**
     * @param Request $request
     * @param $count
     *
     * @return Response
     */
    public function search(Request $request, $count): Response
    {
        $paginatePosts = $this->pagination->paginationSearchQuery($request, $count);

        return $this->render('post/search.html.twig', [
            'posts' => $paginatePosts,
        ]);
    }
}
