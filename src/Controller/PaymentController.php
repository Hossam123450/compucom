<?php 
namespace App\Controller;

use App\Entity\User;
use App\Entity\Product;
use App\Entity\Order;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;
use Stripe\Checkout\Session;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Stripe\Stripe;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PaymentController extends AbstractController
{
    private EntityManagerInterface $em;
    private UrlGeneratorInterface $generator;

    public function __construct(EntityManagerInterface $em,UrlGeneratorInterface $generator)
    {
        $this->em=$em;
        $this->generator=$generator;
    }

    /**
     * @return PayPalHttpClient
     */
    public function getPaypalClient():PayPalHttpClient
    {
        $clientId="AYlJsu_keLrVUUaTmqRLgzVEf3h68ocoHPxcIK4H2mvwmleowP7_Bzi_--9sQrYwCDgbiswft0hhjBxB";
        $clientSecret="EBv_ELCk1h2cDgniXvLcaI5KwgONrgqFHjtgnYYVr8Z4iSQHS89hyez_2S05tXZ1XmL11hhJ_1kc4OBM";
        $environment=new SandboxEnvironment($clientId,$clientSecret);
        return new PayPalHttpClient($environment);

    }

    #[Route('/order/create-session-stripe/{reference}',name:'payment_stripe',methods:['POST'])]
    public function stripeCheckout($reference):RedirectResponse
    {

        $productStripe=[];

        $order=$this->em->getRepository(Order::class)->findOneBy(['reference'=>$reference]);
        
        if(!$order)
        {
            return $this->redirectToRoute('cart_index');
        }
        
        foreach($order->getRecapDetails()->getValues() as $product){
            $productData=$this->em->getRepository(Product::class)->findOneBy(['title'=>$product->getProduct()]);
            $productStripe[]=[
                'price_data'=>[
                    'currency'=>'eur',
                    'unit_amount'=>$productData->getPrice(),
                    'product_data'=>[
                        'name'=>$product->getProduct()
                    ]
                ],
                'quantity'=>$product->getQuantity(),
            ];

        }

        $productStripe[]=[
            'price_data'=>[
                'currency'=>'eur',
                'unit_amount'=>$order->getTransporterPrice(),
                'product_data'=>[
                    'name'=>$order->getTransporterName()
                ]
            ],
            'quantity'=>1,
        ];

        Stripe::setApiKey('sk_test_51Ns9e8LTZKU8ChCA2ke3eCCRgTAq4JK6DPyyttQ2L61s3nhVZZhDja9RUKf1Mswbko5peGMvxb1BcMjalGS8w8tO00VkNblpd6');
            
            $checkout_session = Session::create([
            'customer_email'=>$this->getUser()->getEmail(),
            'payment_method_types'=>['card'],
            'line_items' => [
                $productStripe
            ],
            'mode' => 'payment',
            'success_url' => $this->generator->generate('payment_success',[
                'reference'=>$order->getReference()
            ], UrlGeneratorInterface::ABSOLUTE_URL),
            'cancel_url' => $this->generator->generate('payment_error',[
                'reference'=>$order->getReference()
            ],UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
        
        $order->setStripeSessionId($checkout_session->id);
        $this->em->flush();

        return new RedirectResponse($checkout_session->url);
            
    }


    #[Route('/order/success/{reference}',name:'payment_success')]
    public function StripeSuccess($reference,CartService $service):Response
    {
        return $this->render('order/success.html.twig');

    }

    #[Route('/order/error/{reference}',name:'payment_error')]
    public function StripeError($reference,CartService $service):Response
    {
        return $this->render('order/error.html.twig');

    }


    #[Route('/order/create-session-paypal/{reference}',name:'payment_paypal',methods:['POST'])]
    public function createSessionPaypal($reference):RedirectResponse
    {
        $order=$this->em->getRepository(Order::class)->findOneBy(['reference'=>$reference]);
        
        if(!$order)
        {
            return $this->redirectToRoute('cart_index');
        }

        $items=[];
        $itemTotal=0;

        foreach($order->getRecapDetails()->getValues() as $product){
            $items[]=[
                'name'=>$product->getProduct(),
                'quantity'=>$product->getQuantity(),
                'unit_amount'=>[
                    'value'=>$product->getPrice(),
                    'currency_code'=>'EUR'
                ]
            ];
            $itemTotal += $product->getPrice() * $product->getQuantity();
        }
        $total = $itemTotal + $order->getTransporterPrice();

        $request=new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body=[
            'intent'=>'CAPTURE',
            'purchase_units'=>[
                [
                    'amount'=>[
                        'currency_code'=>'EUR',
                        'value'=>$total,
                        'breakdown'=>[
                            'item_total'=>[
                                'currency_code'=>'EUR',
                                'value'=>$itemTotal,
                            ],
                            'shipping'=>[
                                'currency_code'=>'EUR',
                                'value'=>$order->getTransporterPrice()
                            ]
                        ]
                    ],
                    'items'=>$items
                ]
            ],
            'application_context'=>[
                'return_url'=>$this->generator->generate(
                    'payment_success_paypal',
                    ['reference'=>$order->getReference()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'cancel_url'=>$this->generator->generate(
                    'payment_error',
                    ['reference'=>$order->getReference()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                )
            ]
        ];

        $client=$this->getPaypalClient();
        $response=$client->execute($request);

        if($response->statusCode != 201){
            return $this->redirectToRoute('cart_index');
        }

        $approvaLink='';
        foreach($response->result->links as $link){
            if($link->rel === 'approve'){
                $approvaLink=$link->href;
                break;
            }
        }
        if(empty($approvaLink)){
            return $this->redirectToRoute('cart_index');
        }

        $order->setPaypalOrderId($response->result->id);

        $this->em->flush();

        return new RedirectResponse($approvaLink);
    }

    #[Route('/order/success-paypal/{reference}',name:'payment_success_paypal')]
    public function successPaypal($reference,CartService $cartService)
    {
        $order=$this->em->getRepository(Order::class)->findOneBy(['reference'=>$reference]);
        if(!$order || $order->getUser() !== $this->getUser()){
            return $this->redirectToRoute('cart_index');
        }
        if(!$order->isIsPaid()){
            $cartService->removeCartAll();
            $order->setIsPaid(1);
            $this->em->flush();
        }
        return $this->render('order/success.html.twig',[
            'order'=>$order
        ]);
        

    }

    #[Route('/order/error/{reference}',name:'payment_error')]
    public function errorData($reference)
    {
        $order=$this->em->getRepository(Order::class)->findOneBy(['reference'=>$reference]);
        if(!$order || $order->getUser() !== $this->getUser()){
            return $this->redirectToRoute('cart_index');
        }
        return $this->render('order/error.html.twig',[
            'order'=>$order
        ]);
    }

    
}