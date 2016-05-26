<?php
use Mouf\Integration\Magento\MagentoFallbackResponse;
use Mouf\Integration\Magento\MagentoHtmlElementBlock;
use Mouf\Mvc\Splash\HtmlResponse;
use Zend\Diactoros\Server;
use Mouf\Integration\Magento\MagentoTemplate;

class Mouf_Mvc_Model_Observer extends Varien_Event_Observer
{
	public function onDispatchToMouf($observer) {
		// Let's see if we can dispatch anything in Mouf!
		require_once __DIR__.'/../../../../../../../../../mouf/Mouf.php';

		$defaultMagentoController = $observer->getData('controller_action');

		/* @var $defaultMagentoController Mage_Cms_IndexController */

		$defaultRouter = Mouf::getDefaultRouter();

		$server = Server::createServer($defaultRouter, $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);


		define('ROOT_URL', parse_url(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_PATH));

		$callback = $server->callback;
		$response = $callback($server->request, $server->response, function($request, $response) {
			return new MagentoFallbackResponse();
		});
		/* @var $response \Psr\Http\Message\ResponseInterface */

		if (!$response instanceof MagentoFallbackResponse) {

			$emitter = new \Zend\Diactoros\Response\SapiEmitter();

			if ($response instanceof HtmlResponse  && $response->getHtmlElement() instanceof MagentoTemplate) {
				$defaultMagentoController->loadLayout();
				$magentoTemplate = $response->getHtmlElement();
				$magentoTemplate->setLayout($defaultMagentoController->getLayout());
				// Let's register the renderer
				$magentoTemplate->toHtml();

				// Let's register JS and CSS
				$magentoTemplate->getWebLibraryManager()->toHtml();

				$contentBlock = $magentoTemplate->getContent();
				$magentoContentBlock = new MagentoHtmlElementBlock($contentBlock);

				$defaultMagentoController->getLayout()->addBlock($magentoContentBlock, "my.content.mouf.block");

				foreach ($magentoTemplate->getBlocks() as $blockName => $moufBlock) {
					$magentoBlock = new MagentoHtmlElementBlock($moufBlock);
					$defaultMagentoController->getLayout()->addBlock($magentoBlock, "mouf.".$blockName);
					$defaultMagentoController->getLayout()->getBlock($blockName)->append($magentoBlock);
				}
				//var_dump(array_keys($defaultMagentoController->getLayout()->getAllBlocks()));

				$defaultMagentoController->getLayout()->getBlock('root')->setTemplate($magentoTemplate->getTemplate());

				$defaultMagentoController->getLayout()->getBlock('head')->setTitle($magentoTemplate->getTitle());

				$defaultMagentoController->getLayout()->getBlock('content')->append($magentoContentBlock /*$defaultMagentoController->getLayout()->createBlock('page/html_cookieNotice')*/);

				$defaultMagentoController->renderLayout();

				$defaultMagentoController->getResponse()->sendResponse();
			} else {
				$emitter->emit($response);
			}
			// Exit not very good, but we are acting on an event that does not allow us to do otherwise.
			exit;
		}
	}

}