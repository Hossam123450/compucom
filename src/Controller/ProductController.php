<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em){
        
    }


    #[Route('/products', name: 'app_product')]
    public function index(): Response
    {
        
        $products=$this->em->getRepository(Product::class)->findAll();
        return $this->render('product/product.html.twig',[
            'products'=>$products
        ]);
    }
}
