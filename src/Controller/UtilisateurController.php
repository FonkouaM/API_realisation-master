<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UtilisateurRepository;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;


class UtilisateurController extends AbstractController
{
    
    /**
     * @Route("/api/register", name="utilisateur_new", methods={"POST"})
     */
    public function new(Request $request, UtilisateurRepository $userRepo, SerializerInterface $serializer, UserPasswordHasherInterface $hasherPassword, EntityManagerInterface $em)
    {
        $requestData = \json_decode($request->getContent(), true);
        
        $email = $requestData['email'];

       $userData = $userRepo->findOneBy(['email'=>$email]);
       $status = 201; 

       if(empty($userData))
        {
            $jsonReceive = $request->getContent();
           
            $utilisateur = $serializer->deserialize($jsonReceive, Utilisateur::class, 'json');
            
            $hash = $hasherPassword->hashPassword($utilisateur, $utilisateur->getPassword());
            $utilisateur->setPassword($hash);
            
            $em = $this->getDoctrine()->getManager();
            $em->persist($utilisateur);
            $em->flush();
        }else{
            $status = 400;
            $message='Cet email existe deja';

            return $this->json(['status'=>$status, 'message'=>$message]);
        }
       
        return $this->json(['status' => $status, 'message' => 'Utilisateur cree avec success']);
    }
    
    /**
     * @Route("/api/users/list", name="utilisateur_index", methods={"GET"})
     */
    public function index(Request $request,UtilisateurRepository $utilisateurRepo): Response
    {
        $token = $request->headers->get('Authorization');
        $status = 200;

        $utilisateur = $utilisateurRepo->findOneBy(['token'=>$token]);
        
        if(empty($utilisateur)){
            $etat = 400;
            $message = 'Vous n\'êtes pas connecte!';
            return $this->json(['status'=>$etat, 'message'=>$message]);
        }
        $utilisateur = $utilisateurRepo->findAll();
        if(empty($utilisateur)){
            $etat = 404;
            $message = 'Aucun utilisateur trouve!';
            return $this->json(['status'=>$etat, 'message'=>$message]);
        }
        for($i = 0; $i<= sizeof($utilisateur)-1; $i++){
            $utilisateurs[$i] = ([          
                'user_id'=>$utilisateur[$i]->getId(),
                'user_email'=>$utilisateur[$i]->getEmail(),
                'user_name'=>$utilisateur[$i]->getNom(),
                'user_firstName'=>$utilisateur[$i]->getPrenom(),
                'user_phone'=>$utilisateur[$i]->getTelephone(),
                'user_dateCreated'=>$utilisateur[$i]->getCreatedAt(),
                'user_dateUpdated'=>$utilisateur[$i]->getUpdatedAt(),
            ]);
        }
       
        return $this->json(['status' => $status, 'users' =>$utilisateurs]);
    }
    
    /**
     * @Route("/api/utilisateurs/list/{id}", name="utilisateur_show", methods={"GET"})
     */
    public function show($id,Request $request, UtilisateurRepository $userRepo): Response
    {
        $token = $request->headers->get('Authorization');
        $status = 200;

        $user = $userRepo->findOneBy(['token'=>$token]);
        $utilisateur = $this->getDoctrine()->getRepository(Utilisateur::class)->find($id);

        if(empty($user)){
            $etat = 400;
            $message = 'Vous n\'êtes pas connecte!';
            return $this->json(['status'=>$etat, 'message'=>$message]);
        
        }elseif (!$utilisateur) {
           
            return $this->json(['status'=>404, 'message'=>'Aucun utilisateur trouvé pour cet id : '.$id]);
        }
        $status = 200;
        $utilisateur =[
                    'user_id'=>$utilisateur->getId(),
                    'user_email'=>$utilisateur->getEmail(),
                    'user_name'=>$utilisateur->getNom(),
                    'user_firstName'=>$utilisateur->getPrenom(),
                    'user_phone'=>$utilisateur->getTelephone(),
                    // 'userPassword'=>$utilisateur->getPassword(),
                    'user_dateCreated'=>$utilisateur->getCreatedAt(),
                    'user_dateUpdated'=>$utilisateur->getUpdatedAt(),
        ];
        return $this->json(['status' => $status, 'utilisateur' =>$utilisateur]);
    }

    /**
     * @Route("/api/generate_token", name="generate_token", methods="POST")
     */
    public function generateToken() //: JsonResponse
    {
        
    }

    /**
     * @Route("/api/login", name="connexion_login", methods={"POST"})
     */
    public function login(Request $request, UtilisateurRepository $userRepo, UserPasswordHasherInterface $hasherPassword, EntityManagerInterface $em): JsonResponse
    {
        $user = \json_decode($request->getContent(), true);
        if(empty($user['email'])){
            $status = 400;
            $message = 'Veuillez entrer votre email!';
            return $this->json(['status'=>$status, 'message'=>$message]);

        }elseif (empty($user['password'])){
            $status = 400;
            $message = 'Veuillez entrer un mot de passe!';
            return $this->json(['status'=>$status, 'message'=>$message]);
        }
        $client = HttpClient::create();
        
        $response = $client->request('POST', 'http://localhost:8000/api/generate_token', [
            'headers' => [
                
                'Content-Type' => 'application/json'
            ],
            'json' => [
                'email' => $user['email'],
                'password' => $user['password'], 
            ],
        ]);
        $statusCode = $response->getStatusCode();

        if($statusCode === 401){

           $status = 401;
           $message = 'Vos identifiants sont incorrects';
           return $this->json(['status'=>$status, 'message'=>$message]);
        }else{
            $token = $response->toArray();
           
            $status = 201;
            $email = $user['email'];
            $utilisateur = $userRepo->findOneBy(['email'=>$email]);
            $utilisateur->setToken('Bearer '.$token["token"])
                        ->setStartDate(new \DateTime())
                        ->setEndDate(new \DateTime('+15day'));
               
            $em = $this->getDoctrine()->getManager();
            $em->persist($utilisateur);
            $em->flush();
            return $this->json(['status'=>$status, 'token'=>'Bearer '.$token["token"],
                    // 'user_id'=>$utilisateur->getEmail(),
                    'user_email'=>$utilisateur->getEmail(),
                    'user_name'=>$utilisateur->getNom(),
                    'user_firstname'=>$utilisateur->getPrenom(),
                    'user_phone'=>$utilisateur->getTelephone()
            ]);        
        }
    }

    /**
     * @Route("/api/logout", name="deconnexion_logout")
     */
    public function logout(Request $request, UtilisateurRepository $userRepo, EntityManagerInterface $em): JsonResponse
    {
        $token = $request->headers->get('Authorization');
        $status = 200;

        $utilisateur = $userRepo->findOneBy(['token'=>$token]);
        if(empty($utilisateur)){
            $etat = 400;
            $message = 'Vous n\'avez jamais ete connecte!';
            return $this->json(['status'=>$etat, 'message'=>$message]);
        }
        $utilisateur->setToken(null)
                    ->setStartDate(null)
                    ->setEndDate(null);
                    
        $em = $this->getDoctrine()->getManager();
        $em->persist($utilisateur);
        $em->flush();

        return $this->json(['status'=>$status, 'message'=>'Deconnexion valide']);
    }
}