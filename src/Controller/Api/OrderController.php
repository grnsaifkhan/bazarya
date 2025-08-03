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


    #[Route('/api/orders/{id}', name: 'fetch_order', methods: ['GET'])]
    public function getOrder(int $id, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }


        // Fetch order by ID
        $order = $em->getRepository(Order::class)->find($id);

        // Check if order exists and belongs to this user
        if (!$order || $order->getUser() !== $user) {
            return new JsonResponse(['error' => 'Order not found or access denied'], 404);
        }


        // Format order items
        $items = [];
        foreach ($order->getOrderItems() as $item) {
            $items[] = [
                'product_name' => $item->getProduct()->getName(),
                'size' => $item->getSizeLabel(),
                'quantity' => $item->getQuantity(),
                'price_each' => $item->getPriceEach(),
            ];
        }

        // Build final response
        return new JsonResponse([
            'id' => $order->getId(),
            'status' => $order->getStatus()->value,
            'shippingAddress' => $order->getShippingAddress(),
            'orderDate' => $order->getOrderDate()->format('Y-m-d H:i'),
            'total' => $order->getTotal(),
            'items' => $items,
        ], 200);
    }


    #[Route('/api/orders/{id}/calcel', name: 'cancel_order', methods: ['PATCH'])]
    public function cancelOrder(int $id, Security $security, EntityManagerInterface $em)
    {
        $user = $security->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Access denied'], JsonResponse::HTTP_FORBIDDEN);
        }


        $order = $em->getRepository(Order::class)->find($id);


        if (!$order || $order->getUser() !== $user) {
            return new JsonResponse(['error' => 'Order not found or access denied'], JsonResponse::HTTP_NOT_FOUND);
        }


        if ($order->getStatus() !== OrderStatus::PENDING) {
            return new JsonResponse([
                'error' => 'You can only cancel orders that are still pending.'
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        $order->setStatus(OrderStatus::CANCELLED);
        $em->flush();

        return new JsonResponse([
            'message' => 'Order cancelled successfully.',
            'orderId' => $order->getId(),
            'newStatus' => $order->getStatus()->value,
        ]);
    }
}
