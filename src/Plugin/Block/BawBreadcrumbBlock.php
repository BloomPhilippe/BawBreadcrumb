<?php

namespace Drupal\baw_breadcrumb\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\block\Entity\Block;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'article' block.
 *
 * @Block(
 *   id = "baw_breadcrumb",
 *   admin_label = @Translation("Breadcrumb"),
 *   category = @Translation("Create by BAW")
 * )
 */
class BawBreadcrumbBlock extends BlockBase {

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state) {
        $form = parent::blockForm($form, $form_state);

        $config = $this->getConfiguration();

        $types = \Drupal::entityTypeManager()
            ->getStorage('node_type')
            ->loadMultiple();

        foreach ($types as $key => $type){
            $form['view_for_'.$type->id()] = array(
                '#type' => 'textfield',
                '#title' => $this->t('ID Vue pour ').$type->label(),
                '#description' => $this->t('Veuillez indiquer l\'id de la vue avec la page liée au type de contenu ').$type->label().'. '.PHP_EOL.$this->t(' Exemple : type de contenu produit, la vue est produits.page_1'),
                '#default_value' => isset($config['view_for_'.$type->id()]) ? $config['view_for_'.$type->id()] : '',
            );
        }

        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state) {
        parent::blockSubmit($form, $form_state);
        $values = $form_state->getValues();
        $this->configuration['hello_block_name'] = $values['hello_block_name'];
    }

    function getPreviousLink(){
        $previousUrl = \Drupal::request()->server->get('HTTP_REFERER');
        $fake_request = Request::create($previousUrl);
        $url_object = \Drupal::service('path.validator')->getUrlIfValid($fake_request->getRequestUri());
        $route_name = $url_object->getRouteName();
        $path = $fake_request->getPathInfo();
        $path_args = explode('/', $path);
        $first_argument = end($path_args);

    }

    function routeToUrl($route_name, $arg, $node = null){

        $options = ['absolute' => TRUE];
        $page_title = "contact";
        $url = "";
        if(!is_null($node)){
            $page_title = $node->getTitle();
            $url = Url::fromRoute($route_name, ['node' => $node->id()], $options);
        }elseif(strpos($route_name, 'view') !== false){
            $route_name_array = explode(".", $route_name);
            $view = \Drupal\views\Views::getView($route_name_array[1]);
            $view->setDisplay($route_name_array[2]);
            $page_title = $view->getTitle();
            $url = Url::fromRoute($route_name, [$arg], $options);
        }else{
            $url = Url::fromRoute($route_name, [], $options);
        }

        $link = \Drupal::l($page_title, $url);

        return array(
            'link' => $link,
            'name' => $page_title,
        );

    }

    function generateBreadcrumb(){
        
        $breadcrumb = array();
        $node = \Drupal::routeMatch()->getParameter('node');

        $request = \Drupal::request();
        $route_match = \Drupal::routeMatch();
        $page_title = \Drupal::service('title_resolver')->getTitle($request, $route_match->getRouteObject());
        $route_name = \Drupal::routeMatch()->getRouteName();
        $arg_0 = \Drupal::routeMatch()->getParameter('arg_0');
        $path = \Drupal::service('path.current')->getPath();
        $url_object = \Drupal::service('path.validator')->getUrlIfValid($path);

        if($url_object){
            $query = \Drupal::database()->select('menu_tree', 'm');
            $query->fields('m');
            $query->condition('m.route_name', $route_name);
            if(!is_null($node)){
                $query->condition('m.route_param_key', 'node='.$node->id());
            }else{
                $query->condition('m.route_param_key', 'arg_0='.$arg_0);
            }
            if(strpos($route_name, 'view') === false){
                $query->condition('m.menu_name', 'main');
            }else{
                if($route_name == 'view.mes_drivers.liste_produits'){
                    $query->condition('m.menu_name', 'telechargement');
                }elseif ($route_name == 'view.produits.page_1'){
                    $query->condition('m.menu_name', 'main');
                }
            }
            $result = $query->execute()->fetchAssoc();
            $depth = $result['depth'];


            for ($i = 1; $i < $depth; $i++) {
                $queryDepth = \Drupal::database()->select('menu_tree', 'm');
                $queryDepth->fields('m');
                $queryDepth->condition('m.mlid', $result['p' . $i]);
                if(strpos($route_name, 'view') === false){
                    $queryDepth->condition('m.menu_name', 'main');
                }else{
                    if($route_name == 'view.mes_drivers.liste_produits'){
                        $queryDepth->condition('m.menu_name', 'telechargement');
                    }elseif ($route_name == 'view.produits.page_1'){
                        $queryDepth->condition('m.menu_name', 'main');
                    }
                }
                $resultDepth = $queryDepth->execute()->fetchAssoc();

                $argParent = array();
                $nodeParent = null;

                if (!empty($resultDepth['route_param_key'])) {
                    if(!is_null($node)){
                        $nid = str_replace('node=', '', $resultDepth['route_param_key']);
                        $nodeParent = \Drupal\node\Entity\Node::load($nid);
                    }else{
                        $argParent = str_replace('arg_0=', '', $resultDepth['route_param_key']);
                    }
                }

                $breadcrumb[] = $this->routeToUrl($resultDepth['route_name'], $argParent, $nodeParent);
            }
        }


        $breadcrumb[] = $this->routeToUrl($route_name, $arg_0, $node);

        $currentLink = end($breadcrumb);

        return array(
            'title_page' => $currentLink['name'],
            'links' => $breadcrumb,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function build() {
        return array(
            '#theme' => 'baw_breadcrumb_block',
            '#title' => 'Breadcrumb',
            '#description' => 'Create by BAW',
            '#breadcrumb' => $this->generateBreadcrumb(),
        );
    }
}