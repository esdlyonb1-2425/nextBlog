<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Image;
use App\Entity\Post;
use App\Form\CommentForm;
use App\Form\ImageForm;
use App\Form\PostForm;
use App\Form\PostSearchForm;
use App\Form\UserSearchForm;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Liip\ImagineBundle\Form\Type\ImageType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PostController extends AbstractController
{
    #[Route('/', name: 'app_post')]
    #[Route('/myposts', name: 'app_myposts')]
    public function index( PaginatorInterface $paginator,PostRepository $repository, Request $request): Response
    {


        switch ($request->attributes->get('_route')) {
            case 'app_myposts':
                if(!$this->getUser()){return $this->redirectToRoute('app_login');}
                $posts = $repository->findBy(['author'=> $this->getUser()]);
                break;
            case 'app_post':
                $posts = $repository->findAll();
                break;
        }
        $pagination = $paginator->paginate(
            $repository->findAll(), /* query NOT result */
            $request->query->getInt('page', 1), /* page number */
            2 /* limit per page */
        );

        $userForm = $this->createForm(UserSearchForm::class);
        $searchForm = $this->createForm(PostSearchForm::class);
        return $this->render('post/index.html.twig', [
            'posts' => $posts,
            'searchForm' => $searchForm->createView(),
            'userForm' => $userForm->createView(),
            'pagination'=>$pagination
        ]);
    }
    #[Route('/post/show/{id}', name: 'app_post_show', priority: -1)]
    public function show(Post $post): Response
    {
        $comment = new Comment();
        $form = $this->createForm(CommentForm::class, $comment);
        return $this->render('post/show.html.twig', [
            'post' => $post,
            'form' => $form->createView(),
        ]);
    }
    #[Route('/post/create', name: 'app_post_create')]
public function create(Request $request, EntityManagerInterface $manager): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $post = new Post();
        $form = $this->createForm(PostForm::class, $post);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $post->setAuthor($this->getUser());
            $post->setCreatedAt(new \DateTime());
            $manager->persist($post);
            $manager->flush();
            $this->addFlash('success', 'Post created!');
            return $this->redirectToRoute('app_post_images', ['id' => $post->getId()]);
        }
        return $this->render('post/create.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/post/images/{id}/', name: 'app_post_images')]
    public function createImage(Post $post, Request $request, EntityManagerInterface $manager): Response
    {
        if (!$this->getUser() || !$post) {
            return $this->redirectToRoute('app_login');
        }
        if(!$this->getUser()->getPosts()->contains($post)){
            return $this->redirectToRoute('app_post_show', ['id' => $post->getId()]);
        }
        $image = new Image();
        $form = $this->createForm(ImageForm::class, $image);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $image->setPost($post);
            $manager->persist($image);
            $manager->flush();
            return $this->redirectToRoute('app_post_images', ['id' => $post->getId()]);
        }

        return $this->render('post/createImage.html.twig', [
            'form' => $form->createView(),
            'post'=>$post,
        ]);
    }

#[Route('/post/image/delete/{id}/', name: 'app_post_image_delete')]
public function deleteImage(Image $image, EntityManagerInterface $manager): Response
{
    if(!$this->getUser()){
        return $this->redirectToRoute('app_login');
    }

    $post = $image->getPost();
    if($post->getAuthor() != $image->getPost()){
        return $this->redirectToRoute('app_post_show', ['id' => $post->getId()]);

    }



    if(!$this->getUser()->getPosts()->contains($post)){
        return $this->redirectToRoute('app_post_show', ['id' => $post->getId()]);
    }
    $manager->remove($image);
    $manager->flush();
    return $this->redirectToRoute('app_post_images', ['id' => $post->getId()]);
}



}
