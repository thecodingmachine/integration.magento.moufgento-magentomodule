<?php
use Mouf\Integration\Magento\MagentoFallbackResponse;
use Mouf\Integration\Magento\ExceptionMagentoFallbackResponse;
use Mouf\Integration\Magento\MagentoHtmlElementBlock;
use Mouf\Mvc\Splash\HtmlResponse;
use Zend\Diactoros\Server;

class Mouf_Mvc_Model_Observer extends Varien_Event_Observer
{
	public function onDispatchToMouf($observer) {
		// Let's see if we can dispatch anything in Mouf!
		require_once __DIR__.'/../../../../../../../../../mouf/Mouf.php';

		$defaultMagentoController = $observer->getData('controller_action');

		/* @var $defaultMagentoController Mage_Cms_IndexController */

		$defaultRouter = Mouf::getDefaultRouter();

		$server = Server::createServer($defaultRouter, $_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);

		$magentoTemplate = Mouf::getMagentoTemplate();
		$magentoTemplate->setLayout($defaultMagentoController->getLayout());

		define('ROOT_URL', parse_url(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), PHP_URL_PATH));

		$callback = $server->callback;
		$response = $callback($server->request, $server->response, function($request, $response, $err = null) {
			/**
			 * If there is an error not handled by others middleware, our middleware is called with an exception as first argument (we are the final handler)
			 * We need to propagate the exception 
			 */
			if ($err instanceof \Exception) {
				return new ExceptionMagentoFallbackResponse($err);
			}
			return new MagentoFallbackResponse();
		});
		if ($response instanceof ExceptionMagentoFallbackResponse) {
			throw $response->getException();
		}
		/* @var $response \Psr\Http\Message\ResponseInterface */

		if (!$response instanceof MagentoFallbackResponse) {

			$emitter = new \Zend\Diactoros\Response\SapiEmitter();

			if ($response instanceof HtmlResponse  && $response->getHtmlElement() == $magentoTemplate) {
				$defaultMagentoController->loadLayout();

				// Let's register the renderer
				$magentoTemplate->toHtml();

				// Let's register JS and CSS
				$magentoTemplate->getWebLibraryManager()->toHtml();

				$contentBlock = Mouf::getMagentoTemplate()->getContent();
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