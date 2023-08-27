<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FirstController extends AbstractController
{
    #[Route('/template', name: 'template')]
    public function template(ManagerRegistry $doctrine,Request $request):Response
    {
        
       $contact=new Contact();
       $form=$this->createForm(ContactType::class,$contact);
       $form->handleRequest($request);
       if($form->isSubmitted()){
        $manager=$doctrine->getManager();
        $manager->persist($contact);
        $manager->flush();
        
       }
        return $this->render('template.html.twig',[
            'form'=>$form->createView()
        ]);
    }
}

