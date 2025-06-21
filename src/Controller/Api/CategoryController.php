<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    // #[Route('', name: 'app_api_category')]
    // public function index(): Response
    // {
    //     return $this->render('api/category/index.html.twig', [
    //         'controller_name' => 'Api/CategoryController',
    //     ]);
    // }


    #[Route('', name: 'api_category_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {

        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }


        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['description'])) {
            return new JsonResponse(['error' => 'Name and Description are required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $category = new Category();
        $category->setName($data['name']);
        $category->setDescription($data['description']);

        $em->persist($category);
        $em->flush();

        return new JsonResponse([
            'message' => 'Category created',
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
            ],
        ], JsonResponse::HTTP_CREATED);
    }


    #[Route('', name: 'api_category_list', methods: ['GET'])]
    public function list(CategoryRepository $categoryRepository): JsonResponse
    {
        $categories = $categoryRepository->findAll();

        $data = array_map(fn(Category $category) => [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription()
        ], $categories);


        return new JsonResponse($data);
    }


    #[Route('/{id}', name: 'api_category_show', methods: ['GET'])]
    public function show(int $id, CategoryRepository $categoryRepository): JsonResponse
    {
        $category = $categoryRepository->find($id);

        if (!$category) {
            return new JsonResponse(['error' => 'Category not found'], 404);
        }

        return new JsonResponse([
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
        ]);
    }


    #[Route('/{id}', name: 'api_category_update', methods: ['PUT'])]
    public function update(Category $category, Request $request, EntityManagerInterface $em): JsonResponse
    {

        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['name'])) {
            $category->setName($data['name']);
        }
        if (!empty($data['description'])) {
            $category->setDescription($data['description']);
        }



        $em->flush();

        return new JsonResponse([
            'message' => 'Category updated successfully',
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
            ]
        ]);
    }


    #[Route('/{id}', name: 'api_category_delete', methods: ['DELETE'])]
    public function delete(Category $category, EntityManagerInterface $em): JsonResponse
    {

        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }


        $em->remove($category);
        $em->flush();

        return new JsonResponse(['message' => 'Category deleted successfully']);
    }
}
