<?php

namespace Theodo\Bundle\Drupal8Bundle\Drupal;

use Drupal\bootstrap\Theme;
use Drupal\Core\CoreServiceProvider;
use Drupal\Core\Render\Renderer;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManager;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\DrupalKernel;

/**
 * Drupal class
 *
 * @author Thierry Marianne <thierrym@theodo.fr>
 * @author Kenny Durand <kennyd@theodo.fr>
 * @author Benjamin Grandfond <benjaming@theodo.fr>
 * @author Fabrice Bernhard <fabriceb@theodo.fr>
 */
class DrupalWrapper implements DrupalWrapperInterface
{

    /**
     * The root path of the Drupal core files
     * @var string
     */
    private $drupalDir;

    /**
     * The Symfony2 session service
     * @var string
     */
    private $session;

    /**
     * @var string
     */
    private $drupalKernel;

    /**
     * Indicates whether the Drupal core has exited cleanly
     * @var bool
     */
    private $catchExit = false;


    /**
     * {@inheritdoc}
     */
    private $response;

    /**
     * @param $drupalDir
     * @param ContainerInterface $serviceContainer
     */
    public function __construct($drupalDir, $session = null)
    {
        $this->drupalDir = $drupalDir;
        $this->session   = $session;
    }

    /**
     * The shutdown method only catch exit instruction from Drupal
     * to rebuild the correct response
     *
     * @return mixed
     */
    public function catchExit()
    {
        if (!$this->catchExit) {
            return;
        }

        if (null == $this->response) {
            $this->response = new Response();
        }

        $this->response->setContent(ob_get_contents());
        $this->response->send();
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Return true if the current drupal object contains a valid Response object
     *
     * @return bool
     */
    public function hasResponse()
    {
        return !empty($this->response);
    }

    /**
     * @param bool $isLegacy
     * @return DrupalKernel
     */
    private function bootDrupalKernel($isLegacy = true)
    {
        $currentDir = getcwd();
        chdir($this->drupalDir);

        $autoloader = require_once $this->drupalDir . '/autoload.php';
        $request = Request::createFromGlobals();
        /** @var DrupalKernel $kernel */
        $kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');


        if ($isLegacy) {
            $kernel->prepareLegacyRequest($request);
            $kernel->boot();
        }

        chdir($currentDir);

        return $kernel;
    }

    /**
     * @param bool $isLegacy
     * @return DrupalKernel
     */
    public function getDrupalKernel($isLegacy = true)
    {
        if (null === $this->drupalKernel) {
            $this->drupalKernel = $this->bootDrupalKernel($isLegacy);
        }

        return $this->drupalKernel;
    }

    /**
     * Bootstraps the Drupal Kernel and tries to get the Response object
     *
     * @param Request $request
     * @return Response
     */
    public function handle($request)
    {
        // handle possible exit() in Drupal code using a register shutdown function
        $this->catchExit = true;
        register_shutdown_function(array($this, 'catchExit'));

        ob_start();
        $response = null;

        // make sure the default path points to Drupal root
        $currentDir = getcwd();
        chdir($this->drupalDir);

        $isLegacy = false;
        $drupalKernel = $this->getDrupalKernel($isLegacy);

        $response = $drupalKernel->handle($request);
        $drupalKernel->terminate($request, $response);

        // if we are still here, there were no exit() used in Drupal code, we can unregister our shutdown_function
        $this->catchExit = false;

        // restore the symfony error handle
        restore_error_handler();
        restore_exception_handler();

        chdir($currentDir);


        return $response;
    }


    /**
     * @return Request
     */
    public function getRequest()
    {

        return $this->getDrupalKernel()->getContainer()->get('request');
    }

    /**
     *
     */
    public function initAnonymousDrupalUser()
    {
        $drupalUser = drupal_anonymous_user();
        $GLOBALS['user'] = $drupalUser;
        $this->getRequest()->attributes->set('_account', $drupalUser);
    }

    /**
     * @return Drupal\Core\Session\UserSession
     */
    public function getCurrentUser() {
        require_once DRUPAL_ROOT . '/' . \settings()->get('session_inc', 'core/includes/session.inc');
        $authentication = $this->getDrupalKernel()->getContainer()->get('authentication');

        $drupalUser = $authentication->authenticate($this->getRequest());
        $GLOBALS['user'] = $drupalUser;

        $this->session->migrate();

        return $drupalUser;
    }

    /**
     * @param $nodeId
     * @return mixed
     */
    public function getNode($nodeId, $langcode = "de")
    {
        $this->getDrupalKernel();

        $node = Node::load($nodeId);

        $render_controller = \Drupal::entityManager()->getViewBuilder($node->getEntityTypeId());

        return $render_controller->view($node, "full", $langcode);

    }

    public function renderNode($nodeId) {

        $this->getDrupalKernel();
        /** @var Renderer $renderer */
        $renderer = $this->getDrupalKernel()->getContainer()->get('renderer');

        $node = Node::load($nodeId);
        $build = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'full');

        return $renderer->renderRoot($build);
    }

    public function getMenu($menu_name) {

        $this->getDrupalKernel();
        /** @var Renderer $renderer */
        $renderer = $this->getDrupalKernel()->getContainer()->get('renderer');

        $menu_tree = \Drupal::menuTree();
        $parameters = $menu_tree->getCurrentRouteMenuTreeParameters($menu_name);

        $tree = $menu_tree->load($menu_name, $parameters);
        $menu = $menu_tree->build($tree);

        return $renderer->renderRoot($menu);
    }

    public function getBlock($block_id){

        $this->getDrupalKernel();


        $block_manager = $this->getDrupalKernel()->getContainer()->get('plugin.manager.block');
        $block_config = [];
        $block_plugin = $block_manager->createInstance($block_id, $block_config);

        $block_build = $block_plugin->build();

        return $block_build;
    }

    public function renderBlock($block_id){

        $this->getDrupalKernel();
        /** @var Renderer $renderer */
        $renderer = $this->getDrupalKernel()->getContainer()->get('renderer');

        $block_manager = $this->getDrupalKernel()->getContainer()->get('plugin.manager.block');
        $block_config = [];

        $block_plugin = $block_manager->createInstance($block_id, $block_config);


        $block_build = $block_plugin->build();

        return $renderer->renderRoot($block_build);
    }

    public function getBlockList() {

        $this->getDrupalKernel();

        $block_manager = $this->getDrupalKernel()->getContainer()->get('plugin.manager.block');

        $contextRepository = $this->getDrupalKernel()->getContainer()->get('context.repository');
        $definitions = $block_manager->getDefinitionsForContexts($contextRepository->getAvailableContexts());

        return $definitions;
    }

    public function getTheme() {

        $this->getDrupalKernel();

        /** @var ThemeManager $themeManager */
        $themeManager = $this->getDrupalKernel()->getContainer()->get('theme.manager');


        $theme = $themeManager->getActiveTheme();

        $themeName = $theme->getName();
        $theme_settings = \Drupal::config($themeName . '.settings')->get();

        $themeArray["theme"] = $theme;
        $themeArray["settings"] = $theme_settings;
        $themeArray["settings"]["fluid_container"] = true;

        return $themeArray;

    }

    public function preprocess_page ($variables) {

        $this->getDrupalKernel();

        template_preprocess_page($variables);

        return $variables;

    }

    public function createResponse(array $content, $title, $page_theme_property, array $page_additions = []) {

        $bareRenderer = $this->getDrupalKernel()->getContainer()->get('bare_html_page_renderer');

        return $bareRenderer->renderBarePage( $content, $title, $page_theme_property, $page_additions);

    }

    public function setLanguage($langcode)
    {
        $this->getDrupalKernel();

        $configFactory = $this->getDrupalKernel()->getContainer()->get('config.factory');

        $configFactory->getEditable('system.site')->set('default_langcode', $langcode)->save();

    }


}
