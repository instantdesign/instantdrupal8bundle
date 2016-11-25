<?php

namespace Theodo\Bundle\Drupal8Bundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class DemoController extends Controller
{
    /**
     * @Template()
     */
    public function indexAction()
    {
        $drupalWrapper = $this->get('theodo_drupal8.drupal_wrapper');
        $drupalKernel = $drupalWrapper->getDrupalKernel();
        $drupalContainer = $drupalKernel->getContainer();

        dump($drupalWrapper->getBlockList());

        $content = array();
        $content = $drupalWrapper->preprocess_page($content);


        $content["page"]["#type"] = "page";

        $languagBlock = $drupalWrapper->getBlock("language_block:language_interface");

        $languagBlock["#attributes"] =  array('class' => array("links"));


        $header = array(
            '#prefix' => '<div class="region region-navigation-collapsible">',
            '#suffix' => '</div>',
            'content' =>
                array(
                    array(
                        '#prefix' => '<nav role="navigation" aria-labelledby="block-instant-main-menu-menu" id="block-instant-main-menu" class="contextual-region">',
                        '#suffix' => '</nav>',
                        'content' => array(
                            $drupalWrapper->getBlock("system_menu_block:main"),
                        )
                    ),
                    array(
                        '#prefix' => '<section class="language-switcher-language-url block block-language block-language-blocklanguage-interface clearfix" id="block-language-switcher" role="navigation">',
                        '#suffix' => '</section>',
                        '#attributes' => array(
                            'class' => array("links")
                        ),
                        'content' => array(
                            $languagBlock,
                        )
                    )
                )
        );

        $content["page"]["navigation_collapsible"] = $header;


        $content["page"]["content"] = array("#markup" => "Shopinhalt");


        $content["page"]["footer_col_01"] = $drupalWrapper->getBlock("system_menu_block:footer-col-01");
        $content["page"]["footer_col_02"] = $drupalWrapper->getBlock("system_menu_block:footer-col-02");
        $content["page"]["footer_col_03"] = $drupalWrapper->getBlock("system_menu_block:footer-col-03");



        $bareRenderer = $drupalContainer->get('bare_html_page_renderer');

        return $bareRenderer->renderBarePage( $content["page"], "Shop", "instant");

    }

}
