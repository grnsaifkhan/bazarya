<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AdminOrderController extends AbstractController
{
    #[Route('/api/admin/orders', name: 'app_api_admin_order', methods: ['GET'])]
    public function listAllOrdersForAdmin(Security $security, EntityManagerInterface $em): JsonResponse
    {
        $user = $security->getUser();

        if (!$user || !in_array("ROLE_ADMIN", $user->getRoles())) {
            return new JsonResponse(['error' => 'Access denied'], JsonResponse::HTTP_FORBIDDEN);
        }

        $orders = $em->getRepository(Order::class)->findAll();


        $response = [];

        foreach ($orders as $order) {
            $items = [];

            foreach ($order->getOrderItems() as $item) {
                $items[] = [
                    'product_name' => $item->getProduct()->getName(),
                    'quantity' => $item->getQuantity(),
                    'size' => $item->getSizeLabel(),
                    'price_each' => $item->getPriceEach(),
                ];
            }

            $response[] = [
                'order_id' => $order->getId(),
                'customer' => $order->getUser()->getEmail(), // or getUsername()
                'status' => $order->getStatus()->value,
                'order_date' => $order->getOrderDate()->format('Y-m-d H:i'),
                'shipping_address' => $order->getShippingAddress(),
                'total' => $order->getTotal(),
                'items' => $items,
            ];
        }

        return new JsonResponse($response);
    }



    #[Route('/api/admin/orders/{id}/status', name: 'admin_update_order_status', methods: ['PATCH'])]
    public function updateOrderStatus(int $id, Request $request, EntityManagerInterface $em, Security $security): JsonResponse
    {
        $user = $security->getUser();


        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse(['error' => 'Access denied'], JsonResponse::HTTP_FORBIDDEN);
        }


        $order = $em->getRepository(Order::class)->find($id);

        if (!$order) {
            return new JsonResponse(['error' => 'Order not found'], JsonResponse::HTTP_NOT_FOUND);
        }


        $data = json_decode($request->getContent(), true);


        if (empty($data['status'])) {
            return new JsonResponse(['error' => 'Missing status'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $newStatus = strtolower($data['status']);

        if (!OrderStatus::tryFrom($newStatus)) {
            return new JsonResponse(['error' => 'Invalid status'], JsonResponse::HTTP_BAD_REQUEST);
        }


        $order->setStatus(OrderStatus::from($newStatus));
        $em->flush();


        return new JsonResponse([
            'message' => 'Order status updated',
            'order_id' => $order->getId(),
            'new_status' => $order->getStatus()->value,
        ], JsonResponse::HTTP_OK);
    }
}
