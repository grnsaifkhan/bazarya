<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\OrderStatus;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController extends AbstractController
{
    #[Route('/api/orders', name: 'create_order', methods: ['POST'])]
    public function createOrder(HttpFoundationRequest $request, EntityManagerInterface $em, Security $security, ProductRepository $productRepository): JsonResponse
    {
        $user = $security->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || !is_array($data['items']) || empty($data['shippingAddress'])) {
            return new JsonResponse(["error" => 'Invalid request data'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $order = new Order();
        $order->setUser($user);
        $order->setOrderDate(new \DateTime());
        $order->setStatus(OrderStatus::PENDING);
        $order->setShippingAddress($data['shippingAddress']);


        $total = 0;

        foreach ($data['items'] as $itemData) {
            if (!isset($itemData['product_id'], $itemData['quantity'], $itemData['sizeLabel'])) {
                return new JsonResponse(['error' => 'Missing item data'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $product = $productRepository->find($itemData['product_id']);

            if (!$product) {
                return new JsonResponse(['error' => 'Product not found'], JsonResponse::HTTP_NOT_FOUND);
            }

            $price = $product->getPrice();

            $orderItem = new OrderItem();
            $orderItem->setOrderRef($order);
            $orderItem->setProduct($product);
            $orderItem->setQuantity($itemData['quantity']);
            $orderItem->setSizeLabel($itemData['sizeLabel']);
            $orderItem->setPriceEach($price);

            $order->addOrderItem($orderItem);
            $em->persist($orderItem);

            $total += $price * $itemData['quantity'];
        }

        $order->setTotal($total);
        $em->persist($order);
        $em->flush();


        return new JsonResponse(["message" => "Order created successfully", 'order_id' => $order->getId()], JsonResponse::HTTP_ACCEPTED);
    }
}
