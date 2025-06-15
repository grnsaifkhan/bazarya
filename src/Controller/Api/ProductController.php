<?php

namespace App\Controller\Api;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    // #[Route('/api/product', name: 'app_api_product')]
    // public function index(): JsonResponse
    // {
    //     return new JsonResponse(['token' => 'ieisdni']);
    // }

    #[Route('/api/product', name: 'api_product_create', methods: ['POST'])]
    public function create(HttpFoundationRequest $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $requiredFields = ['name', 'description', 'price', 'stock', 'category'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return new JsonResponse(['error' => "$field is required"], JsonResponse::HTTP_BAD_REQUEST);
            }
        }


        $product = new Product();
        $product->setName($data['name']);
        $product->setDescription($data['description']);
        $product->setPrice($data['price']);
        $product->setCategory($data['category']);

        //save product
        try {
            $entityManager->persist($product);
            $entityManager->flush();

            return new JsonResponse([
                'message' => 'Product created successfully',
                'product' => [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'description' => $product->getDescription(),
                    'price' => $product->getPrice(),
                    'category' => $product->getCategory(),
                ]
            ], JsonResponse::HTTP_CREATED);
        } catch (Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to create product',
                'details' => $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/products', name: 'api_product_list', methods: ['GET'])]
    public function list(EntityManagerInterface $entityManager): JsonResponse
    {
        $products = $entityManager->getRepository(Product::class)->findAll();

        $data = array_map(fn($product) => [
            //'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'category' => $product->getCategory(),
            'image' => $product->getImage(),
        ], $products);

        return new JsonResponse(['data' => $data]);
    }


    #[Route('/api/product/{id}', name: 'api_product_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $entityManager->getRepository(Product::class)->find($id);

        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'category' => $product->getCategory(),
            'image' => $product->getImage(),
        ];

        return new JsonResponse($data);
    }
}
