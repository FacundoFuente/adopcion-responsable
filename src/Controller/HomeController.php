<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\SectionRepository;
use Symfony\Component\HttpFoundation\Request;

class HomeController extends AbstractController //definimos nuestro controlador
{
    #[Route('/', name: 'homepage')] //definimos la ruta del controlador
    public function homepage(Request $request): Response
    {
        return $this->render('homepage/homepage.html.twig', []);
        //return $this->render('homepage/404.html.twig', []);
    }
}