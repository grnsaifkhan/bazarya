<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/api/products')]
class ProductController extends AbstractController
{

    #[Route('', name: 'api_product_create', methods: ['POST'])]
    public function create(HttpFoundationRequest $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): JsonResponse
    {

        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Accept data from multipart/form-data
        $name = $request->request->get('name');
        $description = $request->request->get('description');
        $price = $request->request->get('price');
        $brand = $request->request->get('brand');
        $stock = $request->request->get('stock');
        $categoryId = $request->request->get('category_id');
        $imageFiles = $request->files->get('images'); // <-- note: plural

        // Basic validation
        if (!$name || !$description || !$price || !$brand || !$categoryId || !$stock || !$imageFiles) {
            return new JsonResponse(['error' => 'All fields including images[] are required.'], 400);
        }

        // Validate category
        $category = $entityManager->getRepository(Category::class)->find($categoryId);
        if (!$category) {
            return new JsonResponse(['error' => 'Invalid category ID'], 400);
        }

        // Create Product
        $product = new Product();
        $product->setName($name);
        $product->setDescription($description);
        $product->setPrice($price);
        $product->setBrand($brand);
        $product->setStock($stock);
        $product->setCategory($category);
        $product->setCreatedAt(new \DateTime());
        $product->setUpdatedAt(new \DateTime());

        $entityManager->persist($product);


        if (!is_array($imageFiles)) {
            $imageFiles = [$imageFiles];
        }


        // Handle multiple images
        $imageUrls = [];

        if (is_array($imageFiles)) {
            foreach ($imageFiles as $imageFile) {
                if ($imageFile && $imageFile->isValid()) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

                    try {
                        $imageFile->move(
                            $this->getParameter('uploads_directory'),
                            $newFilename
                        );
                    } catch (FileException $e) {
                        return new JsonResponse(['error' => 'Image upload failed: ' . $e->getMessage()], 500);
                    }

                    $productImage = new ProductImage();
                    $productImage->setImageUrl('/uploads/' . $newFilename);
                    $productImage->setAltText($originalFilename);
                    $productImage->setProduct($product); // Must link to product
                    $product->addProductImage($productImage); // Recommended to keep both sides in sync
                    $imageUrls[] = '/uploads/' . $newFilename;
                }
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Product created successfully',
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'brand' => $product->getBrand(),
                'image_urls' => $imageUrls,
            ]
        ], JsonResponse::HTTP_CREATED);
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
    public function show(int $id, EntityManagerInterface $entityManager, RequestStack $requestStack): JsonResponse
    {
        $baseUrl = $requestStack->getCurrentRequest()->getSchemeAndHttpHost();
        $product = $entityManager->getRepository(Product::class)->find($id);
        $category = $product->getCategory();

        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], JsonResponse::HTTP_NOT_FOUND);
        }


        // Build array of image URLs
        $imageUrls = [];
        foreach ($product->getProductImages() as $image) {
            // Adjust getImageName() to your actual getter for the filename
            $imageUrls[] = $baseUrl . $image->getImageUrl();
        }

        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'category' => $category ? [
                // 'id' => $category->getId(),
                'name' => $category->getName(),
                'description' => $category->getDescription()
            ] : null,
            'images' => $imageUrls,
        ];

        return new JsonResponse($data);
    }

    #[Route('', name: 'api_product_list', methods: ['GET'])]
    public function list(EntityManagerInterface $entityManager, RequestStack $requestStack): JsonResponse
    {
        $baseUrl = $requestStack->getCurrentRequest()->getSchemeAndHttpHost();
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

        $data = array_map(function (Product $product) use ($baseUrl) {
            $category = $product->getCategory();


            // Build array of image URLs
            $imageUrls = [];
            foreach ($product->getProductImages() as $image) {
                // Adjust getImageName() to your actual getter for the filename
                $imageUrls[] = $baseUrl . $image->getImageUrl();
            }

            return [
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'brand' => $product->getBrand(),
                'stock' => $product->getStock(),
                'images' => $imageUrls,
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
