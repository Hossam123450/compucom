<?php

namespace App\Controller;

use App\Entity\Product;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CartController extends AbstractController
{
    #[Route('/My-cart', name: 'cart_index')]
    public function index(CartService $cartService): Response
    {
      
       
        return $this->render('cart/index.html.twig',[
            'cart' => $cartService->getTotal()
        ]);
    }

    #[Route('/My-cart/add/{id<\d+>}', name: 'cart_add')]
    public function addToCart(CartService $cartService,int $id): Response
    {
        $cartService->addToCart($id);
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/My-cart/remove/{id<\d+>}', name: 'cart_remove')]
    public function removeToCart(CartService $cartService,int $id): Response
    {
        $cartService->removeToCart($id);
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/My-cart/decrease/{id<\d+>}',name:'cart_decrease')]
    public function decrease(CartService $cartService,int $id):RedirectResponse
    {
        $cartService->decrease($id);
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/My-cart/removeAll', name: 'cart_removeAll')]
    public function removeAll(CartService $cartService): Response
    {
        $cartService->removeCartAll();
        return $this->redirectToRoute('app_product');
    }
}
