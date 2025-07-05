<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/products')]
class ProductController extends AbstractController
{

    #[Route('', name: 'api_product_create', methods: ['POST'])]
    public function create(HttpFoundationRequest $request, EntityManagerInterface $entityManager): JsonResponse
    {


        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);


        $requiredFields = ['name', 'description', 'price', 'brand', 'category_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return new JsonResponse(['error' => "$field is required"], JsonResponse::HTTP_BAD_REQUEST);
            }
        }


        $category = $entityManager->getRepository(Category::class)->find($data['category_id']);

        if (!$category) {
            return new JsonResponse(['error' => 'Invalid category ID'], JsonResponse::HTTP_BAD_REQUEST);
        }


        $product = new Product();
        $product->setName($data['name']);
        $product->setDescription($data['description']);
        $product->setPrice($data['price']);
        $product->setBrand($data['brand']);
        $product->setCategory($category);
        $product->setCreatedAt(new \DateTime());
        $product->setUpdatedAt(new \DateTime());


        //save product
        try {
            $entityManager->persist($product);
            $entityManager->flush();


            $category = $product->getCategory();

            $categoryData = null;
            if ($category !== null) {
                $categoryData = [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'description' => $category->getDescription(),
                ];
            }

            return new JsonResponse([
                'message' => 'Product created successfully',
                'product' => [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'description' => $product->getDescription(),
                    'price' => $product->getPrice(),
                    'category' => $categoryData,
                ]
            ], JsonResponse::HTTP_CREATED);
        } catch (Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to create product',
                'details' => $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    #[Route('/k', name: 'api_product_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository, SerializerInterface $serializer): JsonResponse
    {
        $product = $productRepository->findAll();
        $json = $serializer->serialize($product, 'json');

        return new JsonResponse($json, 200, [], true);
        // return new JsonResponse(['token' => 'ieisdni']);
    }



    #[Route('/{id}', name: 'api_product_show', methods: ['GET'])]
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
            // 'image' => $product->getImage(),
        ];

        return new JsonResponse($data);
    }

    #[Route('', name: 'api_product_list', methods: ['GET'])]
    public function list(EntityManagerInterface $entityManager): JsonResponse
    {
        $products = $entityManager->getRepository(Product::class)->findAll();

        // $data = array_map(fn($product) => [
        //     //'id' => $product->getId(),
        //     'name' => $product->getName(),
        //     'description' => $product->getDescription(),
        //     'price' => $product->getPrice(),
        //     'stock' => $product->getStock(),
        //     'category' => $product->getCategory(),
        //     'image' => $product->getImage(),
        // ], $products);

        $data = array_map(function (Product $product) {
            $category = $product->getCategory();

            return [
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'brand' => $product->getBrand(),
                'stock' => $product->getStock(),
                // 'createdAt' => $product->getCreatedAt(),
                'updatedAt' => $product->getUpdatedAt(),
                'category' => $category ? [
                    // 'id' => $category->getId(),
                    'name' => $category->getName(),
                    'description' => $category->getDescription()
                ] : null,

            ];
        }, $products);

        return new JsonResponse(['data' => $data]);
    }
}
