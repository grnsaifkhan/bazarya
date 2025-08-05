<?php

namespace App\Controller\Api;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Review;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\Common\Collections\ArrayCollection;


#[Route('/api/review', name: 'api_review')]
final class ReviewController extends AbstractController
{
    #[Route('/create', name: 'create_review', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {

        $data = json_decode($request->getContent(), true);
        $user = $security->getUser();


        if (!$user) {
            return new JsonResponse(['error' => 'Authentication required'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $productId = $data['productId'] ?? null;
        $rating = $data['rating'] ?? null;
        $comment = $data['comment'] ?? null;

        if (!$productId || !$rating) {
            return new JsonResponse(['error' => 'missing productId or rating'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $product = $em->getRepository(Product::class)->find($productId);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 404);
        }


        // Check if user has a delivered order with this product
        $orderItem = $em->createQueryBuilder()
            ->select('oi')
            ->from(OrderItem::class, 'oi')
            ->join('oi.orderRef', 'o')
            ->where('oi.product = :product')
            ->andWhere('o.user = :user')
            ->andWhere('o.status = :status')
            ->setParameter('product', $product)
            ->setParameter('user', $user)
            ->setParameter('status', OrderStatus::DELIVERED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();


        if (!$orderItem) {
            return new JsonResponse(['error' => 'You can only review ordered products after receiving'], JsonResponse::HTTP_FORBIDDEN);
        }


        $existingReview = $em->getRepository(Review::class)->findOneBy([
            'user' => $user,
            'product' => $product,
        ]);


        if ($existingReview) {
            return new JsonResponse(['error' => 'You have already reviewed this product'], 409);
        }


        // Create and save the review
        $review = new Review();
        $review->setUser($user);
        $review->setProduct($product);
        $review->setRating((int) $rating);
        $review->setComment($comment);
        $review->setCreatedAt(new \DateTime());




        $em->persist($review);
        $em->flush();




        // Prepare response data
        $responseData = [
            'id' => $review->getId(),
            'rating' => $review->getRating(),
            'comment' => $review->getComment(),
            'createdAt' => $review->getCreatedAt()->format('Y-m-d H:i:s'),
            'user' => [
                'email' => $user->getEmail(),
            ],
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
            ],
        ];

        return new JsonResponse($responseData, JsonResponse::HTTP_CREATED);
    }
}
