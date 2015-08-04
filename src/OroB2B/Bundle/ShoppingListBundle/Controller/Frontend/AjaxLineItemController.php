<?php

namespace OroB2B\Bundle\ShoppingListBundle\Controller\Frontend;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionDispatcher;

use OroB2B\Bundle\ProductBundle\Entity\Product;
use OroB2B\Bundle\CustomerBundle\Entity\AccountUser;
use OroB2B\Bundle\ShoppingListBundle\Entity\LineItem;
use OroB2B\Bundle\ShoppingListBundle\Form\Type\LineItemType;
use OroB2B\Bundle\ShoppingListBundle\Form\Handler\LineItemHandler;
use OroB2B\Bundle\ShoppingListBundle\Form\Type\FrontendLineItemWidgetType;
use OroB2B\Bundle\ShoppingListBundle\Form\Type\FrontendLineItemType;

class AjaxLineItemController extends Controller
{
    /**
     * Add Product to shopping list (create line item) form
     *
     * @Route(
     *      "/add-product/{productId}",
     *      name="orob2b_shopping_list_line_item_frontend_add_widget",
     *      requirements={"productId"="\d+"}
     * )
     * @Template("OroB2BShoppingListBundle:LineItem/Frontend/widget:add.html.twig")
     * @AclAncestor("orob2b_shoppinglist_add_product")
     * @ParamConverter("product", class="OroB2BProductBundle:Product", options={"id" = "productId"})
     *
     * @param Request $request
     * @param Product $product
     *
     * @return array|RedirectResponse
     */
    public function addProductAction(Request $request, Product $product)
    {
        /** @var AccountUser $accountUser */
        $accountUser = $this->getUser();
        $lineItem = (new LineItem())
            ->setProduct($product)
            ->setOwner($accountUser)
            ->setOrganization($accountUser->getOrganization());

        $form = $this->createForm(FrontendLineItemWidgetType::NAME, $lineItem);

        $handler = new LineItemHandler($form, $request, $this->getDoctrine());
        $result = $this->get('oro_form.model.update_handler')
            ->handleUpdate($lineItem, $form, null, null, null, $handler);

        if ($request->get('_wid')) {
            $result = $handler->updateSavedId($result);
        }

        return $result;
    }

    /**
     * Add Product to Shopping List
     *
     * @Route(
     *      "/products/{productId}",
     *      name="orob2b_shopping_list_frontend_add_product",
     *      requirements={"productId"="\d+"}
     * )
     * @AclAncestor("orob2b_shopping_list_frontend_create")
     * @ParamConverter("product", class="OroB2BProductBundle:Product", options={"id" = "productId"})
     *
     * @param Request  $request
     * @param Product  $product
     *
     * @return array|RedirectResponse
     */
    public function addProductFromViewAction(Request $request, Product $product)
    {
        $shoppingList = $this->get('orob2b_shopping_list.shopping_list.manager')
            ->getForCurrentUser($request->get('shoppingListId'));

        $lineItem = (new LineItem())
            ->setProduct($product)
            ->setShoppingList($shoppingList)
            ->setOwner($shoppingList->getOwner())
            ->setOrganization($shoppingList->getOrganization());

        $form = $this->createForm(FrontendLineItemType::NAME, $lineItem);

        $handler = new LineItemHandler($form, $request, $this->getDoctrine());
        $isFormHandled = $handler->process($lineItem);

        if (!$isFormHandled) {
            return new JsonResponse(['successful' => false, 'message' => (string)$form->getErrors()]);
        }

        $message = $this->get('translator')
            ->trans('orob2b.shoppinglist.line_item_save.flash.success', [], 'jsmessages');

        return new JsonResponse(['successful' => true, 'message' => $message]);
    }

    /**
     * Edit product form
     *
     * @Route("/update/{id}", name="orob2b_shopping_list_line_item_frontend_update_widget", requirements={"id"="\d+"})
     * @Template("OroB2BShoppingListBundle:LineItem:widget/update.html.twig")
     *
     * @param LineItem $lineItem
     *
     * @return array|RedirectResponse
     */
    public function updateAction(LineItem $lineItem)
    {
        $form = $this->createForm(LineItemType::NAME, $lineItem);

        return $this->get('oro_form.model.update_handler')
            ->handleUpdate($lineItem, $form, null, null, null);
    }

    /**
     * @Route("/{gridName}/massAction/{actionName}", name="orob2b_shopping_list_add_products_massaction")
     *
     * @param Request $request
     * @param string $gridName
     * @param string $actionName
     *
     * @return JsonResponse
     */
    public function addProductsMassAction(Request $request, $gridName, $actionName)
    {
        /** @var MassActionDispatcher $massActionDispatcher */
        $massActionDispatcher = $this->get('oro_datagrid.mass_action.dispatcher');

        $response = $massActionDispatcher->dispatchByRequest($gridName, $actionName, $request);

        $data = [
            'successful' => $response->isSuccessful(),
            'message' => $response->getMessage()
        ];

        return new JsonResponse(array_merge($data, $response->getOptions()));
    }

    /**
     * @return Response
     */
    public function getProductsAddBtnAction()
    {
        /** @var AccountUser $accountUser */
        $accountUser = $this->getUser();
        $repository = $this->getDoctrine()->getRepository('OroB2B\Bundle\ShoppingListBundle\Entity\ShoppingList');
        $shoppingLists = $repository->findByUser($accountUser);
        $currentShoppingList = $repository->findCurrentForAccountUser($accountUser);

        return $this->render('OroB2BShoppingListBundle:ShoppingList/Frontend:add_products_btn.html.twig', [
            'shoppingLists' => $shoppingLists,
            'currentShoppingList' => $currentShoppingList
        ]);
    }
}
