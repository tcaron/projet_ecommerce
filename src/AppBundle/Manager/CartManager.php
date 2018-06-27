<?php

namespace AppBundle\Manager;

use AppBundle\Entity\Product;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartManager
{
    /**
     * @var RegistryInterface
     */
    private $doctrine;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * CartManager constructor.
     * @param RegistryInterface $doctrine
     * @param SessionInterface $session
     */
    public function __construct(RegistryInterface $doctrine, SessionInterface $session)
    {
        $this->doctrine = $doctrine;
        $this->session = $session;
    }

    /**
     * @param array $cart
     * @return array
     */
    public function getProductsForDisplay(array $cart)
    {
        $productRepository = $this->doctrine->getRepository(Product::class);

        $products = [];
        $totalAmount = 0;

        foreach ($cart as $id => $quantity) {
            $product = $productRepository->find($id);

            $products[$id]['product'] = $product;
            $products[$id]['qty'] = $quantity;

            $totalAmount += $product->getPrice() * $quantity;
        }

        return [
            'products' => $products,
            'totalAmount' => $totalAmount,
        ];
    }

    /**
     * @param Product $product
     */
    public function removeProduct(Product $product)
    {
        $cart = $this->session->get('cart');

        // remettre le stock en base
        $product->provisionStock($cart[$product->getId()]);

        $em = $this->doctrine->getManager();
        $em->persist($product);
        $em->flush();

        // supprimer le produit du panier en session
        unset($cart[$product->getId()]);
        $this->session->set('cart', $cart);
        $this->session->save();
    }

    public function removeAllProducts()
    {
        $cart = $this->session->get('cart');
        $em = $this->doctrine->getManager();
        foreach($cart as $key => $value){
            $product = $this->doctrine->getRepository(Product::class)->find($key);
            $product->provisionStock($value);
            $em->persist($product);
            unset($cart[$product->getId()]);
        }
        $em->flush();

        $this->session->set('cart', $cart);
        $this->session->save();
    }

    /**
     * @param $productId
     * @return int|mixed
     */
    public function quantityOfProductInCart($productId)
    {
        $cart = $this->session->get('cart') ?? [];

        $quantity = 0;

        foreach ($cart as $id => $qty) {
            if ($productId === $id) {
                // Le produit est présent dans le panier
                $quantity = $qty;
                break;
            }
        }

        return $quantity;
    }

    public function addToCart(Product $product, $qte){

        $currentStock = $product->getStock();

        if ($currentStock === 0) {
            throw new \Exception('Produit indisponible');
        }

        $product->decrementStock($qte);

        $em = $this->doctrine->getManager();
        $em->persist($product);
        $em->flush();

        $cart = $this->session->get('cart');

        $currentQty = $cart[$product->getId()] ?? 0;

        // on ajoute le produit au panier
        $cart[$product->getId()] = $currentQty + $qte;

        $this->session->set('cart', $cart);
        $this->session->save();

        return $product;
    }
}